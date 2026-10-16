<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/** Comment une notification part : tout de suite (destinataire principal), ou dans le prochain bilan (secondaire). */
enum NoticeMode: string implements HasLabel
{
    case Immediate = 'immediate';
    case Grouped = 'grouped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Immediate => __('Tout de suite'),
            self::Grouped => __('Dans le bilan'),
        };
    }
}
