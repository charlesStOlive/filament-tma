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
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

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

    /** @return array<string, array<string, mixed>> */
    public static function userOptionGroups(): array
    {
        if (! NoticeChannels::userChoice()) {
            return [];
        }

        return [
            'tma' => [
                'label' => __('Notifications du Support'),
                'description' => __('Destinataire principal d’un ticket ou d’une tâche, vous êtes prévenu tout de suite de ce qui s’y passe. Destinataire secondaire, vous l’apprenez dans le bilan du matin (:at).', [
                    'at' => config('filament-tma.notifications.digest.at', '08:00'),
                ]),
                'icon' => 'heroicon-o-bell',
                'sort' => 50,
                'visible' => fn (Authenticatable $user): bool => Gate::forUser($user)->allows('viewAny', Ticket::class)
                    || Gate::forUser($user)->allows('viewAny', ProjectTask::class),
                'defaults' => fn (): array => [
                    'notifications' => [
                        'channels' => array_values(array_intersect([NoticeChannels::MAIL, NoticeChannels::APP], NoticeChannels::defaults())),
                        'digest' => in_array(NoticeChannels::DIGEST, NoticeChannels::defaults(), true),
                    ],
                ],
                'schema' => fn (): array => [
                    CheckboxList::make('notifications.channels')
                        ->label(__('Quand je suis destinataire principal, me prévenir'))
                        ->options(NoticeChannels::options())
                        ->helperText(__('Sans aucun des deux, tout attend votre bilan. Une relance arrive toujours par courriel.')),
                    Checkbox::make('notifications.digest')
                        ->label(__('Recevoir le bilan du matin'))
                        ->helperText(__('Ce qui vous attend, et ce qui s’est passé là où vous êtes destinataire secondaire. Sans le bilan, rien de cela ne vous parvient.'))
                        ->visible(fn (): bool => (bool) config('filament-tma.notifications.digest.enabled', true)),
                ],
            ],
        ];
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

        // La visionneuse des images, une fois par fiche de ticket ou de tâche (voir `attachments.blade.php`).
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament-tma::lightbox'), [ViewTicket::class, ViewTask::class]);
    }

    public function boot(Panel $panel): void {}
}
