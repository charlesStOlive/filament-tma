<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Illuminate\Console\Command;

/** Publie les tâches dont la publication programmée est arrivée (maître). Planifiée toutes les cinq minutes. */
class PublishScheduledTasksCommand extends Command
{
    protected $signature = 'tma:publish-scheduled';

    protected $description = 'Publie les tâches projet arrivées à leur date de publication';

    public function handle(TaskWorkflow $workflow): int
    {
        $context = Context::scheduler();
        $published = 0;

        ProjectTask::query()
            ->where('state', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->each(function (ProjectTask $task) use ($workflow, $context, &$published): void {
                if ($workflow->allows($task, 'auto-publish', $context)) {
                    $workflow->run($task, 'auto-publish', [], $context);
                    $published++;
                }
            });

        $this->components->info("{$published} tâche(s) publiée(s).");

        return self::SUCCESS;
    }
}
