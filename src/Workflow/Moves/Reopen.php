<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le demandeur n'est pas satisfait : il dit pourquoi, et la main revient à l'équipe qui traite la demande
 * (« Retour au Support », « Retour à l'équipe EDM »).
 */
class Reopen extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'reopen';
    }

    public function replyField(): ?string
    {
        return 'message';
    }

    public function label(): string
    {
        return __('Retour à l’équipe qui traite');
    }

    public function labelFor(Ticket $ticket): string
    {
        $client = Terms::client($ticket->source_instance_uid);

        return match (true) {
            $ticket->handling_mode !== HandlingMode::Local => __('Retour au Support'),
            $client === null => __('Retour à l’équipe locale'),
            default => __('Retour à l’équipe :client', ['client' => $client]),
        };
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-arrow-uturn-left';
    }

    public function from(): array
    {
        return [PendingValidation::class];
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
        return ['message' => $this->messageRules(), ...$this->fileRules()];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->messageField('message', __('Ce qui ne va pas'), help: ['tma.tickets.validation', __('Confirmer ou renvoyer ?')])];
    }

    public function reason(array $data): ?string
    {
        return null;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->validation_requested_at = null;
        $this->writeComment($ticket, $data['message'], $context, ['reopen' => true], files: $data['files'] ?? []);
    }
}
