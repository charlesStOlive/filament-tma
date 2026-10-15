<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Console\Command;

/**
 * Publie les tâches projet, et les brouillons de tickets du maître, dont la publication programmée est arrivée.
 * Planifiée chaque minute ; une tâche compte comme publiée à l'heure dite (`TaskMoves\AutoPublish`).
 */
class PublishScheduledTasksCommand extends Command
{
    protected $signature = 'tma:publish-scheduled';

    protected $description = 'Publie les tâches projet et les tickets préparés arrivés à leur date de publication';

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

        $tickets = app(TicketWorkflow::class);

        Ticket::query()
            ->where('state', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->each(function ($ticket) use ($tickets, $context, &$published): void {
                if ($tickets->allows($ticket, 'auto-publish', $context)) {
                    $tickets->run($ticket, 'auto-publish', [], $context);
                    $published++;
                }
            });

        $this->components->info("{$published} tâche(s) ou ticket(s) publié(s).");

        return self::SUCCESS;
    }
}
