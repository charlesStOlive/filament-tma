<?php

namespace CharlesStOlive\FilamentTma\States\Task;

use CharlesStOlive\FilamentStateFusionEnhanced\Concerns\StateFusionInfo;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentState;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentStateFusion;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Où en est une tâche projet (CDC §8) :
 *
 *     draft ──(Publier)──> in_progress ──(Terminer)──> done
 *       └───────────────────────┴──────(Annuler)─────> cancelled
 *
 * « Publier » n'est pas un état : c'est le passage de Brouillon à En cours, qui met la tâche à disposition de son
 * application cliente.
 */
abstract class TaskState extends State implements HasFilamentState, HasFilamentStateFusion
{
    use StateFusionInfo;

    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): string;

    public function getDescription(): ?string
    {
        return null;
    }

    public function isFinal(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(TaskDraft::class)
            ->registerState([TaskDraft::class, TaskInProgress::class, TaskDone::class, TaskCancelled::class])
            ->allowTransition(TaskDraft::class, TaskInProgress::class)
            ->allowTransition(TaskInProgress::class, TaskDone::class)
            ->allowTransition([TaskDraft::class, TaskInProgress::class], TaskCancelled::class)
            ->allowTransition([TaskDone::class, TaskCancelled::class], TaskInProgress::class);
    }
}
