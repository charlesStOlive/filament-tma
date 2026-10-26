<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\TicketOpening;
use CharlesStOlive\FilamentTma\Events\TicketCreated;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Le workflow des tickets : la liste des mouvements, le contrôle de chacun et son exécution. Toute origine passe par
 * ici — interface, API, commande, planificateur — pour que la règle soit la même partout (CDC §13 : le masquage d'un
 * bouton n'est jamais le seul contrôle).
 *
 * La règle (addendum §3) : chaque état dit à qui est le tour ; dans le circuit normal, seule cette partie agit, et du
 * côté du demandeur, seulement le demandeur lui-même (`Move::allowsRequester()`). Les passages normaux ne sont pas des
 * droits : les droits disent qui est qui (correspondant du Support, membre du Support) et ouvrent les forçages
 * (`Move::override()`).
 *
 * Une application peut ajouter ou remplacer un mouvement : `app(TicketWorkflow::class)->register(new MonMouvement)`.
 */
class TicketWorkflow
{
    /** @var array<string, Move> */
    private array $moves = [];

    public function __construct()
    {
        foreach ([
            new Moves\Transmit,
            new Moves\Publish,
            new Moves\RequestAnswer,
            new Moves\SubmitForValidation,
            new Moves\Pause,
            new Moves\Resume,
            new Moves\Refuse,
            new Moves\Answer,
            new Moves\Validate,
            new Moves\Reopen,
            new Moves\TakeOver,
            new Moves\Cancel,
            new Moves\ForceReturn,
            new Moves\ForceClose,
            new Moves\AutoClose,
            new Moves\AutoResume,
            new Moves\AutoPublish,
        ] as $move) {
            $this->register($move);
        }
    }

    public function register(Move $move): static
    {
        $this->moves[$move->key()] = $move;

        return $this;
    }

    /** @return array<string, Move> */
    public function moves(): array
    {
        return $this->moves;
    }

    /** Les mouvements qu'une personne peut faire (ceux du système exclus). */
    public function userMoves(): array
    {
        return array_filter($this->moves, fn (Move $move): bool => $move->sides() !== []);
    }

    public function move(string $key): Move
    {
        return $this->moves[$key] ?? throw new InvalidArgumentException("Mouvement de ticket inconnu : {$key}");
    }

    /** Le mouvement est possible depuis l'état actuel, pour ce ticket, sans regarder qui le demande. */
    public function isPossible(Ticket $ticket, Move $move): bool
    {
        if (! in_array($ticket->state::class, $move->from(), true)) {
            return false;
        }

        $to = $move->to($ticket);

        // « Prendre la main » ne change pas l'état.
        return ($to === $ticket->state::class || $ticket->state->canTransitionTo($to)) && $move->isAvailable($ticket);
    }

    public function allows(Ticket $ticket, string|Move $move, Context $context): bool
    {
        $move = is_string($move) ? $this->move($move) : $move;
        $context = $context->forSubject($ticket);

        if (! $this->isPossible($ticket, $move)) {
            return false;
        }

        if ($context->isSystem()) {
            return $move->sides() === [];
        }

        $side = $this->sideFor($ticket, $move, $context);

        if ($side === null) {
            return false;
        }

        // Un forçage ne dépend que de son droit. L'autre application a déjà contrôlé celui de son utilisateur ; un
        // membre du Support agit avec ses droits du maître, que le maître contrôle.
        if (($override = $move->override()) !== null) {
            return $context->isAgent() || ($context->isApi() && $context->user === null) || TmaGate::allows($context->user, $override, $ticket);
        }

        return match ($side) {
            Side::Requester => $this->speaksAsRequester($ticket, $move, $context),
            Side::Handler => $this->isSupport($ticket, $context),
        };
    }

    /** Les mouvements que ce contexte peut faire maintenant sur ce ticket. */
    public function available(Ticket $ticket, Context $context): array
    {
        return array_filter($this->moves, fn (Move $move): bool => $this->allows($ticket, $move, $context));
    }

    /** La partie au nom de laquelle le contexte (cette application par défaut) peut faire le mouvement, s'il y en a une. */
    public function sideFor(Ticket $ticket, Move $move, ?Context $context = null): ?Side
    {
        $sides = $context !== null ? $ticket->sidesOf($context->instanceUid, $context->fromMaster) : $ticket->sidesHere();

        foreach ($move->sides() as $side) {
            if (in_array($side, $sides, true) && $move->allowsSide($side, $ticket)) {
                return $side;
            }
        }

        return null;
    }

    /**
     * Côté demandeur : la personne qui parle est celle que le passage attend (le demandeur, ou un destinataire
     * principal qui prend la main). Dans l'application cliente, il faut aussi être correspondant du Support ; un
     * membre du Support ne l'est jamais.
     */
    private function speaksAsRequester(Ticket $ticket, Move $move, Context $context): bool
    {
        if ($context->isAgent() || $context->actor === null) {
            return false;
        }

        if (! $context->isApi() && ! Addressables::isCorrespondent($context->user)) {
            return false;
        }

        return $move->allowsRequester($ticket, $context->actor);
    }

    /** Côté Support : un membre du Support (`tma.ticket.handle`), ou un membre relié qui agit depuis un client. */
    private function isSupport(Ticket $ticket, Context $context): bool
    {
        return $context->isAgent() || ($context->user !== null && TmaGate::allows($context->user, TmaGate::HANDLE, $ticket));
    }

    /**
     * Modifier la demande (titre, description, priorité, pièces jointes) : son auteur, en brouillon, puis tant qu'aucun
     * message ne l'a suivie — elle compte comme le premier message (addendum §3.2).
     */
    public function mayEditDemand(Ticket $ticket, Context $context): bool
    {
        $context = $context->forSubject($ticket);

        if ($ticket->state->isFinal() || $context->isAgent() || $context->actor === null) {
            return false;
        }

        if ((int) $ticket->created_by_actor_id !== (int) $context->actor->getKey()) {
            return false;
        }

        if ($ticket->state instanceof Draft) {
            return Tma::isHere($ticket->created_on_instance_uid);
        }

        return ! $ticket->entries()->messages()->exists();
    }

    /** Retoucher le titre et la priorité : le Support, à son tour, là où il fait autorité. */
    public function mayQualify(Ticket $ticket, Context $context): bool
    {
        $context = $context->forSubject($ticket);

        return ! $ticket->state instanceof Draft && ! $ticket->state->isFinal() && $ticket->authorityIsHere()
            && $ticket->sideInCharge() === Side::Handler && $this->isSupport($ticket, $context);
    }

    /**
     * Fait le mouvement : contrôle, validation des données, écritures du mouvement, changement d'état, historique,
     * dans une seule transaction ; puis l'événement `TicketTransitioned`.
     *
     * Sur un ticket du Support, une application cliente ne fait pas le mouvement elle-même : elle le
     * demande au maître, qui fait autorité, et recopie le résultat. Si le maître ne répond pas, rien n'est fait.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $transitionUlid  l'identifiant de la transition, fixé par l'application qui la demande
     *                                       (une même demande reçue deux fois ne fait le mouvement qu'une fois)
     */
    public function run(Ticket $ticket, string|Move $move, array $data, Context $context, ?string $transitionUlid = null): StateTransition
    {
        $move = is_string($move) ? $this->move($move) : $move;
        $context = $context->forSubject($ticket);

        if (! $this->allows($ticket, $move, $context)) {
            throw new AuthorizationException(__('filament-tma::workflow.errors.ticket_move'));
        }

        $data = Validator::make($data, $move->rules($ticket))->validate();
        $transitionUlid ??= (string) Str::ulid();

        if ($context->isAgent()) {
            $snapshot = app(MasterClient::class)->agentMove($context->agent, $ticket, $move->key(), $data, $transitionUlid);
            DB::transaction(fn () => Mirror::snapshot($snapshot));
            $transition = StateTransition::query()->where('ulid', $transitionUlid)->firstOrFail();
        } elseif ($this->delegatesToMaster($ticket, $context)) {
            $snapshot = app(MasterClient::class)->runMove($ticket, $move->key(), $data, $context, $transitionUlid);
            DB::transaction(fn () => Mirror::snapshot($snapshot));
            $transition = StateTransition::query()->where('ulid', $transitionUlid)->firstOrFail();
        } else {
            $transition = DB::transaction(function () use ($ticket, $move, $data, $context, $transitionUlid): StateTransition {
                $from = $ticket->state;
                $to = $move->to($ticket);

                app(Timeline::class)->forTransition($transitionUlid, fn () => $move->apply($ticket, $data, $context));

                if ($to !== $from::class) {
                    $ticket->state->transitionTo($to);
                } else {
                    $ticket->save();
                }

                $transition = $ticket->transitions()->create([
                    'ulid' => $transitionUlid,
                    'move' => $move->key(),
                    'from_state' => $from::getMorphClass(),
                    'to_state' => $to::getMorphClass(),
                    'actor_id' => $context->actor?->getKey(),
                    'origin_instance_uid' => $context->instanceUid,
                    'origin' => $context->origin,
                    'reason' => $move->reason($data),
                ]);

                $ticket->touchActivity();
                $move->committing($ticket, $transition, $context);

                return $transition;
            });
        }

        event(new TicketTransitioned($ticket->refresh(), $transition, $context));

        return $transition;
    }

    /** Une application cliente demande au maître ce qui touche un ticket transmis. */
    public function delegatesToMaster(Ticket $ticket, Context $context): bool
    {
        return Tma::isClient() && $ticket->isShared() && ! $context->isApi();
    }

    /**
     * Le maître prépare un ticket pour une application cliente, au nom de l'un de ses correspondants (addendum §3.5) :
     * un brouillon invisible chez le client jusqu'à sa publication (tout de suite, ou à `publish_at`).
     *
     * @param  array<string, mixed>  $attributes  title, description, priority, publish_at
     * @param  array{id: string, name: string, email?: string|null}  $requester  un utilisateur de l'application (`Sync\RemoteUsers`)
     */
    public function createForClient(Instance $instance, array $requester, array $attributes, Context $context): Ticket
    {
        if (! Tma::isMaster()) {
            throw new AuthorizationException(__('filament-tma::workflow.errors.support_prepares'));
        }

        if (! $context->isSystem() && ! $this->mayCreate($context)) {
            throw new AuthorizationException(__('filament-tma::workflow.errors.cannot_create_ticket'));
        }

        $actor = Actor::fromInstance($instance->uid, (string) $requester['id'], (string) $requester['name'], $requester['email'] ?? null);

        $ticket = Ticket::create([
            ...collect($attributes)->only(['title', 'description', 'priority', 'publish_at', 'task_id'])->all(),
            // Au nom d'un client, ou le Support qui s'adresse à lui, ou qui l'informe.
            'opening' => in_array($opening = TicketOpening::tryFrom((string) ($attributes['opening'] ?? '')), TicketOpening::forSupport(), true) ? $opening : TicketOpening::OnBehalf,
            'source_instance_uid' => $instance->uid,
            'created_on_instance_uid' => Tma::instanceUid(),
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
            'requester_actor_id' => $actor->getKey(),
            'created_by_actor_id' => $context->actor->getKey(),
            // Son auteur en est le contact au Support.
            'assignee_actor_id' => $context->actor->getKey(),
        ]);

        // Son auteur et son demandeur en sont d'office destinataires principaux.
        app(Recipients::class)->add($ticket, $context->actor);
        app(Recipients::class)->add($ticket, $actor);

        event(new TicketCreated($ticket, $context));

        return $ticket;
    }

    /**
     * Crée une demande en brouillon, au nom du correspondant du Support du contexte. Dans le maître, un ticket se
     * prépare pour un client (`createForClient()`).
     *
     * @param  array<string, mixed>  $attributes  title, description, priority, task_id (la tâche dont il part)
     */
    public function create(array $attributes, Context $context): Ticket
    {
        if (Tma::isMaster()) {
            throw new AuthorizationException(__('filament-tma::workflow.errors.prepare_for_client'));
        }

        if (! $context->isSystem() && ! $this->mayCreate($context)) {
            throw new AuthorizationException(__('filament-tma::workflow.errors.cannot_create_ticket'));
        }

        $ticket = Ticket::create([
            ...collect($attributes)->only(['title', 'description', 'priority', 'task_id'])->all(),
            'requester_actor_id' => $context->actor->getKey(),
            'created_by_actor_id' => $context->actor->getKey(),
        ]);

        // Son auteur en est d'office destinataire principal.
        app(Recipients::class)->add($ticket, $context->actor);

        event(new TicketCreated($ticket, $context));

        return $ticket;
    }

    /** Faire une demande : le droit de créer, et être correspondant du Support (client) ou membre du Support (maître). */
    public function mayCreate(Context $context): bool
    {
        $user = $context->user;

        if ($user === null || (Gate::getPolicyFor(Ticket::class) !== null && ! Gate::forUser($user)->allows('create', Ticket::class))) {
            return false;
        }

        return Tma::isMaster() ? TmaGate::allows($user, TmaGate::HANDLE) : Addressables::isCorrespondent($user);
    }
}
