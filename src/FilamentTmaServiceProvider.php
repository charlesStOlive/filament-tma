<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Commands\CloseStaleTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\CommentTicketCommand;
use CharlesStOlive\FilamentTma\Commands\ListTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\LogTimeCommand;
use CharlesStOlive\FilamentTma\Commands\PublishScheduledTasksCommand;
use CharlesStOlive\FilamentTma\Commands\ShowTicketCommand;
use CharlesStOlive\FilamentTma\Commands\ResumePausedTicketsCommand;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Listeners\NotifyAutoClosure;
use CharlesStOlive\FilamentTma\Listeners\QueuePush;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TmaGate;
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
                \CharlesStOlive\FilamentTma\Commands\SendDigestCommand::class,
            ])
            ->hasMigrations([
                'create_filament_tma_tables',
                'create_filament_tma_instances_table',
                'create_filament_tma_time_entries_table',
                'create_filament_tma_tasks_table',
                'create_filament_tma_allowances_table',
                'add_publish_at_to_tma_tickets_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketWorkflow::class);
        $this->app->singleton(Timeline::class);
        $this->app->singleton(\CharlesStOlive\FilamentTma\Workflow\TaskWorkflow::class);
        $this->app->singleton(\CharlesStOlive\FilamentTma\Workflow\TimeLog::class);
    }

    /**
     * Le CSS de la chronologie, publié avec les assets de Filament (`php artisan filament:assets`) ; les tâches
     * planifiées (clôture des tickets sans réponse chaque matin, reprise des pauses) ; les notifications.
     */
    public function packageBooted(): void
    {
        TmaGate::registerResource(Ticket::class, TicketResource::class);
        TmaGate::registerResource(ProjectTask::class, TaskResource::class);

        Event::listen(TicketTransitioned::class, NotifyAutoClosure::class);
        Event::listen(TimeEntryChanged::class, \CharlesStOlive\FilamentTma\Listeners\CheckAllowances::class);
        Event::listen(TimeEntryChanged::class, \CharlesStOlive\FilamentTma\Listeners\QueueAllowancesPush::class);
        Event::listen(\CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached::class, \CharlesStOlive\FilamentTma\Listeners\NotifyAllowanceThreshold::class);
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
        });

        FilamentAsset::register([
            Css::make('filament-tma', __DIR__.'/../resources/css/filament-tma.css'),
        ], 'charlesstolive/filament-tma');
    }
}
