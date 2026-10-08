<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Qui traite le ticket : un collègue de l'application source, ou le centre de service (le maître). */
enum HandlingMode: string implements HasColor, HasLabel
{
    case Local = 'local';
    case ServiceDesk = 'service_desk';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => __('Traitement local'),
            self::ServiceDesk => __('Centre de service'),
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
