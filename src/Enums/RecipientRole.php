<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Un destinataire d'un ticket ou d'une tâche (`Models\Recipient`) : principal, prévenu tout de suite de ce qui s'y passe
 * (courriel ou cloche, selon son choix) ; secondaire, seulement dans son bilan.
 */
enum RecipientRole: string implements HasColor, HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => __('filament-tma::enums.recipient_role.main'),
            self::Secondary => __('filament-tma::enums.recipient_role.secondary'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Primary => 'primary',
            self::Secondary => 'gray',
        };
    }
}
