<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Le bouton d'un mouvement du workflow. Il s'affiche quand `TicketWorkflow::allows()` le permet, et l'exécution
 * repasse par `TicketWorkflow::run()`, qui contrôle tout de nouveau côté serveur.
 */
final class MoveAction
{
    public static function name(Move $move): string
    {
        return 'move_'.str_replace('-', '_', $move->key());
    }

    public static function make(Move $move): Action
    {
        $action = Action::make(self::name($move))
            ->label(fn (?Ticket $record): string => $record ? $move->labelFor($record) : $move->label())
            ->color($move->color())
            ->icon($move->icon())
            ->tooltip($move->description())
            ->modalHeading(fn (?Ticket $record): string => $record ? $move->labelFor($record) : $move->label())
            ->modalDescription($move->description())
            ->schema(fn (Ticket $record): array => $move->formSchema($record))
            ->visible(fn (Ticket $record): bool => app(TicketWorkflow::class)->allows($record, $move, Context::current()))
            ->action(function (Ticket $record, array $data, Action $action) use ($move): void {
                try {
                    app(TicketWorkflow::class)->run($record, $move, $data, Context::current());
                } catch (AuthorizationException|ValidationException|SyncException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('filament-tma::workflow.move_heading.ticket', [
                    'number' => $record->number,
                    'state' => $record->refresh()->state->getLabel(),
                ]))->send();
            });

        if ($move->requiresConfirmation()) {
            $action->requiresConfirmation();
        }

        return $action;
    }

    /**
     * Les mouvements d'un utilisateur qui sont des boutons d'en-tête ; les réponses (`Move::replyField()`) se font
     * dans la zone de réponse, sous la chronologie (`Filament\Concerns\HasComposer`).
     *
     * @return array<int, Action>
     */
    public static function all(): array
    {
        $moves = array_filter(app(TicketWorkflow::class)->userMoves(), fn (Move $move): bool => $move->replyField() === null);

        return array_values(array_map(fn (Move $move): Action => self::make($move), $moves));
    }
}
