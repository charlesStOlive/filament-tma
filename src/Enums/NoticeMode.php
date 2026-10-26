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
            self::Immediate => __('filament-tma::enums.notice_mode.immediate'),
            self::Grouped => __('filament-tma::enums.notice_mode.digest'),
            self::Skipped => __('filament-tma::enums.notice_mode.none'),
        };
    }
}
