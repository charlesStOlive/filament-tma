<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\ModelStates\HasStates;

/**
 * Un ticket de TMA. Il appartient à une seule application source (`source_instance_uid`) et, côté maître, à un
 * seul client (`client`). Son identité globale est `ulid`, la même dans toutes les applications qui le partagent ;
 * `number` est lisible (DES-0042) et unique dans son application source.
 *
 * Trois dimensions séparées (CDC §7.1) : l'état métier (`state`), le mode de traitement (`handling_mode`) et
 * l'état de synchronisation (`sync_status`).
 *
 * @property string $ulid
 * @property string $number
 * @property string $source_instance_uid
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property TicketState $state
 * @property HandlingMode $handling_mode
 * @property SyncStatus $sync_status
 */
class Ticket extends Model implements HasMedia, HasRichContent
{
    use HasAttachments;
    use HasStates;
    use HasUlids;
    use InteractsWithRichContent;
    use SoftDeletes;

    protected $table = 'tma_tickets';

    protected $guarded = [];

    protected $attributes = [
        'priority' => 'normal',
        'handling_mode' => 'local',
        'sync_status' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'state' => TicketState::class,
            'priority' => Priority::class,
            'handling_mode' => HandlingMode::class,
            'sync_status' => SyncStatus::class,
            'due_on' => 'date',
            'submitted_at' => 'datetime',
            'taken_at' => 'datetime',
            'waiting_since' => 'datetime',
            'paused_until' => 'datetime',
            'validation_requested_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'auto_close_warned_at' => 'datetime',
            'synced_at' => 'datetime',
            'time_by_month' => 'array',
        ];
    }

    /** L'ulid est l'identité globale ; la clé de route reste l'id local. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->source_instance_uid ??= Tma::instanceUid();
            $ticket->created_on_instance_uid ??= Tma::instanceUid();
            $ticket->last_activity_at ??= now();
            $ticket->number ??= static::nextNumber($ticket->source_instance_uid);
        });

        static::saving(function (Ticket $ticket): void {
            if ($ticket->isDirty('description') && filled($ticket->description)) {
                $ticket->description = Html::clean($ticket->description);
            }
        });
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('description')
            ->fileAttachmentProvider(SpatieMediaLibraryFileAttachmentProvider::make()->collection(self::CONTENT_COLLECTION));
    }

    /** DES-0042 : le préfixe de l'installation, puis un compteur propre à l'application source. */
    public static function nextNumber(string $sourceInstanceUid): string
    {
        return DB::transaction(function () use ($sourceInstanceUid): string {
            $last = static::withTrashed()
                ->where('source_instance_uid', $sourceInstanceUid)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');

            $next = $last !== null && preg_match('/(\d+)$/', $last, $m) ? ((int) $m[1]) + 1 : 1;

            return Tma::ticketPrefix().'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'requester_actor_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'assignee_actor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): MorphMany
    {
        return $this->morphMany(Entry::class, 'subject');
    }

    public function transitions(): MorphMany
    {
        return $this->morphMany(StateTransition::class, 'subject');
    }

    /** Les saisies de temps (dans le maître ; un client n'a que les totaux, `time_total_minutes`). */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'subject');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('state', ['closed', 'cancelled']);
    }

    /** La partie qui doit agir. Un brouillon est à la partie qui l'a créé. */
    public function sideInCharge(): ?Side
    {
        if ($this->state instanceof Draft) {
            return Tma::isHere($this->source_instance_uid) && Tma::isHere($this->created_on_instance_uid) ? Side::Requester : Side::Handler;
        }

        return $this->state->inHandsOf();
    }

    /**
     * Les parties que cette application représente pour ce ticket. L'application source est le demandeur ; en
     * traitement local elle est aussi l'intervenant, en centre de service c'est le maître.
     *
     * @return array<int, Side>
     */
    public function sidesHere(): array
    {
        return $this->sidesOf(Tma::instanceUid(), Tma::isMaster());
    }

    /**
     * Les parties que représente une application donnée (`Workflow\Context::$instanceUid`).
     *
     * @return array<int, Side>
     */
    public function sidesOf(string $instanceUid, bool $isMaster): array
    {
        $sides = [];
        $isSource = $instanceUid === $this->source_instance_uid;

        if ($isSource) {
            $sides[] = Side::Requester;
        }

        if ($this->handling_mode === HandlingMode::Local ? $isSource : $isMaster) {
            $sides[] = Side::Handler;
        }

        return $sides;
    }

    /** L'application qui fait autorité sur ce ticket est celle-ci : l'application source en local, le maître sinon. */
    public function authorityIsHere(): bool
    {
        return $this->handling_mode === HandlingMode::Local ? Tma::isHere($this->source_instance_uid) : Tma::isMaster();
    }

    public function isHandledHere(): bool
    {
        return in_array(Side::Handler, $this->sidesHere(), true);
    }

    /** Cette application a la main : elle peut modifier les champs du ticket. Sinon, on ne fait plus qu'ajouter. */
    public function hasHandHere(): bool
    {
        $side = $this->sideInCharge();

        if ($side === null) {
            return false;
        }

        if ($this->state instanceof Draft) {
            return Tma::isHere($this->created_on_instance_uid);
        }

        return in_array($side, $this->sidesHere(), true);
    }

    /** Les champs (titre, description, priorité…) ne se modifient qu'en brouillon, ou par l'intervenant qui a la main. */
    public function fieldsEditableHere(): bool
    {
        if ($this->state->isFinal()) {
            return false;
        }

        if ($this->state instanceof Draft) {
            return Tma::isHere($this->created_on_instance_uid);
        }

        return $this->isHandledHere() && $this->sideInCharge() === Side::Handler;
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now(), 'auto_close_warned_at' => null])->saveQuietly();
    }

    /** Les entrées de premier niveau visibles ici, avec leurs réponses. */
    public function timelineEntries(bool $withNotes): \Illuminate\Support\Collection
    {
        return $this->entries()
            ->whereNull('parent_id')
            ->visibleHere($withNotes)
            ->with(['author', 'media', 'replies' => fn ($q) => $q->visibleHere($withNotes)->with(['author', 'media'])])
            ->oldest()
            ->get();
    }

    public function latestEntryOfType(EntryType $type): ?Entry
    {
        return $this->entries()->where('type', $type->value)->latest('id')->first();
    }
}
