<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Events\TaskCreated;
use CharlesStOlive\FilamentTma\Events\TaskDeleted;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Le workflow des tâches projet (CDC §8) : maître seulement, avec les droits `tma.task.workflow.{clé}`. Comme pour
 * les tickets, tout passe par `run()`, quelle que soit l'origine.
 */
class TaskWorkflow
{
    /** @var array<string, TaskMove> */
    private array $moves = [];

    public function __construct()
    {
        foreach ([new TaskMoves\Publish, new TaskMoves\Complete, new TaskMoves\CancelTask, new TaskMoves\AutoPublish] as $move) {
            $this->register($move);
        }
    }

    public function register(TaskMove $move): static
    {
        $this->moves[$move->key()] = $move;

        return $this;
    }

    /** @return array<string, TaskMove> */
    public function moves(): array
    {
        return $this->moves;
    }

    /** @return array<string, TaskMove> */
    public function userMoves(): array
    {
        return array_filter($this->moves, fn (TaskMove $move): bool => ! $move->isSystem());
    }

    public function move(string $key): TaskMove
    {
        return $this->moves[$key] ?? throw new InvalidArgumentException("Mouvement de tâche inconnu : {$key}");
    }

    public function allows(ProjectTask $task, string|TaskMove $move, Context $context): bool
    {
        $move = is_string($move) ? $this->move($move) : $move;

        if (! Tma::isMaster() || ($context->isApi() && ! $context->fromMaster)
            || ! in_array($task->state::class, $move->from(), true)
            || ! $task->state->canTransitionTo($move->to())
            || ! $move->isAvailable($task)) {
            return false;
        }

        if ($context->isSystem()) {
            return $move->isSystem();
        }

        return ! $move->isSystem() && TmaGate::allows($context->user, TmaGate::workflowAction($move->key()), $task);
    }

    /** @param  array<string, mixed>  $data */
    public function run(ProjectTask $task, string|TaskMove $move, array $data, Context $context): StateTransition
    {
        $move = is_string($move) ? $this->move($move) : $move;

        if (! $this->allows($task, $move, $context)) {
            throw new AuthorizationException(__('Ce passage n’est pas possible pour cette tâche.'));
        }

        $data = Validator::make($data, $move->rules($task))->validate();

        $transition = DB::transaction(function () use ($task, $move, $data, $context): StateTransition {
            $from = $task->state;
            $at = $move->happenedAt($task);
            $ulid = (string) Str::ulid();

            app(Timeline::class)->forTransition($ulid, fn () => $move->apply($task, $data, $context));
            $task->state->transitionTo($move->to());

            $transition = $task->transitions()->create([
                'ulid' => $ulid,
                'move' => $move->key(),
                'from_state' => $from::getMorphClass(),
                'to_state' => $move->to()::getMorphClass(),
                'actor_id' => $context->actor?->getKey(),
                'origin_instance_uid' => $context->instanceUid,
                'origin' => $context->origin,
                'reason' => $move->reason($data),
                'created_at' => $at,
            ]);

            $task->forceFill(['last_activity_at' => $at])->saveQuietly();

            return $transition;
        });

        event(new TaskTransitioned($task->refresh(), $transition, $context));

        return $transition;
    }

    /**
     * Crée une tâche en brouillon, pour une application cliente (maître seulement).
     *
     * @param  array<string, mixed>  $attributes  title, description, project_id (l'application est la sienne), ou
     *                                            target_instance_uid sans projet, starts_on, due_on, publish_at
     */
    public function create(array $attributes, Context $context): ProjectTask
    {
        if (! Tma::isMaster()) {
            throw new AuthorizationException(__('Les tâches projet se créent chez le Support.'));
        }

        if (! $context->isSystem() && Gate::getPolicyFor(ProjectTask::class) !== null && ! Gate::forUser($context->user)->allows('create', ProjectTask::class)) {
            throw new AuthorizationException(__('Vous ne pouvez pas créer de tâche.'));
        }

        $project = filled($attributes['project_id'] ?? null) ? Project::query()->findOrFail($attributes['project_id']) : null;
        $instance = Instance::query()->where('uid', $project?->target_instance_uid ?? $attributes['target_instance_uid'] ?? null)->firstOrFail();

        $task = ProjectTask::create([
            ...collect($attributes)->only(['title', 'description', 'starts_on', 'due_on', 'publish_at'])->all(),
            'project_id' => $project?->getKey(),
            'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
            'created_by_actor_id' => $context->actor?->getKey(),
            'assignee_actor_id' => $context->actor?->getKey(),
        ]);

        event(new TaskCreated($task, $context));

        return $task;
    }

    /** Supprimer une tâche : dans le maître seulement, avec le droit `tma.task.delete`, quel que soit son état. */
    public function canDelete(ProjectTask $task, Context $context): bool
    {
        return Tma::isMaster() && ! $task->trashed()
            && ($context->isSystem() || Gate::getPolicyFor(ProjectTask::class) === null || Gate::forUser($context->user)->allows('delete', $task));
    }

    /**
     * Supprime une tâche, quel que soit son état, avec ses messages, ses notes et son temps saisi. Publiée, elle
     * disparaît aussi chez son client : l'envoi part par la file, comme tout changement (`TaskDeleted`, `QueuePush`).
     * Les tickets ouverts depuis elle restent, sans lien. Refusée si du temps de la tâche est rattaché à une facture.
     *
     * La suppression est douce (`deleted_at`) : le numéro n'est jamais redonné et l'audit en garde la trace, mais rien
     * ne la défait dans l'interface.
     */
    public function delete(ProjectTask $task, Context $context): void
    {
        if (! $this->canDelete($task, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas supprimer la tâche :number.', ['number' => $task->number]));
        }

        if ($task->timeEntries()->whereNotNull('invoice_id')->exists()) {
            throw ValidationException::withMessages(['task' => __('Du temps de la tâche :number est facturé : elle ne se supprime pas.', ['number' => $task->number])]);
        }

        DB::transaction(function () use ($task): void {
            $task->timeEntries()->get()->each->delete();
            $task->entries()->update(['deleted_at' => now()]);
            $task->delete();
        });

        event(new TaskDeleted($task, $context));
    }

    /**
     * Une tâche enregistrée sans date de publication part tout de suite chez le client ; avec une date, elle reste en
     * brouillon jusque-là. Renvoie vrai si elle vient d'être publiée (faux aussi sans le droit de publier).
     */
    public function publishUnlessScheduled(ProjectTask $task, Context $context): bool
    {
        if (! $task->state instanceof TaskDraft || $task->publish_at !== null || ! $this->allows($task, 'publish', $context)) {
            return false;
        }

        $this->run($task, 'publish', [], $context);

        return true;
    }
}
