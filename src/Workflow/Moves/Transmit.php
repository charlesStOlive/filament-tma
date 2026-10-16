<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;

/**
 * « Transmettre au Support » (CDC §7.4) : le demandeur envoie son brouillon au maître, qui en devient l'autorité. Le
 * ticket est « Nouveau », et suivi d'emblée par le **contact au Support** que le demandeur choisit parmi les membres du
 * Support reliés à l'application — d'office s'il n'y en a qu'un ; sans membre relié, le Support le choisit. Il n'y a
 * pas de « prise en charge » : le Support répond tout de suite (addendum §3.1).
 *
 * L'appel au maître est fait dans la transaction : s'il échoue, le ticket reste tel qu'il était, et l'utilisateur
 * voit l'erreur (décision Q4).
 */
class Transmit extends Move
{
    public function key(): string
    {
        return 'transmit';
    }

    public function label(): string
    {
        return __('Transmettre au Support');
    }

    public function description(): ?string
    {
        return __('Le Support prend la suite : il fera autorité sur ce ticket.');
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-arrow-up-on-square';
    }

    public function from(): array
    {
        return [Draft::class];
    }

    public function to(Ticket $ticket): string
    {
        return Submitted::class;
    }

    public function sides(): array
    {
        return [Side::Requester];
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return Tma::isClient() && Tma::serviceDeskAvailable()
            && Tma::isHere($ticket->source_instance_uid) && Tma::isHere($ticket->created_on_instance_uid);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function rules(Ticket $ticket): array
    {
        // L'id dans le maître : la liste le rend en nombre ou en texte.
        return ['contact' => ['nullable', Rule::in(array_map('strval', array_keys(Addressables::supportContacts())))]];
    }

    /** Le contact au Support, à choisir s'il y a plusieurs membres reliés ; un seul, il l'est d'office. */
    public function formSchema(Ticket $ticket): array
    {
        $contacts = Addressables::supportContacts();

        return count($contacts) < 2 ? [] : [
            Select::make('contact')
                ->label(__('Contact au Support'))
                ->helperText(__('La personne du Support qui suivra votre demande.'))
                ->options($contacts)
                ->required(),
        ];
    }

    /**
     * Qui transmet, le Support (le compte passerelle, que le maître traduit en ses destinataires) et le contact au
     * Support deviennent principaux ; le contact suit la demande.
     */
    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        app(Recipients::class)->add($ticket, $context->actor);

        if ($contact = Addressables::supportContact(isset($data['contact']) ? (string) $data['contact'] : null)) {
            $ticket->assignee_actor_id = $contact->getKey();
            app(Recipients::class)->add($ticket, $contact);
        }

        if ($gateway = Addressables::gateway()) {
            app(Recipients::class)->add($ticket, Actor::forUser($gateway));
        }

        $ticket->submitted_at = now();
        $ticket->sync_status = SyncStatus::Pending;
    }

    public function committing(Ticket $ticket, StateTransition $transition, Context $context): void
    {
        $snapshot = app(MasterClient::class)->transmit($ticket, $transition, $context);

        Mirror::snapshot($snapshot);
        $ticket->refresh();
    }
}
