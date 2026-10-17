<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Comment une notification part : tout de suite (destinataire principal), dans le prochain bilan (secondaire), ou pas
 * du tout, pour qui ne reçoit pas le bilan (`Support\NoticeChannels::digest()`) : elle est seulement notée.
 */
enum NoticeMode: string implements HasLabel
{
    case Immediate = 'immediate';
    case Grouped = 'grouped';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Immediate => __('Tout de suite'),
            self::Grouped => __('Dans le bilan'),
            self::Skipped => __('Non envoyée (sans bilan)'),
        };
    }
}
