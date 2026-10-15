<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Une notification immédiate sur un passage de ticket (`notifications.immediate`). */
class TicketMoveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public StateTransition $transition) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->transition->actor?->name ?? __('Le Support');
        $line = match ($this->transition->move) {
            'request-answer' => __(':who attend votre réponse.', ['who' => $who]),
            'submit-for-validation' => __(':who a résolu votre demande : confirmez pour la clore, ou renvoyez-la.', ['who' => $who]),
            'submit' => __(':who vous confie cette demande.', ['who' => $who]),
            'take-charge' => __(':who a pris votre demande en charge.', ['who' => $who]),
            'reopen' => __(':who vous renvoie la demande : le résultat ne convient pas encore.', ['who' => $who]),
            'answer' => __(':who a répondu.', ['who' => $who]),
            'publish', 'auto-publish' => __('Le Support a ouvert ce ticket pour vous.'),
            default => __(':who : :move.', ['who' => $who, 'move' => rescue(fn (): string => app(TicketWorkflow::class)->move($this->transition->move)->labelFor($this->ticket), $this->transition->move, false)]),
        };

        return (new MailMessage)
            ->subject("[{$this->ticket->number}] {$this->ticket->title}")
            ->greeting(__('Bonjour,'))
            ->line($line)
            ->line(__('État : :state.', ['state' => $this->ticket->state->getLabel()]))
            ->action(__('Ouvrir le ticket'), TicketUrl::view($this->ticket));
    }
}
