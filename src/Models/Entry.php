<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

/**
 * Une entrée de la chronologie d'un ticket ou d'une tâche projet : un message, ou une note. Tout le monde sur le
 * ticket la lit, des deux côtés. Ajouter une entrée ne change jamais l'état du ticket (CDC §9.4).
 *
 * - Un message peut avoir des réponses (des messages dont il est le parent, sur un seul niveau). Son auteur le
 *   modifie tant que personne d'autre n'a écrit de message après lui (`isStillEditable()`).
 * - Une note commente un message de premier niveau (son parent) ou le ticket entier (sans parent). Elle porte un
 *   numéro par ticket (`number`, attribué à l'écriture), s'archive quand on n'a plus besoin de la lire
 *   (`archived_at`) ; son auteur seul la modifie ou la supprime. Lue de tous, sauf si son auteur la garde pour lui
 *   (`private`) : elle ne se montre alors qu'à lui, des deux côtés (`visibleTo()`).
 * - Sur une tâche projet, un message du Support peut être une question au client (`meta.question`, voir
 *   `Workflow\Questions`) : ses réponses sont les réponses au message.
 *
 * @property EntryType $type
 * @property int|null $number
 * @property bool $private
 * @property string $body
 * @property Origin $origin
 */
class Entry extends Model implements HasMedia, HasRichContent
{
    use HasAttachments;
    use HasUlids;
    use InteractsWithRichContent;
    use SoftDeletes;

    protected $table = 'tma_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => EntryType::class,
            'origin' => Origin::class,
            'number' => 'integer',
            'private' => 'boolean',
            'edited_at' => 'datetime',
            'archived_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        static::creating(function (Entry $entry): void {
            $entry->origin_instance_uid ??= Tma::instanceUid();

            if ($entry->type === EntryType::Note && $entry->number === null) {
                $entry->number = static::nextNoteNumber($entry->subject_type, $entry->subject_id);
            }
        });

        static::saving(function (Entry $entry): void {
            if ($entry->isDirty('body')) {
                $entry->body = Html::clean($entry->body);
            }
        });
    }

    /** Le numéro de la prochaine note d'un ticket : une note supprimée garde le sien. */
    public static function nextNoteNumber(string $subjectType, int|string $subjectId): int
    {
        return (int) static::withTrashed()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('type', EntryType::Note->value)
            ->max('number') + 1;
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('body')
            ->fileAttachmentProvider(SpatieMediaLibraryFileAttachmentProvider::make()->collection(self::CONTENT_COLLECTION));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'author_actor_id');
    }

    /** Le message auquel répond un message, ou que commente une note. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Les réponses à un message. */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('type', EntryType::Comment->value)->oldest();
    }

    /** Le temps saisi sur ce message ou cette note (maître). */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'entry_id');
    }

    /** Les notes posées sur un message. */
    public function notes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('type', EntryType::Note->value)->orderBy('number');
    }

    public function isNote(): bool
    {
        return $this->type === EntryType::Note;
    }

    /** Une question du Support sur une tâche projet (`Workflow\Questions`). */
    public function isQuestion(): bool
    {
        return $this->type === EntryType::Comment && $this->parent_id === null && ! empty($this->meta['question']);
    }

    /** Une note privée ne se montre qu'à son auteur ; le reste, à tous. */
    public function isVisibleTo(?Authenticatable $user): bool
    {
        return ! $this->private || in_array((int) $this->author_actor_id, Actor::idsFor($user), true);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isWrittenHere(): bool
    {
        return Tma::isHere($this->origin_instance_uid);
    }

    /** « Note n° 3 » ; avec son auteur : « Note n° 3 · Ada Lovelace ». */
    public function noteLabel(bool $withAuthor = false): string
    {
        $label = __('filament-tma::notes.number_title', ['number' => $this->number]);

        return $withAuthor && $this->author !== null ? $label.' · '.$this->author->name : $label;
    }

    /**
     * Un message reste modifiable tant que son ticket n'est pas terminé et qu'il est le dernier message (addendum
     * §3.2) : dès qu'un autre message le suit, plus personne ne le touche, que ce soit son tour ou non. Les notes ne
     * comptent pas : elles commentent, elles ne répondent pas. Une note, elle, reste toujours modifiable.
     */
    public function isStillEditable(): bool
    {
        if ($this->isNote()) {
            return true;
        }

        $subject = $this->subject;

        if (! $subject instanceof Trackable || ! $subject->acceptsMessages()) {
            return false;
        }

        return ! $subject->entries()
            ->messages()
            ->whereKeyNot($this->getKey())
            ->where(fn (Builder $q): Builder => $q->where('created_at', '>', $this->created_at)
                ->orWhere(fn (Builder $q): Builder => $q->where('created_at', $this->created_at)->where('id', '>', $this->getKey())))
            ->exists();
    }

    public function scopeMessages(Builder $query): Builder
    {
        return $query->where('type', EntryType::Comment->value);
    }

    public function scopeNotes(Builder $query): Builder
    {
        return $query->where('type', EntryType::Note->value);
    }

    /** Ce qu'une personne peut lire : tout, sauf les notes privées des autres. */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q->where('private', false)->orWhereIn('author_actor_id', Actor::idsFor($user)));
    }
}
