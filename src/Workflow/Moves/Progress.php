<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Concerns\AnswersTheRequest;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * « En cours » : le Support travaille sur la demande et le dit, sans rendre la main au demandeur (addendum §3.2).
 *
 * Depuis « Nouveau », il passe le ticket En cours, avec un message facultatif. En cours, il ajoute un message, l'état
 * ne change pas (comme « Prendre la main »). Le demandeur, lui, n'écrit pas de message pendant ce temps (ce n'est pas
 * son tour) : il ajoute des notes. Le dernier message du Support reste donc le dernier du ticket, et son auteur peut
 * le modifier (`Entry::isStillEditable()`). Un commit peut porter le message (`Support\Commit`).
 */
class Progress extends Move
{
    use AnswersTheRequest;
    use WritesMessages;

    public function key(): string
    {
        return 'progress';
    }

    public function replyField(): ?string
    {
        return 'message';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.ticket.progress.label');
    }

    public function description(): ?string
    {
        return __('filament-tma::workflow.ticket.progress.description');
    }

    public function color(): string
    {
        return 'primary';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-wrench-screwdriver';
    }

    public function from(): array
    {
        return [Submitted::class, InProgress::class];
    }

    public function to(Ticket $ticket): string
    {
        return InProgress::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    /** Le message est facultatif pour passer en cours ; une fois en cours, le passage n'est que lui. */
    public function rules(Ticket $ticket): array
    {
        return [
            'message' => $this->messageRules(required: $ticket->state instanceof InProgress),
            ...$this->commitRules(),
            ...$this->fileRules(),
        ];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [
            $this->messageField('message', __('filament-tma::workflow.ticket.progress.message'), $ticket->state instanceof InProgress, ['tma.tickets.en-cours', __('filament-tma::workflow.ticket.progress.help')]),
            $this->commitField(),
        ];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $commit = $this->commitMeta($data, 'message');
        $this->answering($ticket, $context);
        $this->writeComment($ticket, $data['message'] ?? null, $context, ['progress' => true, ...$commit], files: $data['files'] ?? []);
    }
}
