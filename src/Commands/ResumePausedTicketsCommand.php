<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Console\Command;

/** Reprend les tickets en pause dont la date de reprise est passée (`AutoResume`). Planifiée tous les quarts d'heure. */
class ResumePausedTicketsCommand extends Command
{
    protected $signature = 'tma:resume-paused';

    protected $description = 'Reprend les tickets en pause arrivés à leur date de reprise';

    public function handle(TicketWorkflow $workflow): int
    {
        $context = Context::scheduler();
        $resumed = 0;

        Ticket::query()
            ->where('state', 'paused')
            ->where('paused_until', '<=', now())
            ->each(function (Ticket $ticket) use ($workflow, $context, &$resumed): void {
                if ($workflow->allows($ticket, 'auto-resume', $context)) {
                    $workflow->run($ticket, 'auto-resume', [], $context);
                    $resumed++;
                }
            });

        $this->components->info("{$resumed} ticket(s) repris.");

        return self::SUCCESS;
    }
}
