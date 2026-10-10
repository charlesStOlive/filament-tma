<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Une entrée de la chronologie.
 *
 * - Message : l'échange entre les deux parties, demandeur compris, partagé d'office. Ce qui a été fait (la commande
 *   `tma:log`, avec une saisie de temps) est aussi un message.
 * - Note interne : seule l'équipe de l'application où elle est écrite la lit, sauf si on la partage (`shared_at`) ;
 *   voir `filament-tma.notes`.
 *
 * Une réponse est une entrée qui a un parent ; elle a le type de son parent.
 */
enum EntryType: string implements HasColor, HasIcon, HasLabel
{
    case Comment = 'comment';
    case Note = 'note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comment => __('Message'),
            self::Note => __('Note interne'),
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

    /** Visible de l'autre partie sans action de partage. */
    public function isSharedByDefault(): bool
    {
        return $this !== self::Note;
    }
}
