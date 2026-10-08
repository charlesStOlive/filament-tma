<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Écrire dans la chronologie d'un ticket : commenter, noter, répondre, partager une note. Les droits sont contrôlés
 * ici, quelle que soit l'origine. Rien ici ne change l'état du ticket (CDC §9.4).
 */
final class Timeline
{
    /** Peut-on écrire ce type d'entrée sur ce ticket ? */
    public function canAdd(Ticket $ticket, EntryType $type, Context $context): bool
    {
        if ($context->isSystem()) {
            return true;
        }

        return match ($type) {
            // L'échange s'arrête avec le ticket ; les notes internes restent possibles.
            EntryType::Comment, EntryType::Summary => ! $ticket->state->isFinal() && TmaGate::allows($context->user, TmaGate::COMMENT, $ticket),
            EntryType::Note => TmaGate::allows($context->user, TmaGate::NOTE, $ticket),
        };
    }

    public function canReadNotes(Ticket $ticket, Context $context): bool
    {
        return TmaGate::allows($context->user, TmaGate::NOTE, $ticket);
    }

    public function canShare(Entry $note, Context $context): bool
    {
        return $note->type === EntryType::Note
            && $note->shared_at === null
            && $note->isWrittenHere()
            && TmaGate::allows($context->user, TmaGate::SHARE_NOTE, $note->subject);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function add(Ticket $ticket, EntryType $type, ?string $body, Context $context, ?Entry $parent = null, array $meta = []): Entry
    {
        if ($parent !== null) {
            $this->assertReplyable($ticket, $parent);
            $type = $parent->type;
        }

        if (! $this->canAdd($ticket, $type, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas ajouter cette entrée à ce ticket.'));
        }

        if (Html::isBlank($body)) {
            throw ValidationException::withMessages(['body' => __('Le message est vide.')]);
        }

        return $this->write($ticket, $type, $body, $context, $parent, $meta);
    }

    /**
     * Écrit l'entrée, sans contrôle de droit : pour un mouvement du workflow (la question de « Demander une
     * réponse »…), déjà autorisé par sa propre permission, ou pour ce qui arrive de l'autre application.
     *
     * @param  array<string, mixed>  $meta
     */
    public function write(Ticket $ticket, EntryType $type, string $body, Context $context, ?Entry $parent = null, array $meta = []): Entry
    {
        $entry = DB::transaction(function () use ($ticket, $type, $body, $context, $parent, $meta): Entry {
            $entry = $ticket->entries()->create([
                'type' => $type,
                'body' => $body,
                'parent_id' => $parent?->getKey(),
                'author_actor_id' => $context->actor?->getKey() ?? throw new \LogicException('Une entrée a toujours un auteur.'),
                'origin' => $context->origin,
                // La réponse à une note partagée est partagée aussi : l'autre partie suit le fil.
                'shared_at' => $type === EntryType::Note && $parent?->shared_at !== null ? now() : null,
                'meta' => $meta ?: null,
            ]);

            $ticket->touchActivity();

            return $entry;
        });

        event(new EntryAdded($entry, $context));

        return $entry;
    }

    public function share(Entry $note, Context $context): Entry
    {
        if (! $this->canShare($note, $context)) {
            throw new AuthorizationException(__('Cette note ne peut pas être partagée.'));
        }

        $note->forceFill(['shared_at' => now()])->save();

        event(new NoteShared($note, $context));

        return $note;
    }

    private function assertReplyable(Ticket $ticket, Entry $parent): void
    {
        $sameTicket = $parent->subject_type === $ticket->getMorphClass() && (int) $parent->subject_id === (int) $ticket->getKey();

        if (! $sameTicket || $parent->parent_id !== null) {
            throw ValidationException::withMessages(['parent' => __('On ne répond qu’à un message de premier niveau du même ticket.')]);
        }
    }
}
