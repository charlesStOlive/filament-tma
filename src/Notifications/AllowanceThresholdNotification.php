<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Un forfait franchit un seuil : le courriel aux adresses de `allowances.notify`. */
class AllowanceThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AllowanceThresholdReached $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->event->status;
        $what = match (true) {
            $this->event->isExceeded() => __('dépassé (:threshold %)', ['threshold' => $this->event->threshold]),
            $this->event->isExhausted() => __('entièrement consommé'),
            default => __('consommé à :threshold %', ['threshold' => $this->event->threshold]),
        };

        return (new MailMessage)
            ->subject(__('Forfait :label : :what', ['label' => $s['label'], 'what' => $what]))
            ->greeting(__('Bonjour,'))
            ->line(__('Le forfait « :label » est :what sur la période :period.', ['label' => $s['label'], 'what' => $what, 'period' => $s['period_key']]))
            ->line(__('Alloué : :allocated · consommé : :consumed · restant : :remaining:overage.', [
                'allocated' => Duration::format($s['allocated_minutes']),
                'consumed' => Duration::format($s['consumed_minutes']),
                'remaining' => Duration::format($s['remaining_minutes']),
                'overage' => $s['overage_minutes'] > 0 ? ' · '.__('dépassement : :o', ['o' => Duration::format($s['overage_minutes'])]) : '',
            ]));
    }
}
