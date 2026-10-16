<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsFields;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Forms\Components\Select;

/** Confier un brouillon à l'équipe locale (« Confier à l'équipe EDM »), qui le traite sans le Support. */
class Submit extends Move
{
    public function key(): string
    {
        return 'submit';
    }

    public function label(): string
    {
        return Terms::submit();
    }

    public function labelFor(Ticket $ticket): string
    {
        return Terms::submit($ticket->source_instance_uid);
    }

    public function description(): ?string
    {
        return __('L’équipe de cette application traite la demande, sans le Support.');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-paper-airplane';
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
        return Tma::isHere($ticket->source_instance_uid) && Tma::isHere($ticket->created_on_instance_uid);
    }

    public function rules(Ticket $ticket): array
    {
        $users = Tma::userModel();

        return [
            'assignee_user_id' => ['nullable', 'exists:'.(new $users)->getTable().','.(new $users)->getKeyName()],
            'recipients' => ['nullable', 'array'],
        ];
    }

    public function formSchema(Ticket $ticket): array
    {
        $users = Tma::userModel();

        return [
            Select::make('assignee_user_id')
                ->label(__('À confier à'))
                ->helperText(__('Facultatif : sans personne désignée, le premier qui la prend en charge s’en occupe.'))
                ->options(fn (): array => $users::query()->orderBy('name')->pluck('name', (new $users)->getKeyName())->all())
                ->searchable()
                ->live()
                ->hintAction(Help::action('tma.tickets.soumettre', __('Et sans personne désignée ?'))),
            // L'équipe qui reçoit a au moins un destinataire principal : la personne désignée, ou celles choisies ici.
            RecipientsFields::primaries('recipients', fn (): array => Addressables::handlers($ticket))
                ->label(__('Destinataires principaux dans l’équipe'))
                ->helperText(__('Prévenus tout de suite. Au moins une personne, sauf si la demande est confiée à quelqu’un.'))
                ->requiredWithout('assignee_user_id')
                ->validationMessages(['required_without' => __('Choisissez au moins un destinataire principal dans l’équipe, ou « Tout le monde ».')]),
        ];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->handling_mode = HandlingMode::Local;
        $ticket->submitted_at = now();

        if (filled($data['assignee_user_id'] ?? null)) {
            $users = Tma::userModel();
            $ticket->assignee_actor_id = Actor::forUser($users::findOrFail($data['assignee_user_id']))->getKey();
            app(Recipients::class)->add($ticket, Actor::query()->find($ticket->assignee_actor_id));
        }

        // L'écran exige un choix ; sans écran (API, commande, exemples) et sans personne désignée, toute l'équipe.
        $keys = (array) ($data['recipients'] ?? []);

        if ($keys === [] && blank($data['assignee_user_id'] ?? null)) {
            $keys = array_keys(array_merge(...array_values(Addressables::handlers($ticket))));
        }

        foreach ($keys as $key) {
            app(Recipients::class)->add($ticket, Addressables::resolve((string) $key, null));
        }
    }
}
