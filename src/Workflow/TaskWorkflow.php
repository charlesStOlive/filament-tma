<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Events\TaskCreated;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

            $move->apply($task, $data, $context);
            $task->state->transitionTo($move->to());

            $transition = $task->transitions()->create([
                'ulid' => (string) Str::ulid(),
                'move' => $move->key(),
                'from_state' => $from::getMorphClass(),
                'to_state' => $move->to()::getMorphClass(),
                'actor_id' => $context->actor?->getKey(),
                'origin_instance_uid' => $context->instanceUid,
                'origin' => $context->origin,
                'reason' => $move->reason($data),
            ]);

            $task->touchActivity();

            return $transition;
        });

        event(new TaskTransitioned($task->refresh(), $transition, $context));

        return $transition;
    }

    /**
     * Crée une tâche en brouillon, pour une application cliente (maître seulement).
     *
     * @param  array<string, mixed>  $attributes  title, description, target_instance_uid, project, starts_on, ends_on, publish_at
     */
    public function create(array $attributes, Context $context): ProjectTask
    {
        if (! Tma::isMaster()) {
            throw new AuthorizationException(__('Les tâches projet se créent chez le Support.'));
        }

        if (! $context->isSystem() && Gate::getPolicyFor(ProjectTask::class) !== null && ! Gate::forUser($context->user)->allows('create', ProjectTask::class)) {
            throw new AuthorizationException(__('Vous ne pouvez pas créer de tâche.'));
        }

        $instance = Instance::query()->where('uid', $attributes['target_instance_uid'] ?? null)->firstOrFail();

        $task = ProjectTask::create([
            ...collect($attributes)->only(['title', 'description', 'project', 'starts_on', 'ends_on', 'publish_at'])->all(),
            'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
            'created_by_actor_id' => $context->actor?->getKey(),
            'assignee_actor_id' => $context->actor?->getKey(),
        ]);

        event(new TaskCreated($task, $context));

        return $task;
    }
}
