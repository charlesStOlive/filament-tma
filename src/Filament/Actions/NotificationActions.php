<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\Support\DigestSender;
use CharlesStOlive\FilamentTma\Support\NoticeChannels;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;

/**
 * Les notifications dans l'interface :
 * - « Mes notifications » : chacun choisit comment il est prévenu quand il est destinataire principal (courriel,
 *   cloche, les deux) ; aussi dans le menu de l'utilisateur (`?action=tmaNotifications`) ;
 * - « Envoyer maintenant » : les récapitulatifs de qui a des notifications en attente, sans attendre le matin
 *   (droit `notify`) ;
 * - « Relancer » : sur un ticket qui attend quelqu'un d'autre, un message marqué relance part dans l'historique, et
 *   qui a la main reçoit un courriel tout de suite (droit `remind`).
 */
final class NotificationActions
{
    public static function settings(): Action
    {
        return Action::make('tmaNotifications')
            ->label(__('Mes notifications'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (): bool => NoticeChannels::userChoice() && auth()->check())
            ->modalHeading(__('Mes notifications du Support'))
            ->modalDescription(__('Destinataire principal d’un ticket ou d’une tâche, vous êtes prévenu tout de suite de ce qui s’y passe. Destinataire secondaire, vous l’apprenez dans le bilan du matin (:at).', [
                'at' => config('filament-tma.notifications.digest.at', '08:00'),
            ]))
            ->fillForm(fn (): array => ['channels' => NoticeChannels::for(auth()->user())])
            ->schema([
                CheckboxList::make('channels')
                    ->label(__('Quand je suis destinataire principal, me prévenir'))
                    ->options(NoticeChannels::options())
                    ->helperText(__('Sans aucun des deux, tout attend votre bilan. Une relance arrive toujours par courriel.')),
            ])
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->action(function (array $data): void {
                NoticeChannels::save(auth()->user(), (array) ($data['channels'] ?? []));
                Notification::make()->success()->title(__('Réglages enregistrés'))->send();
            });
    }

    public static function sendNow(): Action
    {
        return Action::make('tmaSendNotifications')
            ->label(__('Envoyer maintenant'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => TmaGate::allows(auth()->user(), TmaGate::NOTIFY))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalHeading(__('Envoyer les notifications en attente ?'))
            ->modalDescription(function (): string {
                $counts = DigestSender::pendingCounts();

                return $counts['notices'] === 0
                    ? __('Rien n’attend un envoi groupé.')
                    : __(':n notification(s) en attente pour :r personne(s) : chacune reçoit son récapitulatif maintenant, au lieu d’attendre le prochain.', ['n' => $counts['notices'], 'r' => $counts['recipients']]);
            })
            ->modalSubmitActionLabel(__('Envoyer'))
            ->action(function (): void {
                $result = DigestSender::send(pendingOnly: true);
                Notification::make()->success()
                    ->title(__(':r récapitulatif(s) envoyé(s)', ['r' => $result['recipients']]))
                    ->body(__(':n notification(s).', ['n' => $result['notices']]))
                    ->send();
            });
    }

    public static function remind(): Action
    {
        return Action::make('remind')
            ->label(__('Relancer'))
            ->icon('heroicon-m-bell-alert')
            ->color('gray')
            ->visible(fn (Ticket $record): bool => self::canRemind($record, Context::current()))
            ->modalHeading(fn (Ticket $record): string => __('Relancer : :who', ['who' => match ($record->sideInCharge()) {
                Side::Requester => $record->requester?->name,
                Side::Handler => $record->assignee?->name,
                default => null,
            } ?? $record->waitingOn()]))
            ->modalDescription(__('Un message marqué « Relance » part dans l’historique ; qui a la main reçoit un courriel tout de suite.'))
            ->schema([
                Textarea::make('message')
                    ->label(__('Votre mot (facultatif)'))
                    ->placeholder(self::defaultReminder())
                    ->rows(3),
            ])
            ->modalSubmitActionLabel(__('Relancer'))
            ->action(function (Ticket $record, array $data, Component $livewire): void {
                $text = trim((string) ($data['message'] ?? '')) ?: self::defaultReminder();
                app(Timeline::class)->add($record, EntryType::Comment, '<p>'.nl2br(e($text)).'</p>', Context::current(), meta: ['reminder' => true]);

                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('Relance envoyée'))->send();
            });
    }

    /**
     * Relancer, c'est pour un ticket qui avance et attend quelqu'un d'autre : le demandeur, quand on traite ; qui
     * l'a pris en charge (ou l'équipe qui doit le prendre), quand on attend.
     */
    public static function canRemind(Ticket $ticket, Context $context): bool
    {
        $user = $context->user;

        if (! $user instanceof Authenticatable || $ticket->state->isFinal() || $ticket->state instanceof Draft || $ticket->state instanceof Paused) {
            return false;
        }

        if (! TmaGate::allows($user, TmaGate::REMIND, $ticket) || ! app(Timeline::class)->canAdd($ticket, EntryType::Comment, $context)) {
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
        return __('Ce ticket attend votre retour.');
    }
}
