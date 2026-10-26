<?php

namespace CharlesStOlive\FilamentTma;

use Carbon\CarbonInterface;
use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\AuditResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview;
use CharlesStOlive\FilamentTma\Filament\Widgets\MySupportWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\TimeReportWidget;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Checkbox;
use Filament\Panel;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
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
                'label' => __('filament-tma::plugin.options.label'),
                'description' => __('filament-tma::plugin.options.description'),
                'icon' => 'heroicon-o-bell',
                'sort' => 50,
                'visible' => fn (Authenticatable $user): bool => Gate::forUser($user)->allows('viewAny', Ticket::class)
                    || Gate::forUser($user)->allows('viewAny', ProjectTask::class),
                'defaults' => fn (): array => [
                    'notifications' => [
                        // L'e-mail direct, rangé comme avant la cloche obligatoire : les choix déjà faits restent valables.
                        'channels' => NoticeChannels::defaultChannels(),
                        'digest' => in_array(NoticeChannels::DIGEST, NoticeChannels::defaults(), true),
                        'weekly' => in_array(NoticeChannels::WEEKLY, NoticeChannels::defaults(), true),
                    ],
                ],
                'schema' => fn (): array => [
                    Fieldset::make(__('filament-tma::common.tickets'))
                        ->columns(1)
                        ->schema([
                            Checkbox::make('notifications.channels')
                                ->label(__('filament-tma::plugin.options.direct_email'))
                                ->helperText(__('filament-tma::plugin.options.direct_email_help'))
                                ->formatStateUsing(fn (mixed $state): bool => in_array(NoticeChannels::MAIL, (array) $state, true))
                                ->dehydrateStateUsing(fn (mixed $state): array => $state ? [NoticeChannels::MAIL] : []),
                            Checkbox::make('notifications.digest')
                                ->label(__('filament-tma::plugin.options.morning_digest'))
                                ->helperText(__('filament-tma::plugin.options.morning_digest_help', [
                                    'at' => config('filament-tma.notifications.digest.at', '08:00'),
                                ]))
                                ->visible(fn (): bool => (bool) config('filament-tma.notifications.digest.enabled', true))
                                ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if (! $value && ! $get('notifications.channels')) {
                                        $fail(__('filament-tma::plugin.options.ticket_channel_required'));
                                    }
                                }),
                        ]),
                    Fieldset::make(__('filament-tma::plugin.options.project_tasks'))
                        ->columns(1)
                        ->visible(fn (): bool => (bool) config('filament-tma.notifications.weekly.enabled', true))
                        ->schema([
                            Checkbox::make('notifications.weekly')
                                ->label(__('filament-tma::plugin.options.weekly_digest'))
                                ->helperText(__('filament-tma::plugin.options.weekly_digest_help', [
                                    'day' => now()->startOfWeek(CarbonInterface::MONDAY)->addDays((int) config('filament-tma.notifications.weekly.day', 1) - 1)->locale(app()->getLocale())->dayName,
                                ])),
                        ]),
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
            MySupportWidget::class,
            TimeReportWidget::class,
        ]);

        // La visionneuse des images, une fois par fiche de ticket ou de tâche (voir `attachments.blade.php`).
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament-tma::lightbox'), [ViewTicket::class, ViewTask::class]);
    }

    public function boot(Panel $panel): void {}
}
