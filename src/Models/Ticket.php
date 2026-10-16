<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\Models\Concerns\IsTrackable;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Addressables;
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
     * Ce qui attend une personne : ses brouillons, ses demandes qui attendent sa réponse ou sa confirmation, ce qui
     * lui est confié (Nouveau, En cours), et les nouvelles demandes de son équipe que personne n'a prises, si elle
     * peut les prendre. Dans une application cliente, un membre du Support (`Agent`) est aussi lui-même côté maître
     * et prend les demandes du Support.
     */
    public function scopeAwaiting(Builder $query, Authenticatable $user): Builder
    {
        $agent = Agent::for($user);
        $me = Actor::idsFor($user);

        $mayTake = TmaGate::allows($user, TmaGate::workflowAction('take-charge'));
        $takes = array_keys(array_filter(Tma::isMaster()
            ? [HandlingMode::ServiceDesk->value => $mayTake]
            : [HandlingMode::Local->value => $mayTake, HandlingMode::ServiceDesk->value => $agent !== null]));

        // Le côté demandeur : sa propre demande ; un interne, seulement celles traitées ici ; un correspondant, aussi la
        // demande transmise d'un interne dont il est destinataire principal (il répond pour lui).
        $internal = Addressables::isInternal($user);

        return $query->where(fn (Builder $q): Builder => $q
            ->where(fn (Builder $q): Builder => $q->where('state', 'draft')->whereIn('created_by_actor_id', $me))
            ->orWhere(fn (Builder $q): Builder => $q->whereIn('state', ['awaiting_requester', 'pending_validation'])->whereIn('requester_actor_id', $me)
                ->when($internal, fn (Builder $q): Builder => $q->where('handling_mode', '!=', HandlingMode::ServiceDesk->value)))
            ->when(Tma::isClient() && ! $internal && $agent === null, fn (Builder $q): Builder => $q->orWhere(fn (Builder $q): Builder => $q
                ->whereIn('state', ['awaiting_requester', 'pending_validation'])
                ->where('handling_mode', HandlingMode::ServiceDesk->value)
                ->whereIn('requester_actor_id', Addressables::internalActorIds())
                ->whereHas('recipients', fn (Builder $r): Builder => $r->where('role', RecipientRole::Primary->value)->whereIn('actor_id', $me))))
            ->orWhere(fn (Builder $q): Builder => $q->whereIn('state', ['submitted', 'in_progress'])->whereIn('assignee_actor_id', $me))
            ->orWhere(fn (Builder $q): Builder => $q->where('state', 'submitted')->whereNull('assignee_actor_id')->whereIn('handling_mode', $takes)));
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
     * Qui doit agir, en clair : « Demandeur », le Support, ou l'équipe locale du client (« Équipe EDM ») ; null pour
     * un ticket terminé.
     */
    public function waitingOn(): ?string
    {
        return match ($this->sideInCharge()) {
            Side::Requester => __('Demandeur'),
            Side::Handler => $this->handledBy(),
            null => null,
        };
    }

    /**
     * Qui doit agir, pour une personne donnée : « Vous » si c'est elle le demandeur attendu, le demandeur par son nom
     * sinon, ou l'équipe qui traite.
     */
    public function waitingOnFor(?Authenticatable $user): ?string
    {
        if ($this->sideInCharge() !== Side::Requester) {
            return $this->waitingOn();
        }

        return $this->requester?->isUser($user) ? __('Vous') : ($this->requester?->name ?? $this->waitingOn());
    }

    /**
     * Qui traite la demande, en clair : l'équipe locale du client (« Équipe EDM »), ou le Support. Null pour un
     * brouillon du client, qui n'est encore confié à personne.
     */
    public function handledBy(): ?string
    {
        if ($this->state instanceof Draft && $this->handling_mode === HandlingMode::Local) {
            return null;
        }

        return $this->handling_mode === HandlingMode::Local ? Terms::team($this->source_instance_uid) : Terms::support();
    }

    /**
     * Les parties que représente une application donnée. L'application source est le demandeur ; en traitement
     * local elle traite aussi la demande ; au Support, c'est le maître (`Workflow\Context::$instanceUid`).
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

    /** Les champs (titre, description, priorité…) ne se modifient qu'en brouillon, ou par l'équipe qui traite la demande, quand elle a la main. */
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

    /** Traité par le Support, et publié : un brouillon préparé par le maître reste chez lui. */
    public function isShared(): bool
    {
        return $this->handling_mode === HandlingMode::ServiceDesk && ! $this->state instanceof Draft;
    }
}
