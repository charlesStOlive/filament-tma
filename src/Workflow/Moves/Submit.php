<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use Filament\Forms\Components\Select;

/** Soumettre un brouillon à un collègue de la même application : le traitement local. */
class Submit extends Move
{
    public function key(): string
    {
        return 'submit';
    }

    public function label(): string
    {
        return __('Soumettre');
    }

    public function description(): ?string
    {
        return __('Confier la demande à un intervenant de cette application.');
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

        return ['assignee_user_id' => ['nullable', 'exists:'.(new $users)->getTable().','.(new $users)->getKeyName()]];
    }

    public function formSchema(Ticket $ticket): array
    {
        $users = Tma::userModel();

        return [
            Select::make('assignee_user_id')
                ->label(__('Intervenant'))
                ->helperText(__('Facultatif : sans intervenant désigné, le premier qui le prend en charge le devient.'))
                ->options(fn (): array => $users::query()->orderBy('name')->pluck('name', (new $users)->getKeyName())->all())
                ->searchable()
                ->hintAction(Help::action('tma.tickets.soumettre', __('Et sans intervenant ?'))),
        ];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->handling_mode = HandlingMode::Local;
        $ticket->submitted_at = now();

        if (filled($data['assignee_user_id'] ?? null)) {
            $users = Tma::userModel();
            $ticket->assignee_actor_id = Actor::forUser($users::findOrFail($data['assignee_user_id']))->getKey();
        }
    }
}
