<?php

namespace CharlesStOlive\FilamentTma\Enums;

use CharlesStOlive\FilamentTma\Support\Terms;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Qui traite le ticket : l'équipe locale de l'application source (« Équipe EDM », voir `Support\Terms`), ou le
 * Support (le maître).
 */
enum HandlingMode: string implements HasColor, HasLabel
{
    case Local = 'local';
    case ServiceDesk = 'service_desk';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => Terms::team(),
            self::ServiceDesk => Terms::support(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Local => 'gray',
            self::ServiceDesk => 'info',
        };
    }
}
