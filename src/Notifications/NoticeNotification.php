<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Models\Notice;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ce qu'un destinataire principal apprend tout de suite (`Support\Notifier`) : par courriel, dans la cloche de
 * l'application (les notifications en base de Filament), ou les deux, selon son choix (`Support\NoticeChannels`).
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
        $mail = (new MailMessage)
            ->subject("[{$data['ref']}] {$data['title']}")
            ->greeting(__('Bonjour,'))
            ->line($data['line']);

        if (filled($data['excerpt'] ?? null)) {
            $mail->line('« '.$data['excerpt'].' »');
        }

        $mail->line(__('État : :state.', ['state' => $data['state']]));

        if ($url = $this->notice->url()) {
            $mail->action($this->openLabel(), $url);
        }

        return $mail->salutation(__('Vous recevez ce courriel parce que vous êtes destinataire principal de ce ticket.'));
    }

    /** La cloche de Filament : la même chose, et un lien qui marque la notification comme lue. */
    public function toDatabase(object $notifiable): array
    {
        $data = $this->notice->data;
        $notification = FilamentNotification::make()
            ->title("[{$data['ref']}] {$data['title']}")
            ->body($data['line'].(filled($data['excerpt'] ?? null) ? ' « '.$data['excerpt'].' »' : ''))
            ->icon($this->icon())
            ->iconColor($this->notice->event === NoticeEvent::Reminder ? 'warning' : 'primary');

        if ($url = $this->notice->url()) {
            $notification->actions([Action::make('open')->label($this->openLabel())->url($url)->markAsRead()]);
        }

        return $notification->getDatabaseMessage();
    }

    private function openLabel(): string
    {
        return ($this->notice->data['kind'] ?? 'ticket') === 'task' ? __('Ouvrir la tâche') : __('Ouvrir le ticket');
    }

    private function icon(): string
    {
        return match ($this->notice->event) {
            NoticeEvent::Message => 'heroicon-o-chat-bubble-left-right',
            NoticeEvent::Note => 'heroicon-o-pencil-square',
            NoticeEvent::Reminder => 'heroicon-o-bell-alert',
            NoticeEvent::Task => 'heroicon-o-clipboard-document-check',
            NoticeEvent::Recipient => 'heroicon-o-user-plus',
            default => 'heroicon-o-lifebuoy',
        };
    }
}
