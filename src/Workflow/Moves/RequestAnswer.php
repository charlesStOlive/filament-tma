<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Concerns\AnswersTheRequest;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** Le Support pose une question, sur une demande nouvelle ou en cours : la main passe au demandeur jusqu'à sa réponse. */
class RequestAnswer extends Move
{
    use AnswersTheRequest;
    use WritesMessages;

    public function key(): string
    {
        return 'request-answer';
    }

    public function replyField(): ?string
    {
        return 'question';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.ticket.request_answer.label');
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
        return [Submitted::class, InProgress::class];
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
        return ['question' => $this->messageRules(), ...$this->fileRules()];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('question', __('filament-tma::workflow.ticket.request_answer.message'), help: ['tma.tickets.question-reponse', __('filament-tma::workflow.ticket.request_answer.help')])];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $this->answering($ticket, $context);
        $ticket->waiting_since = now();
        $this->writeComment($ticket, $data['question'], $context, ['question' => true], files: $data['files'] ?? []);
    }
}
