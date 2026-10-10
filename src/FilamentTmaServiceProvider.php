<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Commands\CleanupCommand;
use CharlesStOlive\FilamentTma\Commands\CloseStaleTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\CommentTicketCommand;
use CharlesStOlive\FilamentTma\Commands\InstallCommand;
use CharlesStOlive\FilamentTma\Commands\ListTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\LogTimeCommand;
use CharlesStOlive\FilamentTma\Commands\PublishScheduledTasksCommand;
use CharlesStOlive\FilamentTma\Commands\ResumePausedTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\SendDigestCommand;
use CharlesStOlive\FilamentTma\Commands\ShowTicketCommand;
use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Listeners\AuditTrail;
use CharlesStOlive\FilamentTma\Listeners\CheckAllowances;
use CharlesStOlive\FilamentTma\Listeners\NotifyAllowanceThreshold;
use CharlesStOlive\FilamentTma\Listeners\NotifyAutoClosure;
use CharlesStOlive\FilamentTma\Listeners\QueueAllowancesPush;
use CharlesStOlive\FilamentTma\Listeners\QueuePush;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTmaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-tma')
            ->hasConfigFile('filament-tma')
            ->hasViews('filament-tma')
            ->hasTranslations()
            ->hasRoutes(['web', 'api'])
            ->hasCommands([
                CloseStaleTicketsCommand::class,
                ResumePausedTicketsCommand::class,
                ListTicketsCommand::class,
                ShowTicketCommand::class,
                LogTimeCommand::class,
                CommentTicketCommand::class,
                PublishScheduledTasksCommand::class,
                SendDigestCommand::class,
                CleanupCommand::class,
                InstallCommand::class,
            ])
            ->hasMigrations([
                'create_filament_tma_tables',
                'create_filament_tma_instances_table',
                'create_filament_tma_time_entries_table',
                'create_filament_tma_tasks_table',
                'create_filament_tma_allowances_table',
                'add_publish_at_to_tma_tickets_table',
                'create_filament_tma_audits_table',
                'create_filament_tma_agents_tables',
                'add_short_name_to_tma_instances_table',
                'convert_tma_summary_entries_to_messages',
                'drop_due_on_from_tma_tickets_table',
                'rename_ends_on_to_due_on_in_tma_tasks_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketWorkflow::class);
        $this->app->singleton(Timeline::class);
        $this->app->singleton(TaskWorkflow::class);
        $this->app->singleton(TimeLog::class);
    }

    /**
     * Le CSS de la chronologie, publié avec les assets de Filament (`php artisan filament:assets`) ; les tâches
     * planifiées (clôture des tickets sans réponse chaque matin, reprise des pauses) ; les notifications.
     */
    public function packageBooted(): void
    {
        // L'aide (base de connaissances Guava) : groupe « TMA et tâches projet », pages `tma.*`.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../docs/knowledge-base' => base_path('docs/knowledge-base/fr'),
            ], 'filament-tma-docs');
        }

        TmaGate::registerResource(Ticket::class, TicketResource::class);
        TmaGate::registerResource(ProjectTask::class, TaskResource::class);

        if (config('filament-tma.audit.enabled', true)) {
            AuditTrail::register();
        }

        Event::listen(TicketTransitioned::class, NotifyAutoClosure::class);
        Event::listen(TimeEntryChanged::class, CheckAllowances::class);
        Event::listen(TimeEntryChanged::class, QueueAllowancesPush::class);
        Event::listen(AllowanceThresholdReached::class, NotifyAllowanceThreshold::class);
        Event::listen([
            TicketTransitioned::class, TaskTransitioned::class, EntryAdded::class, NoteShared::class,
            TicketUpdated::class, TaskUpdated::class, AutoCloseWarned::class, TimeEntryChanged::class,
        ], QueuePush::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('tma:close-stale')->dailyAt((string) config('filament-tma.auto_close.at', '07:00'))->withoutOverlapping();
            $schedule->command('tma:resume-paused')->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command('tma:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('tma:digest')
                ->days((array) config('filament-tma.notifications.digest.days', [1, 2, 3, 4, 5]))
                ->dailyAt((string) config('filament-tma.notifications.digest.at', '08:00'))
                ->timezone((string) config('filament-tma.notifications.digest.timezone', config('app.timezone')))
                ->withoutOverlapping();

            if (config('filament-tma.cleanup.schedule', false)) {
                $schedule->command('tma:cleanup')->weeklyOn(0, '03:00')->withoutOverlapping();
            }
        });

        FilamentAsset::register([
            Css::make('filament-tma', __DIR__.'/../resources/css/filament-tma.css'),
        ], 'charlesstolive/filament-tma');
    }
}
