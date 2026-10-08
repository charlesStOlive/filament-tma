<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Une entrée de la chronologie.
 *
 * - Commentaire : l'échange entre les deux parties, partagé d'office.
 * - Note : reste dans l'application où elle est écrite, sauf si on la partage (`shared_at`).
 * - Résumé d'intervention : ce qui a été fait (souvent écrit par la commande `tma:log`), partagé.
 *
 * Une réponse est une entrée qui a un parent ; elle a le type de son parent.
 */
enum EntryType: string implements HasColor, HasIcon, HasLabel
{
    case Comment = 'comment';
    case Note = 'note';
    case Summary = 'summary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comment => __('Commentaire'),
            self::Note => __('Note'),
            self::Summary => __('Résumé d’intervention'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Comment => 'primary',
            self::Note => 'warning',
            self::Summary => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Comment => 'heroicon-m-chat-bubble-left-right',
            self::Note => 'heroicon-m-pencil-square',
            self::Summary => 'heroicon-m-clipboard-document-check',
        };
    }

    /** Visible de l'autre partie sans action de partage. */
    public function isSharedByDefault(): bool
    {
        return $this !== self::Note;
    }
}
