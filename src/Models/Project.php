<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un projet : un titre, une application destinataire (`target_instance_uid`, celle de toutes ses tâches) et N tâches
 * projet. Créé dans le maître ; l'application cliente le reçoit avec ses tâches publiées. Son temps est la somme
 * des temps de ses tâches (`timeTotals()`), base de la future facturation par projet.
 *
 * @property string $ulid
 * @property string $target_instance_uid
 * @property string $title
 * @property string|null $description
 */
class Project extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'tma_projects';

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        // Le titre change aussi chez le client : ses tâches publiées repartent.
        static::updated(function (Project $project): void {
            if (! Tma::isMaster() || ! $project->wasChanged(['title', 'description'])) {
                return;
            }

            $project->tasks()->get()
                ->filter(fn (ProjectTask $task): bool => $task->isShared())
                ->each(fn (ProjectTask $task) => event(new TaskUpdated($task)));
        });
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function targetInstance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'target_instance_uid', 'uid');
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Le temps du projet : la somme de celui de ses tâches, au total et par mois, en minutes. Côté client, celui des
     * tâches publiées qu'il a reçues.
     *
     * @return array{total_minutes: int, by_month: array<string, int>}
     */
    public function timeTotals(): array
    {
        $byMonth = [];

        foreach ($this->tasks()->get() as $task) {
            foreach (TimeTotals::for($task)['by_month'] as $month => $minutes) {
                $byMonth[$month] = ($byMonth[$month] ?? 0) + (int) $minutes;
            }
        }

        ksort($byMonth);

        return ['total_minutes' => array_sum($byMonth), 'by_month' => $byMonth];
    }

    /** « Refonte du site » ; dans le maître, avec son application : « Refonte du site — Desapp ». */
    public function label(): string
    {
        return Tma::isMaster() && $this->targetInstance !== null ? $this->title.' — '.$this->targetInstance->name : $this->title;
    }
}
