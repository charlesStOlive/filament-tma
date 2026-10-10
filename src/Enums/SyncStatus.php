<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Où en est la copie d'un ticket chez l'autre partie. Un ticket local reste « local ». */
enum SyncStatus: string implements HasColor, HasLabel
{
    case Local = 'local';
    case Pending = 'pending';
    case Synced = 'synced';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => __('Non partagé'),
            self::Pending => __('En attente de synchronisation'),
            self::Synced => __('Synchronisé'),
            self::Error => __('Synchronisation en erreur'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Local => 'gray',
            self::Pending => 'warning',
            self::Synced => 'success',
            self::Error => 'danger',
        };
    }
}
