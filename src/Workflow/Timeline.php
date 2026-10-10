<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Écrire dans la chronologie d'un ticket ou d'une tâche projet : commenter, noter, répondre, partager une note. Les droits sont contrôlés
 * ici, quelle que soit l'origine. Rien ici ne change l'état du ticket (CDC §9.4).
 */
final class Timeline
{
    /** Peut-on écrire ce type d'entrée sur ce ticket ? */
    public function canAdd(Trackable $ticket, EntryType $type, Context $context): bool
    {
        if ($context->isSystem()) {
            return true;
        }

        // Un membre du Support écrit dans le maître, qui contrôle ses droits de là-bas.
        if ($context->forSubject($ticket)->isAgent()) {
            return $type !== EntryType::Comment || ! $ticket->state->isFinal();
        }

        return match ($type) {
            // L'échange s'arrête avec le ticket ; un résumé d'intervention (souvent écrit après coup, avec le temps) et
            // les notes internes restent possibles.
            EntryType::Comment => ! $ticket->state->isFinal() && TmaGate::allows($context->user, TmaGate::COMMENT, $ticket),
            EntryType::Summary => TmaGate::allows($context->user, TmaGate::COMMENT, $ticket),
            EntryType::Note => TmaGate::allows($context->user, TmaGate::NOTE, $ticket),
        };
    }

    public function canReadNotes(Trackable $ticket, Context $context): bool
    {
        return TmaGate::allows($context->user, TmaGate::NOTE, $ticket);
    }

    public function canShare(Entry $note, Context $context): bool
    {
        // Partager une note d'ici, c'est parler pour l'application cliente : pas le rôle d'un membre du Support.
        if ($note->subject instanceof Trackable && $context->forSubject($note->subject)->isAgent()) {
            return false;
        }

        return $note->type === EntryType::Note
            && $note->shared_at === null
            && $note->isWrittenHere()
            && TmaGate::allows($context->user, TmaGate::SHARE_NOTE, $note->subject);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{path: string, name?: string}>  $uploads  fichiers envoyés (disque des pièces jointes)
     */
    public function add(Trackable $ticket, EntryType $type, ?string $body, Context $context, ?Entry $parent = null, array $meta = [], array $uploads = []): Entry
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

        $agentContext = $context->forSubject($ticket);

        if ($agentContext->isAgent()) {
            return $this->writeAsAgent($ticket, $type, (string) $body, $agentContext, $parent, $uploads);
        }

        return $this->write($ticket, $type, $body, $context, $parent, $meta, $uploads);
    }

    /**
     * Un membre du Support écrit, depuis une application cliente, dans le maître et en son nom de là-bas ; ce
     * qui est partagé revient ici par l'état recopié. Sa note reste une note du Support : elle n'est pas
     * recopiée ici (on la retrouve dans le maître).
     *
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    private function writeAsAgent(Trackable $subject, EntryType $type, string $body, Context $context, ?Entry $parent, array $uploads): Entry
    {
        $result = app(MasterClient::class)->agentEntry($context->agent, $subject, $type->value, $body, $parent?->ulid, Uploads::toBase64($uploads));

        DB::transaction(fn () => Mirror::snapshot($result['snapshot']));
        Uploads::discard($uploads);

        return Entry::query()->where('ulid', $result['entry']['ulid'])->first()
            ?? (new Entry)->forceFill(['ulid' => $result['entry']['ulid'], 'type' => $type, 'body' => $body]);
    }

    /**
     * Écrit l'entrée, sans contrôle de droit : pour un mouvement du workflow (la question de « Demander une
     * réponse »…), déjà autorisé par sa propre permission, ou pour ce qui arrive de l'autre application.
     *
     * Sur un ticket du Support, une application cliente envoie au maître ce qui est partagé, dans la
     * transaction : si le maître ne répond pas, rien n'est écrit.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    public function write(Trackable $ticket, EntryType $type, string $body, Context $context, ?Entry $parent = null, array $meta = [], array $uploads = []): Entry
    {
        $entry = DB::transaction(function () use ($ticket, $type, $body, $context, $parent, $meta, $uploads): Entry {
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

            Uploads::attach($entry, $uploads);
            $ticket->touchActivity();

            if ($entry->isShared() && $this->sendsToMaster($ticket, $context)) {
                app(MasterClient::class)->sendEntry($entry);
            }

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

        DB::transaction(function () use ($note, $context): void {
            $note->forceFill(['shared_at' => now()])->save();

            if ($this->sendsToMaster($note->subject, $context)) {
                app(MasterClient::class)->sendEntry($note);
            }
        });

        event(new NoteShared($note, $context));

        return $note;
    }

    /** Un client envoie au maître ce qu'il écrit sur un objet partagé dont le maître fait autorité. */
    private function sendsToMaster(Trackable $subject, Context $context): bool
    {
        return Tma::isClient() && $subject->isShared() && ! $subject->authorityIsHere() && ! $context->isApi();
    }

    private function assertReplyable(Trackable $ticket, Entry $parent): void
    {
        $sameTicket = $parent->subject_type === $ticket->getMorphClass() && (int) $parent->subject_id === (int) $ticket->getKey();

        if (! $sameTicket || $parent->parent_id !== null) {
            throw ValidationException::withMessages(['parent' => __('On ne répond qu’à un message de premier niveau du même ticket.')]);
        }
    }
}
