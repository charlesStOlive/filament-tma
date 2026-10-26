<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le demandeur répond : la main revient au Support. La réponse s'accroche à la dernière question posée.
 * Tout ce qu'il écrit à son tour est sa réponse (addendum §3.2).
 */
class Answer extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'answer';
    }

    public function replyField(): ?string
    {
        return 'answer';
    }

    public function label(): string
    {
        return __('filament-tma::common.reply');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-chat-bubble-left-ellipsis';
    }

    public function from(): array
    {
        return [AwaitingRequester::class];
    }

    public function to(Ticket $ticket): string
    {
        return InProgress::class;
    }

    public function sides(): array
    {
        return [Side::Requester];
    }

    public function rules(Ticket $ticket): array
    {
        return ['answer' => $this->messageRules(), ...$this->fileRules()];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('answer', __('filament-tma::workflow.ticket.answer.message'), help: ['tma.tickets.question-reponse', __('filament-tma::workflow.ticket.answer.help')])];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $question = $ticket->entries()
            ->whereNull('parent_id')
            ->where('meta->question', true)
            ->latest('id')
            ->first();

        $ticket->waiting_since = null;
        $this->writeComment($ticket, $data['answer'], $context, ['answer' => true], $question, $data['files'] ?? []);
    }
}
