<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Supprimer des tâches, dans le maître seulement, quel que soit leur état (`TaskWorkflow::delete()`) : l'icône d'une
 * ligne de la liste, le bouton de la fiche, et la suppression groupée. La fenêtre prévient de tout ce qui part avec
 * la tâche : ses messages, ses notes, son temps saisi, et sa copie chez le client si elle est publiée.
 */
final class TaskDeleteActions
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (ProjectTask $record): bool => app(TaskWorkflow::class)->canDelete($record, Context::current()))
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading(fn (ProjectTask $record): string => __('filament-tma::tasks.delete.heading', ['number' => $record->number]))
            ->modalDescription(fn (ProjectTask $record): string => self::warning(collect([$record])))
            ->modalSubmitActionLabel(__('filament-tma::tasks.delete.submit'))
            ->successNotificationTitle(fn (ProjectTask $record): string => __('filament-tma::tasks.delete.done', ['number' => $record->number]))
            ->using(function (ProjectTask $record, DeleteAction $action): bool {
                try {
                    app(TaskWorkflow::class)->delete($record, Context::current());
                } catch (AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title(self::reason($e))->send();
                    $action->cancel();
                }

                return true;
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('deleteTasks')
            ->label(__('filament-tma::common.delete'))
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->visible(fn (): bool => Tma::isMaster())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading(fn (Collection $records): string => trans_choice('filament-tma::tasks.delete.bulk_heading', $records->count(), ['count' => $records->count()]))
            ->modalDescription(fn (Collection $records): string => self::warning($records))
            ->modalSubmitActionLabel(__('filament-tma::tasks.delete.submit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $workflow = app(TaskWorkflow::class);
                $refused = [];

                foreach ($records as $task) {
                    try {
                        $workflow->delete($task, Context::current());
                    } catch (AuthorizationException|ValidationException $e) {
                        $refused[] = self::reason($e);
                    }
                }

                $deleted = $records->count() - count($refused);

                Notification::make()
                    ->{$refused === [] ? 'success' : 'warning'}()
                    ->title(trans_choice('filament-tma::tasks.delete.bulk_done', $deleted, ['count' => $deleted]))
                    ->body($refused === [] ? null : implode("\n", $refused))
                    ->send();
            });
    }

    /**
     * Ce qui part avec les tâches : « Partent avec elle : 3 messages, 1 note et 4 h 30 de temps saisi. Elle est
     * publiée : elle disparaît aussi chez EDM. »
     *
     * @param  Collection<int, ProjectTask>  $tasks
     */
    private static function warning(Collection $tasks): string
    {
        $entries = fn (EntryType $type): int => (int) $tasks->sum(fn (ProjectTask $task): int => $task->entries()->where('type', $type)->count());
        $minutes = (int) $tasks->sum(fn (ProjectTask $task): int => (int) $task->timeEntries()->sum('minutes'));
        $messages = $entries(EntryType::Comment);
        $notes = $entries(EntryType::Note);
        $one = $tasks->count() === 1;

        $parts = array_filter([
            $messages > 0 ? trans_choice('filament-tma::tasks.delete.messages', $messages, ['count' => $messages]) : null,
            $notes > 0 ? trans_choice('filament-tma::tasks.delete.notes', $notes, ['count' => $notes]) : null,
            $minutes > 0 ? __('filament-tma::tasks.delete.time', ['time' => Duration::format($minutes, Duration::AUTO)]) : null,
        ]);

        $lines = [$parts === []
            ? ($one ? __('filament-tma::tasks.delete.nothing_one') : __('filament-tma::tasks.delete.nothing_many'))
            : ($one ? __('filament-tma::tasks.delete.goes_with_one', ['what' => self::join($parts)]) : __('filament-tma::tasks.delete.goes_with_many', ['what' => self::join($parts)]))];

        $shared = $tasks->filter(fn (ProjectTask $task): bool => $task->isShared());

        if ($shared->isNotEmpty()) {
            $clients = self::join($shared->map(fn (ProjectTask $task): string => Terms::clientOrInstance($task->target_instance_uid))->unique()->values()->all());
            $lines[] = $one
                ? __('filament-tma::tasks.delete.published_one', ['client' => $clients])
                : trans_choice('filament-tma::tasks.delete.published_many', $shared->count(), ['count' => $shared->count(), 'client' => $clients]);
        }

        $lines[] = __('filament-tma::tasks.delete.warning');

        return implode(' ', $lines);
    }

    /** @param  array<int, string>  $items  « a, b et c » */
    private static function join(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' '.__('filament-tma::tasks.delete.and').' '.$last;
    }

    private static function reason(AuthorizationException|ValidationException $e): string
    {
        return $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
    }
}
