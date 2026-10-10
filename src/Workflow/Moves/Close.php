<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** L'équipe qui traite la demande la clôt sans attendre le demandeur : pas de réponse, ou pas de confirmation. */
class Close extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'close';
    }

    public function label(): string
    {
        return __('Clore sans réponse');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-lock-closed';
    }

    public function from(): array
    {
        return [AwaitingRequester::class, PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return Closed::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->reasonField(false)];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->closed_at = now();
    }
}
