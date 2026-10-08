<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/** D'où vient une écriture : l'interface, l'API de l'autre application, une commande, le planificateur. */
enum Origin: string implements HasLabel
{
    case Ui = 'ui';
    case Api = 'api';
    case Command = 'command';
    case Scheduler = 'scheduler';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ui => __('Interface'),
            self::Api => __('API'),
            self::Command => __('Commande'),
            self::Scheduler => __('Planificateur'),
        };
    }
}
