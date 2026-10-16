<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** Le demandeur confirme que le travail lui convient, avec un mot s'il veut : le ticket est clos. */
class Validate extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'validate';
    }

    public function replyField(): ?string
    {
        return 'message';
    }

    public function label(): string
    {
        return __('Confirmer et clore');
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-check-circle';
    }

    public function from(): array
    {
        return [PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return Closed::class;
    }

    public function sides(): array
    {
        return [Side::Requester];
    }

    public function rules(Ticket $ticket): array
    {
        return ['message' => $this->messageRules(required: false), ...$this->fileRules()];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('message', __('Un mot pour le Support (facultatif)'), required: false)];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->closed_at = now();
        $this->writeComment($ticket, $data['message'] ?? null, $context, files: $data['files'] ?? []);
    }
}
