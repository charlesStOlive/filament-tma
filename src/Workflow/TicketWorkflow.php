<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TicketCreated;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
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
 * Le workflow des tickets : la liste des mouvements, le contrôle de chacun (état, partie, droit) et son exécution.
 * Toute origine passe par ici — interface, API, commande, planificateur — pour que la règle soit la même partout
 * (CDC §13 : le masquage d'un bouton n'est jamais le seul contrôle).
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
            new Moves\Submit,
            new Moves\Transmit,
            new Moves\TakeCharge,
            new Moves\RequestAnswer,
            new Moves\Answer,
            new Moves\Pause,
            new Moves\Resume,
            new Moves\SubmitForValidation,
            new Moves\Validate,
            new Moves\Reopen,
            new Moves\Close,
            new Moves\Refuse,
            new Moves\Cancel,
            new Moves\AutoClose,
            new Moves\AutoResume,
            new Moves\Publish,
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

    /** Les mouvements qu'un utilisateur peut faire (ceux du système exclus) : leurs permissions. */
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

        return $ticket->state->canTransitionTo($move->to($ticket)) && $move->isAvailable($ticket);
    }

    public function allows(Ticket $ticket, string|Move $move, Context $context): bool
    {
        $move = is_string($move) ? $this->move($move) : $move;
        $context = $context->forSubject($ticket);

        // Un membre du Support ne transmet pas une demande au Support : il en fait partie.
        if ($move->key() === 'transmit' && Agent::for($context->user) !== null) {
            return false;
        }

        if (! $this->isPossible($ticket, $move)) {
            return false;
        }

        // Qui peut parler au Support (le droit « Transmettre au Support ») forme l'équipe du client : ses demandes à
        // lui partent au Support, il ne les confie pas à sa propre équipe.
        if ($move->key() === 'submit' && $this->allows($ticket, 'transmit', $context)) {
            return false;
        }

        if ($context->isSystem()) {
            return $move->sides() === [];
        }

        if ($this->sideFor($ticket, $move, $context) === null) {
            return false;
        }

        // L'autre application a déjà contrôlé le droit de son utilisateur ; ici, seule compte la partie qu'elle
        // représente pour ce ticket. Un agent agit avec ses droits du maître, que le maître contrôle.
        if ($context->isAgent()) {
            return true;
        }

        return ($context->isApi() && $context->user === null) || TmaGate::allows($context->user, TmaGate::workflowAction($move->key()), $ticket);
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
            throw new AuthorizationException(__('Ce passage n’est pas possible pour ce ticket.'));
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

                $move->apply($ticket, $data, $context);
                $ticket->state->transitionTo($to);

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

    /** Une application cliente demande au maître ce qui touche un ticket du Support. */
    public function delegatesToMaster(Ticket $ticket, Context $context): bool
    {
        return Tma::isClient() && $ticket->handling_mode === HandlingMode::ServiceDesk && ! $context->isApi();
    }

    /**
     * Le maître prépare un ticket pour une application cliente, au nom de l'un de ses utilisateurs (addendum §3) :
     * un brouillon invisible chez le client jusqu'à sa publication (tout de suite, ou à `publish_at`).
     *
     * @param  array<string, mixed>  $attributes  title, description, priority, publish_at
     * @param  array{id: string, name: string, email?: string|null}  $requester  un utilisateur de l'application (`Sync\RemoteUsers`)
     */
    public function createForClient(Instance $instance, array $requester, array $attributes, Context $context): Ticket
    {
        if (! Tma::isMaster()) {
            throw new AuthorizationException(__('Seul le Support prépare un ticket pour un client.'));
        }

        if (! $context->isSystem() && Gate::getPolicyFor(Ticket::class) !== null && ! Gate::forUser($context->user)->allows('create', Ticket::class)) {
            throw new AuthorizationException(__('Vous ne pouvez pas créer de ticket.'));
        }

        $actor = Actor::fromInstance($instance->uid, (string) $requester['id'], (string) $requester['name'], $requester['email'] ?? null);

        $ticket = Ticket::create([
            ...collect($attributes)->only(['title', 'description', 'priority', 'publish_at'])->all(),
            'source_instance_uid' => $instance->uid,
            'created_on_instance_uid' => Tma::instanceUid(),
            'handling_mode' => HandlingMode::ServiceDesk,
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
            'requester_actor_id' => $actor->getKey(),
            'created_by_actor_id' => $context->actor->getKey(),
        ]);

        event(new TicketCreated($ticket, $context));

        return $ticket;
    }

    /**
     * Crée un ticket en brouillon, au nom de l'utilisateur du contexte.
     *
     * @param  array<string, mixed>  $attributes  title, description, priority
     */
    public function create(array $attributes, Context $context): Ticket
    {
        if (! $context->isSystem() && Gate::getPolicyFor(Ticket::class) !== null && ! Gate::forUser($context->user)->allows('create', Ticket::class)) {
            throw new AuthorizationException(__('Vous ne pouvez pas créer de ticket.'));
        }

        $ticket = Ticket::create([
            ...collect($attributes)->only(['title', 'description', 'priority'])->all(),
            'requester_actor_id' => $context->actor->getKey(),
            'created_by_actor_id' => $context->actor->getKey(),
        ]);

        event(new TicketCreated($ticket, $context));

        return $ticket;
    }
}
