<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** Le Support ne prend pas une demande nouvelle en charge, et dit pourquoi : elle finit annulée, avec ce motif. */
class Refuse extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'refuse';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.ticket.refuse.label');
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-no-symbol';
    }

    public function from(): array
    {
        return [Submitted::class];
    }

    public function to(Ticket $ticket): string
    {
        return Cancelled::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->reasonField()];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->cancelled_at = now();
    }
}
