<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Les deux parties d'un ticket. Le demandeur est du côté de l'application source ; l'intervenant est un collègue
 * de cette application (traitement local) ou le centre de service (le maître).
 */
enum Side: string implements HasLabel
{
    case Requester = 'requester';
    case Handler = 'handler';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requester => __('Demandeur'),
            self::Handler => __('Intervenant'),
        };
    }
}
