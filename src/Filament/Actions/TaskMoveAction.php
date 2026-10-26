<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Le bouton d'un mouvement de tâche projet ; `TaskWorkflow::run()` contrôle tout de nouveau côté serveur. */
final class TaskMoveAction
{
    public static function make(TaskMove $move): Action
    {
        $action = Action::make('task_'.str_replace('-', '_', $move->key()))
            ->label(fn (?ProjectTask $record): string => $record ? $move->labelFor($record) : $move->label())
            ->color($move->color())
            ->icon($move->icon())
            ->tooltip($move->description())
            ->modalHeading(fn (?ProjectTask $record): string => $record ? $move->labelFor($record) : $move->label())
            ->modalDescription($move->description())
            ->schema(fn (ProjectTask $record): array => $move->formSchema($record))
            ->visible(fn (ProjectTask $record): bool => app(TaskWorkflow::class)->allows($record, $move, Context::current()))
            ->action(function (ProjectTask $record, array $data, Action $action) use ($move): void {
                try {
                    app(TaskWorkflow::class)->run($record, $move, $data, Context::current());
                } catch (AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('filament-tma::workflow.move_heading.task', ['number' => $record->number, 'state' => $record->refresh()->state->getLabel()]))->send();
            });

        if ($move->requiresConfirmation()) {
            $action->requiresConfirmation();
        }

        return $action;
    }

    /** @return array<int, Action> */
    public static function all(): array
    {
        return array_values(array_map(fn (TaskMove $move): Action => self::make($move), app(TaskWorkflow::class)->userMoves()));
    }
}
