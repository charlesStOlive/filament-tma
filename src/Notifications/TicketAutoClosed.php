<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Le ticket a été clôturé faute d'activité du demandeur. */
class TicketAutoClosed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('[:number] Ticket clôturé sans réponse', ['number' => $this->ticket->number]))
            ->greeting(__('Bonjour,'))
            ->line(__('Le ticket « :title » (:number) attendait votre retour depuis :days jours. Il vient d’être clôturé automatiquement.', [
                'title' => $this->ticket->title,
                'number' => $this->ticket->number,
                'days' => config('filament-tma.auto_close.after_days'),
            ]))
            ->action(__('Voir le ticket'), TicketUrl::view($this->ticket))
            ->line(__('Si le besoin existe toujours, créez une nouvelle demande en citant ce numéro.'));
    }
}
