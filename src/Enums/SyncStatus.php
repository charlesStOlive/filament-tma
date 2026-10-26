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
            self::Local => __('filament-tma::enums.sync_status.local'),
            self::Pending => __('filament-tma::enums.sync_status.pending'),
            self::Synced => __('filament-tma::enums.sync_status.synced'),
            self::Error => __('filament-tma::enums.sync_status.failed'),
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
