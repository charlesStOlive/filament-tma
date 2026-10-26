<?php

namespace CharlesStOlive\FilamentTma\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Ce dont une notification parle (`Support\Notifier`). Qui la reçoit, et comment, dépend des destinataires du ticket
 * ou de la tâche (`Models\Recipient`) et de ce qu’on attend de chacun (`NoticeTone`).
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
    case AutoCloseWarning = 'auto-close-warning';
    case AutoClosed = 'auto-closed';
    case Question = 'question';

    public function getLabel(): string
    {
        return match ($this) {
            self::Turn => __('filament-tma::enums.notice_event.ticket_waiting'),
            self::Progress => __('filament-tma::common.follow_up'),
            self::Message => __('filament-tma::common.new_message'),
            self::Note => __('filament-tma::enums.notice_event.new_note'),
            self::Task => __('filament-tma::enums.notice_event.project_task'),
            self::Recipient => __('filament-tma::enums.notice_event.recipient'),
            self::Stale => __('filament-tma::enums.notice_event.stale'),
            self::Reminder => __('filament-tma::common.reminder'),
            self::AutoCloseWarning => __('filament-tma::enums.notice_event.closing_warning'),
            self::AutoClosed => __('filament-tma::enums.notice_event.auto_close'),
            self::Question => __('filament-tma::common.support_question'),
        };
    }
}
