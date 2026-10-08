<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Notifications\AllowanceThresholdNotification;
use Illuminate\Support\Facades\Notification;

/** La réaction par défaut à un seuil : un courriel aux adresses configurées. L'application peut en ajouter d'autres. */
class NotifyAllowanceThreshold
{
    public function handle(AllowanceThresholdReached $event): void
    {
        foreach ((array) config('filament-tma.allowances.notify', []) as $address) {
            Notification::route('mail', trim($address))->notify(new AllowanceThresholdNotification($event));
        }
    }
}
