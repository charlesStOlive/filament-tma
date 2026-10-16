<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\AuditResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Les écrans de filament-tma dans un panel :
 *
 *     ->plugins([FilamentTmaPlugin::make()])
 *
 * Le mode (maître ou client), l'identifiant de l'installation et le reste se règlent dans config/filament-tma.php.
 */
class FilamentTmaPlugin implements Plugin
{
    public const ID = 'filament-tma';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(self::ID);
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            TicketResource::class,
            ProjectResource::class,
            TaskResource::class,
            InstanceResource::class,
            AllowanceResource::class,
            AuditResource::class,
        ]);

        $panel->widgets([
            AllowanceOverview::class,
        ]);

        // « Mes notifications » : la fenêtre de la liste des tickets, ouverte d'ici (`NotificationActions::settings()`).
        $panel->userMenuItems([
            'tma-notifications' => Action::make('tmaNotificationsMenu')
                ->label(fn (): string => __('Mes notifications du Support'))
                ->icon('heroicon-o-bell')
                ->url(fn (): string => TicketResource::getUrl('index', ['action' => 'tmaNotifications']))
                ->visible(fn (): bool => NoticeChannels::userChoice() && TicketResource::canViewAny()),
        ]);

        // La visionneuse des images, une fois par fiche de ticket ou de tâche (voir `attachments.blade.php`).
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament-tma::lightbox'), [ViewTicket::class, ViewTask::class]);
    }

    public function boot(Panel $panel): void {}
}
