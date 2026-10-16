<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\EntryChanged;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Addressables;
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
 * Écrire dans la chronologie d'un ticket ou d'une tâche projet : un message, une réponse, une note ; modifier son
 * message ou sa note, archiver une note, supprimer la sienne. Les droits sont contrôlés ici, quelle que soit
 * l'origine. Rien ici ne change l'état du ticket (CDC §9.4).
 *
 * Tout ce qu'on écrit est lu des deux côtés : sur un ticket du Support, une application cliente l'envoie au maître
 * au moment où on l'écrit, et un membre du Support qui travaille chez le client écrit directement dans le maître.
 * Une note privée suit le même chemin (son auteur la retrouve des deux côtés), mais ne se montre qu'à lui.
 */
final class Timeline
{
    /** Le passage en train de se faire : les messages qu'il écrit le citent (`meta.transition`), voir `forTransition()`. */
    private ?string $transitionUlid = null;

    /**
     * Ce que `$writes` écrit fait partie du passage `$transitionUlid` (sa question, sa réponse, son mot de clôture) :
     * l'historique montre le message et le changement d'état ensemble. Un message écrit à part n'est pas lié.
     */
    public function forTransition(string $transitionUlid, \Closure $writes): mixed
    {
        $previous = $this->transitionUlid;
        $this->transitionUlid = $transitionUlid;

        try {
            return $writes();
        } finally {
            $this->transitionUlid = $previous;
        }
    }

    /** Peut-on écrire ce type d'entrée sur ce ticket ? */
    public function canAdd(Trackable $ticket, EntryType $type, Context $context): bool
    {
        if ($context->isSystem()) {
            return true;
        }

        if ($type === EntryType::Note && ! self::notesEnabled()) {
            return false;
        }

        // Un membre du Support écrit dans le maître, qui contrôle ses droits de là-bas.
        if ($context->forSubject($ticket)->isAgent()) {
            return $type === EntryType::Note || $ticket->acceptsMessages();
        }

        // Un interne n'écrit rien sur ce qui touche le Support (ticket transmis, tâche) : un correspondant le fait.
        if (Addressables::keptOut($ticket, $context->user)) {
            return false;
        }

        return match ($type) {
            // L'échange s'arrête avec le ticket (pas avec une tâche terminée) ; les notes restent possibles.
            EntryType::Comment => $ticket->acceptsMessages() && TmaGate::allows($context->user, TmaGate::COMMENT, $ticket),
            EntryType::Note => TmaGate::allows($context->user, TmaGate::NOTE, $ticket),
        };
    }

    /** Les notes existent dans cette application (`filament-tma.notes.enabled`). */
    public static function notesEnabled(): bool
    {
        return (bool) config('filament-tma.notes.enabled', true);
    }

    /**
     * Modifier un message ou une note : son auteur seul. Un message, tant que personne d'autre n'a écrit de message
     * après lui (`Entry::isStillEditable()`), même si son auteur a perdu la main.
     */
    public function canEdit(Entry $entry, Context $context): bool
    {
        $subject = $entry->subject;

        if (! $subject instanceof Trackable || $entry->trashed() || ($entry->isNote() && ! self::notesEnabled()) || Addressables::keptOut($subject, $context->user)) {
            return false;
        }

        return $this->isAuthor($entry, $context->forSubject($subject)) && $entry->isStillEditable();
    }

    /** Archiver une note (ou la ressortir) : quiconque peut écrire des notes sur ce ticket ; une note privée, son auteur. */
    public function canArchive(Entry $note, Context $context): bool
    {
        return $note->isNote() && ! $note->trashed() && $note->subject instanceof Trackable
            && $this->canAdd($note->subject, EntryType::Note, $context)
            && (! $note->private || $this->isAuthor($note, $context->forSubject($note->subject)));
    }

    /** Supprimer une note : son auteur seul. Un message ne se supprime pas. */
    public function canDelete(Entry $note, Context $context): bool
    {
        return $note->isNote() && $this->canEdit($note, $context);
    }

    /**
     * @param  Entry|null  $parent  le message auquel on répond ; pour une note, le message qu'elle commente (sans
     *                              parent, elle porte sur le ticket entier)
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{path: string, name?: string}>  $uploads  fichiers envoyés (disque des pièces jointes)
     * @param  bool  $private  une note que son auteur garde pour lui
     */
    public function add(Trackable $ticket, EntryType $type, ?string $body, Context $context, ?Entry $parent = null, array $meta = [], array $uploads = [], bool $private = false): Entry
    {
        $private = $private && $type === EntryType::Note;

        if ($parent !== null) {
            $this->assertParent($ticket, $type, $parent);
        }

        if (! $this->canAdd($ticket, $type, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas ajouter cette entrée à ce ticket.'));
        }

        $this->assertNotBlank($type, $body, count($uploads));

        $agentContext = $context->forSubject($ticket);

        if ($agentContext->isAgent()) {
            return $this->writeAsAgent($ticket, $type, (string) $body, $agentContext, $parent, $uploads, $private, ! empty($meta['reminder']));
        }

        return $this->write($ticket, $type, (string) $body, $context, $parent, $meta, $uploads, $private);
    }

    /**
     * Un membre du Support écrit, depuis une application cliente, dans le maître et en son nom de là-bas ; l'entrée
     * revient ici par l'état recopié. Une relance (`$reminder`) le reste là-bas.
     *
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    private function writeAsAgent(Trackable $subject, EntryType $type, string $body, Context $context, ?Entry $parent, array $uploads, bool $private, bool $reminder = false): Entry
    {
        $result = app(MasterClient::class)->agentEntry($context->agent, $subject, $type->value, $body, $parent?->ulid, Uploads::toBase64($uploads), $private, $reminder);

        DB::transaction(fn () => Mirror::snapshot($result['snapshot']));
        Uploads::discard($uploads);

        return Entry::query()->where('ulid', $result['entry']['ulid'])->first()
            ?? (new Entry)->forceFill(['ulid' => $result['entry']['ulid'], 'type' => $type, 'body' => $body]);
    }

    /**
     * Écrit l'entrée, sans contrôle de droit : pour un mouvement du workflow (la question de « Demander une
     * réponse »…), déjà autorisé par sa propre permission, ou pour ce qui arrive de l'autre application.
     *
     * Sur un ticket du Support, une application cliente envoie l'entrée au maître, dans la transaction : si le
     * maître ne répond pas, rien n'est écrit. Une note prend le numéro que le maître lui donne (deux notes écrites en
     * même temps des deux côtés ne gardent pas le même). Une note privée ne change pas la dernière activité du
     * ticket, que les autres voient.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    public function write(Trackable $ticket, EntryType $type, string $body, Context $context, ?Entry $parent = null, array $meta = [], array $uploads = [], bool $private = false): Entry
    {
        if ($this->transitionUlid !== null && $type === EntryType::Comment) {
            $meta['transition'] = $this->transitionUlid;
        }

        $entry = DB::transaction(function () use ($ticket, $type, $body, $context, $parent, $meta, $uploads, $private): Entry {
            $entry = $ticket->entries()->create([
                'type' => $type,
                'private' => $private && $type === EntryType::Note,
                'body' => $body,
                'parent_id' => $parent?->getKey(),
                'author_actor_id' => $context->actor?->getKey() ?? throw new \LogicException('Une entrée a toujours un auteur.'),
                'origin' => $context->origin,
                'meta' => $meta ?: null,
            ]);

            Uploads::attach($entry, $uploads);

            if (! $entry->private) {
                $ticket->touchActivity();
            }

            if ($this->sendsToMaster($ticket, $context)) {
                $number = app(MasterClient::class)->sendEntry($entry)['number'] ?? null;

                if ($entry->isNote() && $number !== null && (int) $number !== $entry->number) {
                    $entry->forceFill(['number' => (int) $number])->saveQuietly();
                }
            }

            return $entry;
        });

        event(new EntryAdded($entry, $context));

        return $entry;
    }

    /**
     * Change le texte d'un message ou d'une note ; pour une note, ajoute des images (`$uploads`) ou en retire
     * (`$removeFiles`, par identifiant : `Uploads::uuidOf()`), et la garde pour soi ou la montre à tous (`$private`,
     * null : sans changement).
     *
     * @param  array<int, array{path: string, name?: string}>  $uploads
     * @param  array<int, string>  $removeFiles
     */
    public function edit(Entry $entry, ?string $body, Context $context, array $uploads = [], array $removeFiles = [], ?bool $private = null): Entry
    {
        $private = $entry->isNote() ? $private : null;

        if (! $this->canEdit($entry, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez plus modifier cette entrée.'));
        }

        $remainingFiles = $entry->getMedia(Entry::ATTACHMENTS_COLLECTION)
            ->reject(fn ($media): bool => in_array(Uploads::uuidOf($media), $removeFiles, true))
            ->count();
        $this->assertNotBlank($entry->type, $body, $remainingFiles + count($uploads));

        $agentContext = $context->forSubject($entry->subject);

        if ($agentContext->isAgent()) {
            return $this->changeAsAgent($entry, $agentContext, array_filter([
                'body' => (string) $body,
                'files' => Uploads::toBase64($uploads),
                'remove_files' => $removeFiles,
                'private' => $private,
            ], fn (mixed $value): bool => $value !== null), $uploads);
        }

        return $this->change($entry, $context, EntryChanged::EDITED, function () use ($entry, $body, $uploads, $removeFiles, $private): void {
            $entry->forceFill(['body' => (string) $body, 'edited_at' => now(), 'private' => $private ?? $entry->private])->save();
            Uploads::attach($entry, $uploads);
            Uploads::remove($entry, $removeFiles);
        });
    }

    /** Archive une note dont on n'a plus besoin, ou la ressort (`$archived = false`). */
    public function archive(Entry $note, bool $archived, Context $context): Entry
    {
        if (! $this->canArchive($note, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas archiver cette note.'));
        }

        $agentContext = $context->forSubject($note->subject);

        if ($agentContext->isAgent()) {
            return $this->changeAsAgent($note, $agentContext, ['archived' => $archived]);
        }

        return $this->change($note, $context, $archived ? EntryChanged::ARCHIVED : EntryChanged::RESTORED, function () use ($note, $archived): void {
            $note->forceFill(['archived_at' => $archived ? now() : null])->save();
        }, touches: false);
    }

    /** Supprime une note (son auteur seul). Son numéro n'est pas redonné. */
    public function delete(Entry $note, Context $context): Entry
    {
        if (! $this->canDelete($note, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas supprimer cette note.'));
        }

        $agentContext = $context->forSubject($note->subject);

        if ($agentContext->isAgent()) {
            return $this->changeAsAgent($note, $agentContext, ['delete' => true]);
        }

        return $this->change($note, $context, EntryChanged::DELETED, fn () => $note->delete(), touches: false);
    }

    /** Applique un changement ici, l'envoie au maître s'il fait autorité (dans la transaction), et l'annonce. */
    private function change(Entry $entry, Context $context, string $change, \Closure $apply, bool $touches = true): Entry
    {
        $subject = $entry->subject;

        DB::transaction(function () use ($entry, $subject, $context, $apply, $touches): void {
            $apply();

            if ($touches) {
                $subject->touchActivity();
            }

            if ($this->sendsToMaster($subject, $context)) {
                app(MasterClient::class)->sendEntry($entry);
            }
        });

        event(new EntryChanged($entry, $context, $change));

        return $entry;
    }

    /**
     * Un membre du Support, depuis une application cliente, change son entrée (ou archive une note) dans le maître ;
     * le résultat revient ici par l'état recopié.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    private function changeAsAgent(Entry $entry, Context $context, array $data, array $uploads = []): Entry
    {
        $snapshot = app(MasterClient::class)->agentChangeEntry($context->agent, $entry->subject, $entry->ulid, $data);

        DB::transaction(fn () => Mirror::snapshot($snapshot));
        Uploads::discard($uploads);

        return Entry::withTrashed()->where('ulid', $entry->ulid)->first() ?? $entry;
    }

    /**
     * L'auteur d'une entrée, ici : l'acteur de l'utilisateur ; pour un membre du Support chez un client, l'acteur
     * qu'il est dans le maître.
     */
    private function isAuthor(Entry $entry, Context $context): bool
    {
        $author = $entry->author;

        if ($author === null) {
            return false;
        }

        if ($context->isAgent()) {
            return $author->instance_uid === $context->agent->master_uid
                && (string) $author->remote_id === (string) $context->agent->master_user_id;
        }

        return $context->actor !== null && $author->is($context->actor);
    }

    /** Un client envoie au maître ce qu'il écrit sur un objet partagé dont le maître fait autorité. */
    private function sendsToMaster(Trackable $subject, Context $context): bool
    {
        return Tma::isClient() && $subject->isShared() && ! $subject->authorityIsHere() && ! $context->isApi();
    }

    /** Un message a du texte ; une note, du texte ou au moins une image. */
    private function assertNotBlank(EntryType $type, ?string $body, int $files): void
    {
        if (! Html::isBlank($body) || ($type === EntryType::Note && $files > 0)) {
            return;
        }

        throw ValidationException::withMessages(['body' => $type === EntryType::Note ? __('La note est vide.') : __('Le message est vide.')]);
    }

    /** On répond à un message de premier niveau ; une note commente un message de premier niveau. */
    private function assertParent(Trackable $ticket, EntryType $type, Entry $parent): void
    {
        $sameTicket = $parent->subject_type === $ticket->getMorphClass() && (int) $parent->subject_id === (int) $ticket->getKey();

        if ($sameTicket && $parent->parent_id === null && ! $parent->isNote()) {
            return;
        }

        throw ValidationException::withMessages(['parent' => $type === EntryType::Note
            ? __('Une note commente un message de premier niveau du même ticket, ou le ticket entier.')
            : __('On ne répond qu’à un message de premier niveau du même ticket.')]);
    }
}
