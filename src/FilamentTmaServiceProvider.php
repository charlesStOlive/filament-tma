<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Commands\CloseStaleTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\CommentTicketCommand;
use CharlesStOlive\FilamentTma\Commands\ListTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\LogTimeCommand;
use CharlesStOlive\FilamentTma\Commands\ShowTicketCommand;
use CharlesStOlive\FilamentTma\Commands\ResumePausedTicketsCommand;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Listeners\NotifyAutoClosure;
use CharlesStOlive\FilamentTma\Listeners\QueueTicketPush;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
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
            ])
            ->hasMigrations([
                'create_filament_tma_tables',
                'create_filament_tma_instances_table',
                'create_filament_tma_time_entries_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketWorkflow::class);
        $this->app->singleton(Timeline::class);
        $this->app->singleton(\CharlesStOlive\FilamentTma\Workflow\TimeLog::class);
    }

    /**
     * Le CSS de la chronologie, publié avec les assets de Filament (`php artisan filament:assets`) ; les tâches
     * planifiées (clôture des tickets sans réponse chaque matin, reprise des pauses) ; les notifications.
     */
    public function packageBooted(): void
    {
        Event::listen(TicketTransitioned::class, NotifyAutoClosure::class);
        Event::listen([TicketTransitioned::class, EntryAdded::class, NoteShared::class, TicketUpdated::class, AutoCloseWarned::class, TimeEntryChanged::class], QueueTicketPush::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('tma:close-stale')->dailyAt((string) config('filament-tma.auto_close.at', '07:00'))->withoutOverlapping();
            $schedule->command('tma:resume-paused')->everyFifteenMinutes()->withoutOverlapping();
        });

        FilamentAsset::register([
            Css::make('filament-tma', __DIR__.'/../resources/css/filament-tma.css'),
        ], 'charlesstolive/filament-tma');
    }
}
