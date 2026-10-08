<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** Le travail est fait : le demandeur valide ou rouvre. */
class SubmitForValidation extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'submit-for-validation';
    }

    public function label(): string
    {
        return __('Soumettre à validation');
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-check-badge';
    }

    public function from(): array
    {
        return [InProgress::class];
    }

    public function to(Ticket $ticket): string
    {
        return PendingValidation::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return ['message' => $this->messageRules(false)];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('message', __('Ce qui a été fait'), false)];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->validation_requested_at = now();
        $this->writeComment($ticket, $data['message'] ?? null, $context);
    }
}
