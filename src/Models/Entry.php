<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

/**
 * Une entrée de la chronologie d'un ticket (et, au lot 2, d'une tâche projet) : commentaire, note, résumé
 * d'intervention. Une réponse a un parent, sur un seul niveau, et le type de son parent.
 *
 * Une note n'est visible que dans l'application où elle a été écrite (`origin_instance_uid`), jusqu'à ce qu'on la
 * partage (`shared_at`). Ajouter une entrée ne change jamais l'état du ticket (CDC §9.4).
 *
 * @property EntryType $type
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
            'shared_at' => 'datetime',
            'edited_at' => 'datetime',
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
        });

        static::saving(function (Entry $entry): void {
            if ($entry->isDirty('body')) {
                $entry->body = Html::clean($entry->body);
            }
        });
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /** Partagée avec l'autre partie : un commentaire ou un résumé d'office, une note une fois partagée. */
    public function isShared(): bool
    {
        return $this->type->isSharedByDefault() || $this->shared_at !== null;
    }

    public function isWrittenHere(): bool
    {
        return Tma::isHere($this->origin_instance_uid);
    }

    /**
     * Ce qu'on voit ici : les entrées partagées, et les notes écrites ici quand on a le droit de lire les notes.
     * Une note venue de l'autre application n'est là que si elle a été partagée.
     */
    public function scopeVisibleHere(Builder $query, bool $withNotes): Builder
    {
        return $query->where(function (Builder $query) use ($withNotes): void {
            $query->where('type', '!=', EntryType::Note->value)
                ->orWhereNotNull('shared_at');

            if ($withNotes) {
                $query->orWhere(fn (Builder $q) => $q->where('type', EntryType::Note->value)->where('origin_instance_uid', Tma::instanceUid()));
            }
        });
    }
}
