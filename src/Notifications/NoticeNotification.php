<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeTone;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\MailBrand;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Ce qu'une personne apprend tout de suite (`Support\Notifier`) : dans la cloche de l'application (les notifications
 * en base de Filament), et par courriel quand un ticket l'attend et qu'elle a choisi l'e-mail direct, ou quand c'est
 * imposé (relance, « Informer par courriel »). Le courriel d'un ticket dit d'abord ce qu'on attend d'elle ; celui
 * d'une tâche, qu'il est pour information (`resources/views/mail/notice.blade.php`).
 */
class NoticeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, string>  $channels  `mail`, `database` */
    public function __construct(public Notice $notice, public array $channels = ['mail'])
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->notice->data;
        $kind = $this->kind();
        $tone = $this->tone();
        $heading = (string) $data['title'];
        $headline = rtrim((string) ($data['headline'] ?? $data['line']), '.');
        $user = $notifiable instanceof Authenticatable ? $notifiable : null;
        $closesOn = isset($data['closes_on']) && $this->notice->event !== NoticeEvent::AutoCloseWarning
            ? Dates::format(Carbon::parse($data['closes_on']), 'd/m/Y', $user)
            : null;

        return (new MailMessage)
            ->subject("[{$data['ref']}] {$headline} – {$heading}")
            ->view(['filament-tma::mail.notice', 'filament-tma::mail.notice-text'], [
                // Ce qui attend la personne prend la couleur des tickets, même sur une tâche (une question du Support).
                'brand' => MailBrand::for($tone === NoticeTone::Action ? 'ticket' : $kind),
                'kind' => $kind,
                'tone' => $tone->value,
                'data' => $data,
                'kicker' => $this->kicker(),
                'heading' => $heading,
                'preheader' => $tone === NoticeTone::Action && filled($data['action'] ?? null) ? $headline.' : '.$data['action'] : $headline,
                'closesOn' => $closesOn,
                'url' => $this->notice->url(),
                'button' => $data['button'] ?? $this->openLabel(),
                'rows' => $this->rows(),
                'reason' => $this->reason(),
            ]);
    }

    /** La cloche de Filament : ce qui s'est passé, ce qu'on attend, et un lien qui marque la notification comme lue. */
    public function toDatabase(object $notifiable): array
    {
        $data = $this->notice->data;
        $body = $data['line'].(filled($data['excerpt'] ?? null) ? ' « '.$data['excerpt'].' »' : '');

        if ($this->tone() === NoticeTone::Action && filled($data['action'] ?? null)) {
            $body .= ' '.__('filament-tma::notifications.mail.todo', ['action' => lcfirst((string) $data['action'])]);
        }

        $notification = FilamentNotification::make()
            ->title("[{$data['ref']}] {$data['title']}")
            ->body($body)
            ->icon($this->icon())
            ->iconColor(match ($this->tone()) {
                NoticeTone::Action => 'warning',
                NoticeTone::Outcome => 'gray',
                NoticeTone::Info => $this->kind() === 'task' ? 'info' : 'primary',
            });

        if ($url = $this->notice->url()) {
            $notification->actions([Action::make('open')->label($data['button'] ?? $this->openLabel())->url($url)->markAsRead()]);
        }

        return $notification->getDatabaseMessage();
    }

    private function kind(): string
    {
        return ($this->notice->data['kind'] ?? 'ticket') === 'task' ? 'task' : 'ticket';
    }

    private function tone(): NoticeTone
    {
        return NoticeTone::tryFrom((string) ($this->notice->data['tone'] ?? '')) ?? NoticeTone::Info;
    }

    /** « Ticket DES-0001 · Support », « Tâche T-0001 · Nouvel environnement graphique ». */
    private function kicker(): string
    {
        $data = $this->notice->data;

        return $this->kind() === 'task'
            ? __('filament-tma::notifications.mail.task_ref', ['ref' => $data['ref']]).(filled($data['project'] ?? null) ? ' · '.$data['project'] : '')
            : __('filament-tma::notifications.mail.ticket_ref', ['ref' => $data['ref']]).' · '.__('filament-tma::common.support');
    }

    /** @return array<string, string> */
    private function rows(): array
    {
        $data = $this->notice->data;

        return array_filter([
            __('filament-tma::common.state') => $data['state'] ?? null,
            __('filament-tma::common.priority') => $data['priority'] ?? null,
            __('filament-tma::common.project') => $this->kind() === 'task' ? ($data['project'] ?? null) : null,
        ], fn (?string $value): bool => filled($value));
    }

    private function reason(): string
    {
        $ticket = $this->kind() === 'ticket';

        return match (true) {
            $this->notice->event === NoticeEvent::Reminder => __('filament-tma::notifications.mail.why.reminder'),
            $this->notice->event === NoticeEvent::Question && $this->tone() === NoticeTone::Action => __('filament-tma::notifications.mail.why.question'),
            filled($this->notice->data['message'] ?? null) => __('filament-tma::notifications.mail.why.informed'),
            ($this->notice->data['role'] ?? null) === 'secondary' => $ticket ? __('filament-tma::notifications.mail.why.secondary_ticket') : __('filament-tma::notifications.mail.why.secondary_task'),
            $this->tone() === NoticeTone::Action => $ticket
                ? __('filament-tma::notifications.mail.why.ticket_action')
                : __('filament-tma::notifications.mail.why.task_recipient'),
            default => $ticket ? __('filament-tma::notifications.mail.why.main_ticket') : __('filament-tma::notifications.mail.why.main_task'),
        };
    }

    private function openLabel(): string
    {
        return $this->kind() === 'task' ? __('filament-tma::notifications.open_task') : __('filament-tma::notifications.open_ticket');
    }

    private function icon(): string
    {
        return match ($this->notice->event) {
            NoticeEvent::Message => 'heroicon-o-chat-bubble-left-right',
            NoticeEvent::Note => 'heroicon-o-pencil-square',
            NoticeEvent::Reminder, NoticeEvent::AutoCloseWarning => 'heroicon-o-bell-alert',
            NoticeEvent::Task => 'heroicon-o-clipboard-document-check',
            NoticeEvent::Recipient => 'heroicon-o-user-plus',
            NoticeEvent::Question => 'heroicon-o-question-mark-circle',
            NoticeEvent::AutoClosed => 'heroicon-o-lock-closed',
            default => 'heroicon-o-lifebuoy',
        };
    }
}
