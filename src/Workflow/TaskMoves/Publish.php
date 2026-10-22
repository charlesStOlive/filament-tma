<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsFields;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;

/** « Publier chez EDM » : met la tâche à disposition de son application cliente ; elle passe En cours et part chez le client. */
class Publish extends TaskMove
{
    public function key(): string
    {
        return 'publish';
    }

    public function label(): string
    {
        return Terms::publish();
    }

    public function labelFor(ProjectTask $task): string
    {
        return Terms::publish($task->target_instance_uid);
    }

    public function description(): ?string
    {
        return __('La tâche devient visible dans l’application du client.');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-megaphone';
    }

    public function from(): array
    {
        return [TaskDraft::class];
    }

    public function to(): string
    {
        return TaskInProgress::class;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function rules(ProjectTask $task): array
    {
        return ['recipients' => ['nullable', 'array']];
    }

    /** Sans destinataire principal chez le client, il faut en choisir au moins un pour publier. */
    public function formSchema(ProjectTask $task): array
    {
        if (self::hasClientPrimary($task)) {
            return [];
        }

        return [
            RecipientsFields::primaries('recipients', fn (): array => array_diff_key(Addressables::options($task->target_instance_uid), [Terms::support() => true]), fn () => $task->project)
                ->label(__('Destinataires principaux chez le client'))
                ->helperText(__('Prévenus de la publication, puis de tout ce qui arrive à la tâche.'))
                ->required(),
        ];
    }

    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->published_at = $this->happenedAt($task);
        $task->publish_at = null;

        foreach ((array) ($data['recipients'] ?? []) as $key) {
            app(Recipients::class)->add($task, Addressables::resolve((string) $key, $task->target_instance_uid));
        }
    }

    public static function hasClientPrimary(ProjectTask $task): bool
    {
        return app(Recipients::class)->hasPrimary($task, fn (Actor $actor): bool => $actor->instance_uid === $task->target_instance_uid);
    }
}
