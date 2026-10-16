<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Forçage (addendum §3.4) : le Support reprend la main quand c'est le tour du demandeur, sans attendre sa réponse ou
 * sa confirmation. Dans le circuit normal, il ne le peut pas ; seul ce droit l'ouvre (`tma.ticket.force-return`).
 */
class ForceReturn extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'force-return';
    }

    public function label(): string
    {
        return __('Forcer le retour');
    }

    public function description(): ?string
    {
        return __('Hors du tour : la main revient au Support sans attendre le demandeur.');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-arrow-uturn-left';
    }

    public function from(): array
    {
        return [AwaitingRequester::class, PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return InProgress::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function override(): ?string
    {
        return TmaGate::FORCE_RETURN;
    }

    public function rules(Ticket $ticket): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->reasonField(false, help: ['tma.tickets.forcages', __('Quand forcer le retour ?')])];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->waiting_since = null;
        $ticket->validation_requested_at = null;
    }
}
