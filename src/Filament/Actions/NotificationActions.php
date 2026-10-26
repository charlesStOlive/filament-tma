<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentPermissionManager\UserOptions\Contracts\HasUserOptions;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\Support\DigestSender;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;

/**
 * Les notifications dans l'interface :
 * - « Mes notifications » : lien vers la section Notifications du Support de la page Profil ;
 * - « Envoyer maintenant » : les récapitulatifs de qui a des notifications en attente, sans attendre le matin
 *   (droit `notify`) ;
 * - « Relancer » : sur un ticket qui attend quelqu'un d'autre, un message marqué relance part dans l'historique, et
 *   qui a la main reçoit un courriel tout de suite (droit `remind`) ;
 * - « Informer par courriel » : sur une tâche publiée, dans le maître, un message marqué `notify` part dans
 *   l'historique, et les destinataires principaux du client le reçoivent par courriel (sinon, une tâche ne leur
 *   parvient que dans la cloche et le bilan de la semaine ; droit `comment` de la tâche).
 */
final class NotificationActions
{
    public static function profileLink(): Action
    {
        return Action::make('tmaNotifications')
            ->label(__('filament-tma::notification_settings.mine'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (): bool => NoticeChannels::userChoice()
                && auth()->user() instanceof HasUserOptions
                && ! in_array('tma', config('filament-permission-manager.user_options.disabled', []), true)
                && filament()->getProfileUrl() !== null)
            ->url(fn (): string => filament()->getProfileUrl().'#user-options-tma');
    }

    public static function sendNow(): Action
    {
        return Action::make('tmaSendNotifications')
            ->label(__('filament-tma::notification_settings.flush.label'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => TmaGate::allows(auth()->user(), TmaGate::NOTIFY))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalHeading(__('filament-tma::notification_settings.flush.heading'))
            ->modalDescription(function (): string {
                $counts = DigestSender::pendingCounts();

                return $counts['notices'] === 0
                    ? __('filament-tma::notification_settings.flush.nothing')
                    : __('filament-tma::notification_settings.flush.description', ['n' => $counts['notices'], 'r' => $counts['recipients']]);
            })
            ->modalSubmitActionLabel(__('filament-tma::notification_settings.flush.submit'))
            ->action(function (): void {
                $result = DigestSender::send(pendingOnly: true);
                Notification::make()->success()
                    ->title(__('filament-tma::notification_settings.flush.done', ['r' => $result['recipients']]))
                    ->body(__('filament-tma::notification_settings.flush.done_body', ['n' => $result['notices']]))
                    ->send();
            });
    }

    public static function remind(): Action
    {
        return Action::make('remind')
            ->label(__('filament-tma::notification_settings.remind.label'))
            ->icon('heroicon-m-bell-alert')
            ->color('gray')
            ->visible(fn (Ticket $record): bool => self::canRemind($record, Context::current()))
            ->modalHeading(fn (Ticket $record): string => __('filament-tma::notification_settings.remind.heading', ['who' => match ($record->sideInCharge()) {
                Side::Requester => $record->requester?->name,
                Side::Handler => $record->assignee?->name,
                default => null,
            } ?? $record->waitingOn()]))
            ->modalDescription(__('filament-tma::notification_settings.remind.description'))
            ->schema([
                Textarea::make('message')
                    ->label(__('filament-tma::notification_settings.remind.message'))
                    ->placeholder(self::defaultReminder())
                    ->rows(3),
            ])
            ->modalSubmitActionLabel(__('filament-tma::notification_settings.remind.label'))
            ->action(function (Ticket $record, array $data, Component $livewire): void {
                $text = trim((string) ($data['message'] ?? '')) ?: self::defaultReminder();
                app(Timeline::class)->add($record, EntryType::Comment, '<p>'.nl2br(e($text)).'</p>', Context::current(), meta: ['reminder' => true]);

                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('filament-tma::notification_settings.remind.sent'))->send();
            });
    }

    public static function informByMail(): Action
    {
        return Action::make('informByMail')
            ->label(__('filament-tma::notification_settings.inform.label'))
            ->icon('heroicon-m-envelope')
            ->color('gray')
            ->visible(fn (ProjectTask $record): bool => self::canInform($record, Context::current()))
            ->modalHeading(__('filament-tma::notification_settings.inform.heading'))
            ->modalDescription(__('filament-tma::notification_settings.inform.description'))
            ->schema([
                Textarea::make('message')
                    ->label(__('filament-tma::common.message'))
                    ->required()
                    ->rows(5),
            ])
            ->modalSubmitActionLabel(__('filament-tma::notification_settings.flush.submit'))
            ->action(function (ProjectTask $record, array $data, Component $livewire): void {
                app(Timeline::class)->add($record, EntryType::Comment, '<p>'.nl2br(e(trim((string) $data['message']))).'</p>', Context::current(), meta: ['notify' => true]);

                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('filament-tma::notification_settings.inform.sent'))->body(__('filament-tma::notification_settings.inform.sent_body'))->send();
            });
    }

    /** Informer par courriel : le Support, dans le maître, sur une tâche publiée chez le client où il peut écrire. */
    public static function canInform(ProjectTask $task, Context $context): bool
    {
        return Tma::isMaster() && $task->isShared() && $task->published_at !== null
            && app(Timeline::class)->canAdd($task, EntryType::Comment, $context);
    }

    /**
     * Relancer, c'est pour un ticket qui avance et attend quelqu'un d'autre : le demandeur, quand on traite ; qui
     * est son contact au Support (ou le Support, sans contact), quand on attend.
     */
    public static function canRemind(Ticket $ticket, Context $context): bool
    {
        $user = $context->user;

        if (! $user instanceof Authenticatable || $ticket->state->isFinal() || $ticket->state instanceof Draft || $ticket->state instanceof Paused) {
            return false;
        }

        if (! TmaGate::allows($user, TmaGate::REMIND, $ticket) || ! app(Timeline::class)->canAdd($ticket, EntryType::Comment, $context, reminder: true)) {
            return false;
        }

        $mine = Actor::idsFor($user);
        $holder = match ($ticket->sideInCharge()) {
            Side::Requester => $ticket->requester_actor_id,
            Side::Handler => $ticket->assignee_actor_id,
            default => null,
        };

        return $holder !== null
            ? ! in_array((int) $holder, $mine, true)
            : $ticket->sideInCharge() === Side::Handler && in_array((int) $ticket->requester_actor_id, $mine, true);
    }

    private static function defaultReminder(): string
    {
        return __('filament-tma::notification_settings.remind.default_message');
    }
}
