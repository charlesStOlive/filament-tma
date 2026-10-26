<?php

namespace CharlesStOlive\FilamentTma\Enums;

use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Comment un ticket s'est ouvert, et donc où il en est à sa publication :
 *
 * - `Request` : un correspondant fait sa demande, dans son application (le cas ordinaire) ;
 * - `OnBehalf` : le Support l'écrit **au nom de** quelqu'un du client (un courriel reçu, recopié) : c'est sa demande,
 *   au Support d'y répondre ;
 * - `Outreach` : le Support **s'adresse au client** (une idée, une question, une proposition) et attend sa réponse ;
 * - `Notice` : le Support **informe** le client, sans attendre de réponse : le ticket est clos dès sa publication.
 *
 * Dans les deux derniers cas, la personne du client n'est pas un demandeur : elle est « Adressé à ».
 */
enum TicketOpening: string implements HasDescription, HasIcon, HasLabel
{
    case Request = 'request';
    case OnBehalf = 'on_behalf';
    case Outreach = 'outreach';
    case Notice = 'notice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Request => __('filament-tma::enums.ticket_opening.request.label'),
            self::OnBehalf => __('filament-tma::enums.ticket_opening.on_behalf.label'),
            self::Outreach => __('filament-tma::enums.ticket_opening.addressed.label'),
            self::Notice => __('filament-tma::enums.ticket_opening.notice.label'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Request => __('filament-tma::enums.ticket_opening.request.description'),
            self::OnBehalf => __('filament-tma::enums.ticket_opening.on_behalf.description'),
            self::Outreach => __('filament-tma::enums.ticket_opening.addressed.description'),
            self::Notice => __('filament-tma::enums.ticket_opening.notice.description'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Request => 'heroicon-m-inbox-arrow-down',
            self::OnBehalf => 'heroicon-m-user',
            self::Outreach => 'heroicon-m-chat-bubble-left-right',
            self::Notice => 'heroicon-m-information-circle',
        };
    }

    /** Le Support écrit au client (il n'est pas demandeur). */
    public function fromSupport(): bool
    {
        return $this === self::Outreach || $this === self::Notice;
    }

    /** La personne du client sur le ticket : « Demandeur », ou « Adressé à » quand le Support lui écrit. */
    public function requesterLabel(): string
    {
        return $this->fromSupport() ? __('filament-tma::enums.ticket_opening.addressed_to') : __('filament-tma::common.requester');
    }

    /** L'en-tête de la demande, dans l'historique : « Demande », « Message du Support », « Information ». */
    public function badge(): string
    {
        return match ($this) {
            self::Request, self::OnBehalf => __('filament-tma::enums.ticket_opening.request.label'),
            self::Outreach => __('filament-tma::enums.ticket_opening.support_message'),
            self::Notice => __('filament-tma::enums.ticket_opening.information'),
        };
    }

    /** @return class-string<TicketState> l'état d'un ticket préparé par le Support, une fois publié */
    public function publishedState(): string
    {
        return match ($this) {
            self::Outreach => AwaitingRequester::class,
            self::Notice => Closed::class,
            default => Submitted::class,
        };
    }

    /** @return array<int, self> ce que le Support peut ouvrir */
    public static function forSupport(): array
    {
        return [self::OnBehalf, self::Outreach, self::Notice];
    }
}
