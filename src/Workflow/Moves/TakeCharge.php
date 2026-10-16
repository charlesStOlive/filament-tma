<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsFields;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\Recipients;

/** Quelqu'un de l'équipe qui traite les demandes prend celle-ci : il en devient le responsable (« Pris en charge par »). */
class TakeCharge extends Move
{
    public function key(): string
    {
        return 'take-charge';
    }

    public function label(): string
    {
        return __('Prendre en charge');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-hand-raised';
    }

    public function from(): array
    {
        return [Submitted::class];
    }

    public function to(Ticket $ticket): string
    {
        return InProgress::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return ['recipients' => ['nullable', 'array']];
    }

    /**
     * L'équipe qui reçoit confirme ou change ses destinataires principaux : par défaut, qui prend en charge. Seulement là
     * où le ticket se traite (un membre du Support dans une application cliente agit chez le maître).
     */
    public function formSchema(Ticket $ticket): array
    {
        if (! $ticket->authorityIsHere()) {
            return [];
        }

        $options = fn (): array => Addressables::handlers($ticket);

        return [
            RecipientsFields::primaries('recipients', $options)
                ->label(__('Destinataires principaux de votre côté'))
                ->helperText(__('Prévenus tout de suite de tout ce qui arrive au ticket.'))
                // Qui prend en charge, et les principaux déjà nommés dans son équipe.
                ->default(fn (): array => array_values(array_intersect(
                    array_unique([
                        ...(auth()->user() !== null ? ['u:'.auth()->user()->getAuthIdentifier()] : []),
                        ...app(Recipients::class)->keys($ticket)['primary_recipients'],
                    ]),
                    array_keys(array_merge(...array_values($options()))),
                )))
                ->required(),
        ];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->taken_at = now();
        $ticket->assignee_actor_id = $context->actor?->getKey() ?? $ticket->assignee_actor_id;
        app(Recipients::class)->add($ticket, $context->actor);

        foreach ((array) ($data['recipients'] ?? []) as $key) {
            app(Recipients::class)->add($ticket, Addressables::resolve((string) $key, $ticket->clientInstanceUid()));
        }
    }
}
