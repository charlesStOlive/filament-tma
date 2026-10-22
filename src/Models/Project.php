<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Un projet : un titre, une application destinataire (`target_instance_uid`, celle de toutes ses tâches) et N tâches
 * projet. Créé dans le maître ; l'application cliente le reçoit avec ses tâches publiées. Son temps est la somme
 * des temps de ses tâches (`timeTotals()`), base de la future facturation par projet.
 *
 * Le maître lui donne une fourchette, en jours (`estimate_min_days`, `estimate_max_days` : « 1–3 j ») et des
 * participants (`members()`). Y participent aussi, sans qu'on les nomme, les destinataires de ses tâches
 * (`participants()`) : c'est « Mes projets » sur le tableau de bord.
 *
 * @property string $ulid
 * @property string $target_instance_uid
 * @property string $title
 * @property string|null $description
 * @property float|null $estimate_min_days
 * @property float|null $estimate_max_days
 */
class Project extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'tma_projects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estimate_min_days' => 'float',
            'estimate_max_days' => 'float',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        // Le titre, la fourchette changent aussi chez le client : ses tâches publiées repartent.
        static::updated(function (Project $project): void {
            if ($project->wasChanged(['title', 'description', 'estimate_min_days', 'estimate_max_days'])) {
                $project->pushToClient();
            }
        });
    }

    /** Dans le maître, le projet repart chez le client avec ses tâches publiées (il n'y voyage qu'avec elles). */
    public function pushToClient(): void
    {
        if (! Tma::isMaster()) {
            return;
        }

        $this->tasks()->get()
            ->filter(fn (ProjectTask $task): bool => $task->isShared())
            ->each(fn (ProjectTask $task) => event(new TaskUpdated($task)));
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    /** Les participants nommés par le maître (des acteurs : membres du Support, correspondants du client). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'tma_project_members', 'project_id', 'actor_id')->withTimestamps();
    }

    /** Les participants changent (dans le maître : ils partent chez le client). */
    public function syncMembers(array $actorIds): void
    {
        $changes = $this->members()->sync(array_values(array_unique(array_map('intval', $actorIds))));

        if (array_filter($changes) !== []) {
            $this->pushToClient();
        }
    }

    /**
     * Qui participe : les participants nommés, puis les destinataires de ses tâches, une fois chacun.
     *
     * @return Collection<int, Actor>
     */
    public function participants(): Collection
    {
        $this->loadMissing(['members', 'tasks.recipients.actor']);

        return $this->members
            ->concat($this->tasks->flatMap(fn (ProjectTask $task): Collection => $task->recipients->pluck('actor')))
            ->filter()
            ->unique(fn (Actor $actor): int => (int) $actor->getKey())
            ->values();
    }

    /** Les projets où participent ces acteurs (`Actor::idsFor()`) : nommés, ou destinataires d'une de ses tâches. */
    public function scopeInvolving(Builder $query, array $actorIds): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereHas('members', fn (Builder $members): Builder => $members->whereIn('tma_actors.id', $actorIds))
            ->orWhereHas('tasks.recipients', fn (Builder $recipients): Builder => $recipients->whereIn('actor_id', $actorIds)));
    }

    /** Les projets pas terminés : sans tâche encore, ou avec au moins une tâche ni terminée ni annulée. */
    public function scopeOngoing(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereDoesntHave('tasks')
            ->orWhereHas('tasks', fn (Builder $tasks): Builder => $tasks->open()));
    }

    /** Les tâches en cours du projet. */
    public function ongoingTasks(): HasMany
    {
        return $this->tasks()->where('state', TaskInProgress::getMorphClass());
    }

    /** « 1–3 j » ; null sans fourchette. */
    public function estimateLabel(): ?string
    {
        return Duration::estimate($this->estimate_min_days, $this->estimate_max_days);
    }

    /** Le temps passé dépasse la fourchette (sa borne haute). */
    public function isOverEstimate(int $minutes): bool
    {
        return $this->estimate_max_days !== null && $minutes > $this->estimate_max_days * Duration::dayMinutes();
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
