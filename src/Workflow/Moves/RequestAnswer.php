<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** L'équipe qui traite la demande pose une question : la main passe au demandeur jusqu'à sa réponse. */
class RequestAnswer extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'request-answer';
    }

    public function label(): string
    {
        return __('Demander des précisions');
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-question-mark-circle';
    }

    public function from(): array
    {
        return [InProgress::class];
    }

    public function to(Ticket $ticket): string
    {
        return AwaitingRequester::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return ['question' => $this->messageRules()];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('question', __('Question'), help: ['tma.tickets.question-reponse', __('Que devient le ticket ?')])];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->waiting_since = now();
        $this->writeComment($ticket, $data['question'], $context, ['question' => true]);
    }
}
