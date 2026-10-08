<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Allowance;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un forfait vient de franchir un seuil sur une période (CDC §11.3) : 80 %, 90 %… 100 % veut dire « entièrement
 * consommé », au-delà « dépassé » (`isExhausted()`, `isExceeded()`). Une fois par seuil et par période.
 *
 * @param  array<string, mixed>  $status  `Support\AllowanceMeter::status()`
 */
class AllowanceThresholdReached
{
    use Dispatchable;

    public function __construct(public Allowance $allowance, public string $periodKey, public int $threshold, public array $status) {}

    public function isExhausted(): bool
    {
        return $this->threshold === 100;
    }

    public function isExceeded(): bool
    {
        return $this->threshold > 100;
    }
}
