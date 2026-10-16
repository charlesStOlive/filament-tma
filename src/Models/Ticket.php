<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\Models\Concerns\IsTrackable;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * Un ticket va toujours d'une application cliente au Support (addendum §2) : en brouillon, il reste dans l'application
 * où il est écrit ; ensuite, le maître fait autorité. Deux dimensions séparées (CDC §7.1) : l'état métier (`state`) et
 * l'état de synchronisation (`sync_status`).
 *
 * @property string $ulid
 * @property string $number
 * @property string $source_instance_uid
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property TicketState $state
 * @property SyncStatus $sync_status
 */
class Ticket extends Model implements HasMedia, HasRichContent, Trackable
{
    use HasAttachments;
    use HasStates;
    use HasUlids;
    use InteractsWithRichContent;
    use IsTrackable;
    use SoftDeletes;

    protected $table = 'tma_tickets';

    protected $guarded = [];

    protected $attributes = [
        'priority' => 'normal',
        'sync_status' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'state' => TicketState::class,
            'priority' => Priority::class,
            'sync_status' => SyncStatus::class,
            'publish_at' => 'datetime',
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
            $ticket->number ??= static::nextNumber($ticket->created_on_instance_uid);
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

    /**
     * DES-0042 : le préfixe de l'installation où le ticket est créé, puis son compteur, sur les tickets créés là.
     * Un ticket que le Support prépare pour un client porte donc le numéro du Support (FIL-0003) : le Support ne
     * connaît pas les tickets que le client garde chez lui, et ne doit pas prendre un numéro qu'il a déjà.
     */
    public static function nextNumber(string $createdOnInstanceUid): string
    {
        return DB::transaction(function () use ($createdOnInstanceUid): string {
            $last = static::withTrashed()
                ->where('created_on_instance_uid', $createdOnInstanceUid)
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

    /** La tâche projet dont ce ticket est parti, s'il en vient une. */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    /** L'échange s'arrête avec le ticket ; les notes restent possibles. */
    public function acceptsMessages(): bool
    {
        return ! $this->state->isFinal();
    }

    /** L'application source, quand le maître la connaît (« Applications clientes »). */
    public function sourceInstance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'source_instance_uid', 'uid');
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('state', ['closed', 'cancelled']);
    }

    /**
     * Ce qui attend une personne : ses brouillons ; ses demandes quand c'est son tour (une réponse, une confirmation) ;
     * côté Support, celles dont elle est le contact et les demandes nouvelles sans contact. Dans une application
     * cliente, un membre du Support (`Agent`) est lui-même côté maître et prend les demandes du Support.
     */
    public function scopeAwaiting(Builder $query, Authenticatable $user): Builder
    {
        $me = Actor::idsFor($user);
        $support = Tma::isMaster() ? TmaGate::allows($user, TmaGate::HANDLE) : Agent::for($user) !== null;

        return $query->where(fn (Builder $q): Builder => $q
            ->where(fn (Builder $q): Builder => $q->where('state', 'draft')->whereIn('created_by_actor_id', $me))
            ->orWhere(fn (Builder $q): Builder => $q->whereIn('state', ['awaiting_requester', 'pending_validation'])->whereIn('requester_actor_id', $me))
            ->orWhere(fn (Builder $q): Builder => $q->whereIn('state', ['submitted', 'in_progress'])->whereIn('assignee_actor_id', $me))
            ->when($support, fn (Builder $q): Builder => $q->orWhere(fn (Builder $q): Builder => $q->where('state', 'submitted')->whereNull('assignee_actor_id'))));
    }

    /** La partie qui doit agir. Un brouillon est à la partie qui l'a créé. */
    public function sideInCharge(): ?Side
    {
        if ($this->state instanceof Draft) {
            return Tma::isHere($this->source_instance_uid) && Tma::isHere($this->created_on_instance_uid) ? Side::Requester : Side::Handler;
        }

        return $this->state->inHandsOf();
    }

    /** Qui doit agir, en clair : « Demandeur » ou le Support ; null pour un ticket terminé. */
    public function waitingOn(): ?string
    {
        return match ($this->sideInCharge()) {
            Side::Requester => __('Demandeur'),
            Side::Handler => Terms::support(),
            null => null,
        };
    }

    /**
     * Qui doit agir, pour une personne donnée : « Vous » si c'est elle le demandeur attendu, le demandeur par son nom
     * sinon, ou le Support.
     */
    public function waitingOnFor(?Authenticatable $user): ?string
    {
        if ($this->sideInCharge() !== Side::Requester) {
            return $this->waitingOn();
        }

        return $this->requester?->isUser($user) ? __('Vous') : ($this->requester?->name ?? $this->waitingOn());
    }

    /**
     * Les parties que représente une application donnée : l'application source est le demandeur, le maître est le
     * Support (`Workflow\Context::$instanceUid`).
     *
     * @return array<int, Side>
     */
    public function sidesOf(string $instanceUid, bool $isMaster): array
    {
        return array_values(array_filter([
            $instanceUid === $this->source_instance_uid ? Side::Requester : null,
            $isMaster ? Side::Handler : null,
        ]));
    }

    /** L'application qui fait autorité est celle-ci : celle où le brouillon s'écrit, puis le maître. */
    public function authorityIsHere(): bool
    {
        return $this->state instanceof Draft ? Tma::isHere($this->created_on_instance_uid) : Tma::isMaster();
    }

    protected function hasAutoCloseWarning(): bool
    {
        return true;
    }

    public function kind(): string
    {
        return 'ticket';
    }

    public function clientInstanceUid(): string
    {
        return $this->source_instance_uid;
    }

    /** Transmis, ou publié : un brouillon reste dans l'application où il s'écrit. */
    public function isShared(): bool
    {
        return ! $this->state instanceof Draft;
    }
}
