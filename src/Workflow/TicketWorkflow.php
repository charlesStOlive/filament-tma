<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Events\TicketCreated;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Moves;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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

        if (! $this->isPossible($ticket, $move)) {
            return false;
        }

        if ($context->isSystem()) {
            return $move->sides() === [];
        }

        $side = $this->sideFor($ticket, $move);

        return $side !== null && TmaGate::allows($context->user, TmaGate::workflowAction($move->key()), $ticket);
    }

    /** Les mouvements que ce contexte peut faire maintenant sur ce ticket. */
    public function available(Ticket $ticket, Context $context): array
    {
        return array_filter($this->moves, fn (Move $move): bool => $this->allows($ticket, $move, $context));
    }

    /** La partie au nom de laquelle cette application peut faire le mouvement, s'il y en a une. */
    public function sideFor(Ticket $ticket, Move $move): ?Side
    {
        foreach ($move->sides() as $side) {
            if (in_array($side, $ticket->sidesHere(), true) && $move->allowsSide($side, $ticket)) {
                return $side;
            }
        }

        return null;
    }

    /**
     * Fait le mouvement : contrôle, validation des données, écritures du mouvement, changement d'état, historique,
     * dans une seule transaction ; puis l'événement `TicketTransitioned`.
     *
     * @param  array<string, mixed>  $data
     */
    public function run(Ticket $ticket, string|Move $move, array $data, Context $context): StateTransition
    {
        $move = is_string($move) ? $this->move($move) : $move;

        if (! $this->allows($ticket, $move, $context)) {
            throw new AuthorizationException(__('Ce passage n’est pas possible pour ce ticket.'));
        }

        $data = Validator::make($data, $move->rules($ticket))->validate();

        $transition = DB::transaction(function () use ($ticket, $move, $data, $context): StateTransition {
            $from = $ticket->state;
            $to = $move->to($ticket);

            $move->apply($ticket, $data, $context);
            $ticket->state->transitionTo($to);

            $transition = $ticket->transitions()->create([
                'move' => $move->key(),
                'from_state' => $from::getMorphClass(),
                'to_state' => $to::getMorphClass(),
                'actor_id' => $context->actor?->getKey(),
                'origin' => $context->origin,
                'reason' => $move->reason($data),
            ]);

            $ticket->touchActivity();

            return $transition;
        });

        event(new TicketTransitioned($ticket->refresh(), $transition, $context));

        return $transition;
    }

    /**
     * Crée un ticket en brouillon, au nom de l'utilisateur du contexte.
     *
     * @param  array<string, mixed>  $attributes  title, description, priority, due_on
     */
    public function create(array $attributes, Context $context): Ticket
    {
        if (! $context->isSystem() && Gate::getPolicyFor(Ticket::class) !== null && ! Gate::forUser($context->user)->allows('create', Ticket::class)) {
            throw new AuthorizationException(__('Vous ne pouvez pas créer de ticket.'));
        }

        $ticket = Ticket::create([
            ...collect($attributes)->only(['title', 'description', 'priority', 'due_on'])->all(),
            'requester_actor_id' => $context->actor->getKey(),
            'created_by_actor_id' => $context->actor->getKey(),
        ]);

        event(new TicketCreated($ticket, $context));

        return $ticket;
    }
}
