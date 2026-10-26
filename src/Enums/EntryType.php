<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Une entrée de la chronologie, lue par tout le monde sur le ticket, des deux côtés.
 *
 * - Message : l'échange entre les deux parties, demandeur compris. Ce qui a été fait (la commande `tma:log`, avec
 *   une saisie de temps) est aussi un message. Une réponse est un message qui a un parent (un seul niveau).
 * - Note : un commentaire posé à côté de l'échange, sur un message (son parent) ou sur le ticket entier. Numérotée,
 *   elle s'archive quand on n'a plus besoin de la lire ; voir `Models\Entry`.
 */
enum EntryType: string implements HasColor, HasIcon, HasLabel
{
    case Comment = 'comment';
    case Note = 'note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comment => __('filament-tma::common.message'),
            self::Note => __('filament-tma::enums.entry_type.note'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Comment => 'primary',
            self::Note => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Comment => 'heroicon-m-chat-bubble-left-right',
            self::Note => 'heroicon-m-pencil-square',
        };
    }
}
