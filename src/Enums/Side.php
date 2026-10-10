<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Les deux parties d'un ticket. Le demandeur est du côté de l'application source ; l'équipe qui traite la demande
 * est l'équipe locale de cette application (« Équipe EDM »), ou le Support (le maître). Pour le nom à afficher sur
 * un ticket donné, voir `Ticket::waitingOn()`.
 */
enum Side: string implements HasLabel
{
    case Requester = 'requester';
    case Handler = 'handler';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requester => __('Demandeur'),
            self::Handler => __('Équipe support'),
        };
    }
}
