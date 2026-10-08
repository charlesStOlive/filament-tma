<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

/** Le rythme d'un forfait : un volume par mois, ou par trimestre. */
enum AllowancePeriod: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => __('Mensuel'),
            self::Quarterly => __('Trimestriel'),
        };
    }

    /** La clé de la période qui contient ce jour : `2026-10` ou `2026-Q4`. */
    public function keyFor(\DateTimeInterface $day): string
    {
        $day = CarbonImmutable::instance($day);

        return match ($this) {
            self::Monthly => $day->format('Y-m'),
            self::Quarterly => $day->format('Y').'-Q'.$day->quarter,
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} premier et dernier jour de la période */
    public function bounds(string $key): array
    {
        if ($this === self::Quarterly) {
            [$year, $quarter] = explode('-Q', $key);
            $start = CarbonImmutable::create((int) $year, ((int) $quarter - 1) * 3 + 1, 1);

            return [$start, $start->addMonths(3)->subDay()];
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $key.'-01')->startOfDay();

        return [$start, $start->endOfMonth()->startOfDay()];
    }
}
