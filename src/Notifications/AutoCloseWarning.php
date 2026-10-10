<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Avertit le demandeur qu'un ticket qui attend sa réponse sera bientôt clos (`tma:close-stale`). */
class AutoCloseWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public \DateTimeInterface $closesOn) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $waitingFor = $this->ticket->state->getLabel();

        return (new MailMessage)
            ->subject(__('[:number] Sans réponse, ce ticket sera clos le :date', [
                'number' => $this->ticket->number,
                'date' => $this->closesOn->format('d/m/Y'),
            ]))
            ->greeting(__('Bonjour,'))
            ->line(__('Le ticket « :title » (:number) attend votre retour : il est à l’état « :state ».', [
                'title' => $this->ticket->title,
                'number' => $this->ticket->number,
                'state' => $waitingFor,
            ]))
            ->line(__('Sans activité de votre part, il sera clos automatiquement le :date.', ['date' => $this->closesOn->format('d/m/Y')]))
            ->action(__('Ouvrir le ticket'), TicketUrl::view($this->ticket))
            ->line(__('Un commentaire suffit à le garder ouvert.'));
    }
}
