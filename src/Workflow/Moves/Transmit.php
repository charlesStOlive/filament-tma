<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\Recipients;

/**
 * « Transmettre au Support » (CDC §7.4) : le ticket part au maître, qui en devient l'autorité. Le demandeur le fait
 * d'un brouillon ; l'équipe locale, d'un ticket qu'elle traite (elle escalade). Le ticket repart en « Nouveau ».
 *
 * L'appel au maître est fait dans la transaction : s'il échoue, le ticket reste tel qu'il était, et l'utilisateur
 * voit l'erreur (décision Q4).
 */
class Transmit extends Move
{
    public function key(): string
    {
        return 'transmit';
    }

    public function label(): string
    {
        return __('Transmettre au Support');
    }

    public function description(): ?string
    {
        return __('Le Support prend la suite : il fera autorité sur ce ticket.');
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-arrow-up-on-square';
    }

    public function from(): array
    {
        return [Draft::class, Submitted::class, InProgress::class, AwaitingRequester::class];
    }

    public function to(Ticket $ticket): string
    {
        return Submitted::class;
    }

    public function sides(): array
    {
        return [Side::Requester, Side::Handler];
    }

    public function allowsSide(Side $side, Ticket $ticket): bool
    {
        return match ($side) {
            Side::Requester => $ticket->state instanceof Draft,
            Side::Handler => ! $ticket->state instanceof Draft,
        };
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return Tma::serviceDeskAvailable()
            && $ticket->handling_mode === HandlingMode::Local
            && Tma::isHere($ticket->source_instance_uid);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    /**
     * Qui transmet et le Support (le compte passerelle, que le maître traduit en ses destinataires) deviennent
     * principaux ; les internes, demandeur compris, cessent d'en être destinataires : un correspondant répond pour eux.
     */
    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        app(Recipients::class)->removeInternals($ticket);
        app(Recipients::class)->add($ticket, $context->actor);

        if ($gateway = Addressables::gateway()) {
            app(Recipients::class)->add($ticket, Actor::forUser($gateway));
        }

        $ticket->handling_mode = HandlingMode::ServiceDesk;
        $ticket->submitted_at = now();
        $ticket->sync_status = SyncStatus::Pending;
    }

    public function committing(Ticket $ticket, StateTransition $transition, Context $context): void
    {
        $snapshot = app(MasterClient::class)->transmit($ticket, $transition, $context);

        Mirror::snapshot($snapshot);
        $ticket->refresh();
    }
}
