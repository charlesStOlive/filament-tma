<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Commands\CloseStaleTicketsCommand;
use CharlesStOlive\FilamentTma\Commands\ResumePausedTicketsCommand;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Listeners\NotifyAutoClosure;
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
            ->hasRoute('web')
            ->hasCommands([CloseStaleTicketsCommand::class, ResumePausedTicketsCommand::class])
            ->hasMigrations([
                'create_filament_tma_tables',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketWorkflow::class);
        $this->app->singleton(Timeline::class);
    }

    /**
     * Le CSS de la chronologie, publié avec les assets de Filament (`php artisan filament:assets`) ; les tâches
     * planifiées (clôture des tickets sans réponse chaque matin, reprise des pauses) ; les notifications.
     */
    public function packageBooted(): void
    {
        Event::listen(TicketTransitioned::class, NotifyAutoClosure::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('tma:close-stale')->dailyAt((string) config('filament-tma.auto_close.at', '07:00'))->withoutOverlapping();
            $schedule->command('tma:resume-paused')->everyFifteenMinutes()->withoutOverlapping();
        });

        FilamentAsset::register([
            Css::make('filament-tma', __DIR__.'/../resources/css/filament-tma.css'),
        ], 'charlesstolive/filament-tma');
    }
}
