<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Ce dont une notification parle (`Support\Notifier`). Qui la reçoit, et comment, dépend des destinataires du ticket
 * ou de la tâche (`Models\Recipient`), pas de la sorte : un principal tout de suite, un secondaire dans son bilan.
 */
enum NoticeEvent: string implements HasLabel
{
    case Turn = 'turn';
    case Progress = 'progress';
    case Message = 'message';
    case Note = 'note';
    case Task = 'task';
    case Recipient = 'recipient';
    case Stale = 'stale';
    case Reminder = 'reminder';
    case AutoClosed = 'auto-closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Turn => __('Un ticket vous attend'),
            self::Progress => __('Suivi'),
            self::Message => __('Nouveau message'),
            self::Note => __('Nouvelle note'),
            self::Task => __('Tâche projet'),
            self::Recipient => __('Vous êtes destinataire'),
            self::Stale => __('Rappel : rien ne bouge'),
            self::Reminder => __('Relance'),
            self::AutoClosed => __('Clôture automatique'),
        };
    }
}
