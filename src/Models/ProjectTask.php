<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Concerns\HasAttachments;
use CharlesStOlive\FilamentTma\Models\Concerns\IsTrackable;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\States\Task\TaskState;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\ModelStates\HasStates;

/**
 * Une tâche projet (CDC §6.4, §8) : créée dans le maître, destinée à une seule application cliente
 * (`target_instance_uid`) d'un seul client. Le maître en est l'intervenant et fait autorité ; l'application
 * cliente la voit une fois publiée et n'y fait qu'ajouter (commentaires, notes, images, réponses).
 *
 * @property string $ulid
 * @property string $number
 * @property string $target_instance_uid
 * @property TaskState $state
 * @property Carbon|null $starts_on le début ; sans saisie, le jour de la création
 * @property Carbon|null $due_on l'échéance prévue (pas une fin fixe : la tâche finit quand elle est terminée, `done_at`)
 * @property Carbon|null $done_at
 */
class ProjectTask extends Model implements HasMedia, HasRichContent, Trackable
{
    use HasAttachments;
    use HasStates;
    use HasUlids;
    use InteractsWithRichContent;
    use IsTrackable;
    use SoftDeletes;

    protected $table = 'tma_tasks';

    protected $guarded = [];

    protected $attributes = [
        'sync_status' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'state' => TaskState::class,
            'sync_status' => SyncStatus::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'done_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'synced_at' => 'datetime',
            'time_by_month' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        static::creating(function (ProjectTask $task): void {
            $task->last_activity_at ??= now();
            // Sans début saisi, la tâche commence le jour de sa création.
            $task->starts_on ??= now()->toDateString();
            $task->number ??= static::nextNumber();
        });

        static::saving(function (ProjectTask $task): void {
            if ($task->isDirty('description') && filled($task->description)) {
                $task->description = Html::clean($task->description);
            }
        });
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('description')
            ->fileAttachmentProvider(SpatieMediaLibraryFileAttachmentProvider::make()->collection(self::CONTENT_COLLECTION));
    }

    /** T-0042 : le préfixe des tâches (`filament-tma.instance.task_prefix`), puis un compteur du maître. */
    public static function nextNumber(): string
    {
        $prefix = (string) config('filament-tma.instance.task_prefix', 'T');

        return DB::transaction(function () use ($prefix): string {
            $last = static::withTrashed()->where('number', 'like', $prefix.'-%')->lockForUpdate()->orderByDesc('id')->value('number');
            $next = $last !== null && preg_match('/(\d+)$/', $last, $m) ? ((int) $m[1]) + 1 : 1;

            return $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'assignee_actor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function targetInstance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'target_instance_uid', 'uid');
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('state', ['done', 'cancelled']);
    }

    public function kind(): string
    {
        return 'task';
    }

    public function clientInstanceUid(): string
    {
        return $this->target_instance_uid;
    }

    /** Le maître est l'intervenant ; l'application destinataire, une fois la tâche publiée, l'autre partie. */
    public function sidesOf(string $instanceUid, bool $isMaster): array
    {
        $sides = [];

        if ($instanceUid === $this->target_instance_uid && ! $this->state instanceof TaskDraft) {
            $sides[] = Side::Requester;
        }

        if ($isMaster) {
            $sides[] = Side::Handler;
        }

        return $sides;
    }

    public function authorityIsHere(): bool
    {
        return Tma::isMaster();
    }

    /** Publiée : la copie existe chez le client. */
    public function isShared(): bool
    {
        return ! $this->state instanceof TaskDraft;
    }

    public function fieldsEditableHere(): bool
    {
        return Tma::isMaster() && ! $this->state->isFinal();
    }
}
