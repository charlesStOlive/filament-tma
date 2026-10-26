<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\RecipientsChanged;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateInstance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * L'API du maître pour une application cliente (docs/API.md). L'application appelante vient du jeton
 * (`AuthenticateInstance`) : elle ne voit et ne touche que ses propres tickets, et n'y agit qu'en demandeur.
 */
class TicketController
{
    /** `POST tickets` : la transmission au Support. Idempotente (par l'ulid du ticket et de la transition). */
    public function store(Request $request): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);

        $payload = $request->validate([
            'ticket' => ['required', 'array'],
            'ticket.ulid' => ['required', 'string', 'size:26'],
            'ticket.number' => ['required', 'string', 'max:50'],
            'ticket.source_instance_uid' => ['required', 'string', 'in:'.$instance->uid],
            'ticket.title' => ['required', 'string', 'max:255'],
            'ticket.requester' => ['required', 'array'],
            'ticket.requester.instance_uid' => ['required', 'in:'.$instance->uid],
            'ticket.requester.id' => ['required'],
            'entries' => ['array'],
            'transition' => ['required', 'array'],
            'transition.ulid' => ['required', 'string', 'size:26'],
            'files' => ['array'],
            'recipients' => ['array'],
        ]);

        $existing = Ticket::withTrashed()->where('ulid', $payload['ticket']['ulid'])->first();
        abort_if($existing !== null && $existing->source_instance_uid !== $instance->uid, 403);

        [$ticket, $transition] = DB::transaction(function () use ($request, $instance, $existing): array {
            $alreadyTransmitted = $existing !== null;

            $ticket = Mirror::ticket($request->input('ticket'), [
                'state' => $alreadyTransmitted ? $existing->state::getMorphClass() : 'submitted',
                'client_type' => $instance->client_type,
                'client_id' => $instance->client_id,
                'last_activity_at' => now(),
                'sync_version' => $existing?->sync_version ?? 0,
            ]);

            foreach ($request->input('entries', []) as $entry) {
                Mirror::entry($ticket, $entry);
            }

            Mirror::files($ticket, $request->input('files', []));
            $transition = Mirror::transition($ticket, [...$request->input('transition'), 'to_state' => 'submitted']);

            // Ses destinataires à elle, et le Support (nos destinataires par défaut) : toujours un principal chez nous.
            app(Recipients::class)->fromClient($ticket, $instance->uid, $request->input('recipients', []));

            // Sans contact choisi chez le client, le seul membre du Support par défaut le devient (addendum §3.1).
            if ($ticket->assignee_actor_id === null && ($defaults = app(Recipients::class)->supportDefaults())->count() === 1) {
                $ticket->forceFill(['assignee_actor_id' => $defaults->first()->getKey()])->save();
            }

            return [$ticket, $transition];
        });

        // Le passage tel qu'écrit ici (relu par une requête, il ne dirait plus qu'il vient d'être créé) : une
        // transmission rejouée ne prévient pas deux fois.
        if ($transition->wasRecentlyCreated) {
            event(new TicketTransitioned($ticket, $transition, $this->context($request, $instance)));
        }

        return response()->json(Payload::snapshot($ticket->refresh()), $existing === null ? 201 : 200);
    }

    /** `GET tickets/{ulid}` : l'état d'un ticket de l'application appelante. */
    public function show(Request $request, string $ulid): JsonResponse
    {
        return response()->json(Payload::snapshot($this->ticket($request, $ulid)));
    }

    /**
     * `PATCH tickets/{ulid}` : l'auteur de la demande la modifie (titre, description, priorité, pièces jointes), tant
     * qu'aucun message ne l'a suivie (addendum §3.2).
     */
    public function demand(Request $request, string $ulid, TicketWorkflow $workflow): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $ticket = $this->ticket($request, $ulid);

        $request->validate([
            'ticket' => ['required', 'array'],
            'ticket.title' => ['required', 'string', 'max:255'],
            'ticket.description' => ['nullable', 'string'],
            'ticket.priority' => ['required', Rule::enum(Priority::class)],
            'files' => ['array'],
            'file_uuids' => ['array'],
            'actor' => ['required', 'array'],
            'actor.instance_uid' => ['required', 'in:'.$instance->uid],
            'actor.id' => ['required'],
        ]);

        abort_unless($workflow->mayEditDemand($ticket, $this->context($request, $instance)), 403,
            __('filament-tma::sync.errors.request_locked'));

        DB::transaction(function () use ($ticket, $request): void {
            $ticket->forceFill([
                'title' => $request->input('ticket.title'),
                'description' => $request->input('ticket.description'),
                'priority' => $request->input('ticket.priority'),
            ])->save();

            Mirror::files($ticket, $request->input('files', []));
            Mirror::keepFiles($ticket, (array) $request->input('file_uuids', []));
            $ticket->touchActivity();
        });

        event(new TicketUpdated($ticket->refresh()));

        return response()->json(Payload::snapshot($ticket->refresh()));
    }

    /** `POST tickets/{ulid}/moves` : un mouvement du demandeur, fait ici (le maître fait autorité). */
    public function move(Request $request, string $ulid, TicketWorkflow $workflow): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $ticket = $this->ticket($request, $ulid);

        $request->validate([
            'move' => ['required', 'string'],
            'data' => ['array'],
            'actor' => ['required', 'array'],
            'actor.instance_uid' => ['required', 'in:'.$instance->uid],
            'actor.id' => ['required'],
            'transition_ulid' => ['required', 'string', 'size:26'],
        ]);

        // Déjà fait : la demande a été rejouée (réseau…), on renvoie l'état sans refaire le mouvement.
        if (! $ticket->transitions()->where('ulid', $request->input('transition_ulid'))->exists()) {
            try {
                $workflow->run($ticket, $request->input('move'), $request->input('data', []), $this->context($request, $instance), $request->input('transition_ulid'));
            } catch (AuthorizationException $e) {
                abort(403, $e->getMessage());
            }
        }

        return response()->json(Payload::snapshot($ticket->refresh()));
    }

    /**
     * `POST tickets/{ulid}/entries` : un message, une réponse ou une note de l'application appelante ; renvoyé, le
     * même `ulid` modifie l'entrée, l'archive ou la supprime (voir `Mirror::entryFrom()`).
     */
    public function entry(Request $request, string $ulid): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $ticket = $this->ticket($request, $ulid);

        // Une nouvelle entrée est de l'application appelante ; renvoyée, une entrée garde son auteur et son origine.
        $isNew = ! $ticket->entries()->withTrashed()->where('ulid', (string) $request->input('ulid'))->exists();
        $payload = $request->validate([
            'ulid' => ['required', 'string', 'size:26'],
            'type' => ['required', 'in:'.implode(',', array_column(EntryType::cases(), 'value'))],
            'body' => ['nullable', 'string'],
            'author' => [$isNew ? 'required' : 'nullable', 'array'],
            'author.instance_uid' => $isNew ? ['required', 'in:'.$instance->uid] : ['nullable'],
            'origin_instance_uid' => $isNew ? ['required', 'in:'.$instance->uid] : ['nullable'],
            'files' => ['array'],
            'file_uuids' => ['array'],
            'private' => ['boolean'],
        ]);

        abort_if($isNew && ! $ticket->acceptsMessages() && $payload['type'] !== EntryType::Note->value, 422, __('filament-tma::sync.errors.ticket_finished'));
        // Le demandeur parle en répondant (un passage) ; à part, il n'écrit qu'une note ou une relance (addendum §3.2).
        abort_if($isNew && $payload['type'] === EntryType::Comment->value && empty($request->input('meta.reminder')), 403,
            __('filament-tma::common.ticket_message_needs_move'));

        $entry = DB::transaction(fn () => Mirror::entryFrom($instance->uid, $ticket, $request->all()));

        if ($isNew) {
            if (! $entry->private) {
                $ticket->touchActivity();
            }

            event(new EntryAdded($entry, $this->context($request, $instance)));
        }

        return response()->json(['entry' => Payload::entry($entry)], $isNew ? 201 : 200);
    }

    /**
     * `POST tickets/{ulid}/recipients` : les destinataires vus de l'application appelante (ses utilisateurs, le
     * Support) ; la réponse donne l'état complet à recopier.
     */
    public function recipients(Request $request, string $ulid): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $ticket = $this->ticket($request, $ulid);
        $request->validate(['recipients' => ['present', 'array'], 'actor' => ['required', 'array'], 'actor.instance_uid' => ['in:'.$instance->uid]]);

        $added = app(Recipients::class)->fromClient($ticket, $instance->uid, $request->input('recipients', []));
        Notifier::recipientsAdded($ticket, $added, Mirror::actor($request->input('actor')));
        event(new RecipientsChanged($ticket, $this->context($request, $instance)));

        return response()->json(Payload::snapshot($ticket->refresh()));
    }

    private function ticket(Request $request, string $ulid): Ticket
    {
        return Ticket::query()
            ->where('ulid', $ulid)
            ->where('source_instance_uid', AuthenticateInstance::instance($request)->uid)
            ->where('state', '!=', 'draft')
            ->firstOrFail();
    }

    private function context(Request $request, Instance $instance): Context
    {
        $actor = Mirror::actor($request->input('actor') ?? $request->input('author') ?? $request->input('transition.actor') ?? $request->input('ticket.requester'));

        return Context::api($actor, $instance->uid, fromMaster: false);
    }
}
