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
use CharlesStOlive\FilamentTma\Filament\Widgets\OngoingTicketsWidget;
use CharlesStOlive\FilamentTma\Filament\Widgets\ProjectTasksWidget;
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
                'label' => __('Notifications du Support'),
                'description' => __('Un ticket attend parfois quelque chose de vous ; une tâche projet, jamais : elle vous tient au courant. La cloche de l’application vous prévient toujours.'),
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
                    Fieldset::make(__('Tickets'))
                        ->columns(1)
                        ->schema([
                            Checkbox::make('notifications.channels')
                                ->label(__('E-mail direct : un courriel dès qu’un ticket attend mon action'))
                                ->helperText(__('Une demande à prendre, une question à laquelle répondre, une solution à confirmer. Une relance arrive toujours par courriel.'))
                                ->formatStateUsing(fn (mixed $state): bool => in_array(NoticeChannels::MAIL, (array) $state, true))
                                ->dehydrateStateUsing(fn (mixed $state): array => $state ? [NoticeChannels::MAIL] : []),
                            Checkbox::make('notifications.digest')
                                ->label(__('Bilan du matin'))
                                ->helperText(__('Les jours ouvrés à :at : ce qui vous attend, la fin de vos tickets, et en bas ce que vous suivez en copie.', [
                                    'at' => config('filament-tma.notifications.digest.at', '08:00'),
                                ]))
                                ->visible(fn (): bool => (bool) config('filament-tma.notifications.digest.enabled', true))
                                ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if (! $value && ! $get('notifications.channels')) {
                                        $fail(__('Choisissez l’e-mail direct, le bilan du matin, ou les deux : un ticket qui vous attend doit vous parvenir.'));
                                    }
                                }),
                        ]),
                    Fieldset::make(__('Tâches projet'))
                        ->columns(1)
                        ->visible(fn (): bool => (bool) config('filament-tma.notifications.weekly.enabled', true))
                        ->schema([
                            Checkbox::make('notifications.weekly')
                                ->label(__('Bilan de la semaine'))
                                ->helperText(__('Le :day : ce qui a avancé sur vos tâches projet, pour information. Il n’y a pas de courriel à chaque étape.', [
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
            OngoingTicketsWidget::class,
            ProjectTasksWidget::class,
        ]);

        // La visionneuse des images, une fois par fiche de ticket ou de tâche (voir `attachments.blade.php`).
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament-tma::lightbox'), [ViewTicket::class, ViewTask::class]);
    }

    public function boot(Panel $panel): void {}
}
