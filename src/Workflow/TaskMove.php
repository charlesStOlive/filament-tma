<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use Carbon\CarbonInterface;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskState;

/**
 * Un mouvement du workflow d'une tâche projet : « Publier chez EDM », « Terminer », « Annuler ». Seul le maître les fait ;
 * le droit est `tma.task.workflow.{clé}`. Même forme que `Move` (tickets), pour des objets distincts.
 */
abstract class TaskMove
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** Le libellé pour une tâche donnée : il peut nommer son client (« Publier chez EDM », voir `Support\Terms`). */
    public function labelFor(ProjectTask $task): string
    {
        return $this->label();
    }

    public function description(): ?string
    {
        return null;
    }

    public function color(): string
    {
        return 'primary';
    }

    public function icon(): ?string
    {
        return null;
    }

    /** @return array<int, class-string<TaskState>> */
    abstract public function from(): array;

    /** @return class-string<TaskState> */
    abstract public function to(): string;

    /** Un mouvement du planificateur seulement (pas un bouton, pas un droit). */
    public function isSystem(): bool
    {
        return false;
    }

    public function isAvailable(ProjectTask $task): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(ProjectTask $task): array
    {
        return [];
    }

    /** @return array<int, mixed> */
    public function formSchema(ProjectTask $task): array
    {
        return [];
    }

    public function apply(ProjectTask $task, array $data, Context $context): void {}

    /**
     * Le moment où le passage compte comme fait (son historique, la dernière activité) : maintenant, sauf pour une
     * publication programmée, faite à l'heure dite quel que soit le passage du planificateur.
     */
    public function happenedAt(ProjectTask $task): CarbonInterface
    {
        return now();
    }

    public function reason(array $data): ?string
    {
        return isset($data['reason']) && filled($data['reason']) ? (string) $data['reason'] : null;
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }
}
