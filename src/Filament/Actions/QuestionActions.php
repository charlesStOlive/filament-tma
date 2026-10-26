<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Questions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Sous une question du Support, dans la chronologie d'une tâche (`Workflow\Questions`) ; l'argument `entry` la
 * désigne :
 *
 * - `answerQuestion` : y répondre, en fenêtre (texte et pièces jointes). La première réponse venue du client la clôt ;
 * - `withdrawQuestion` : la retirer, dans le maître, tant qu'elle est ouverte.
 *
 * Poser une question se fait dans la zone d'écriture (`HasComposer`).
 */
final class QuestionActions
{
    public static function answer(): Action
    {
        return Action::make('answerQuestion')
            ->label(__('filament-tma::common.reply'))
            ->icon('heroicon-m-arrow-uturn-left')
            ->modalHeading(__('filament-tma::questions.answer.label'))
            // Le texte de la question, pour l'avoir sous les yeux.
            ->modalDescription(fn (ProjectTask $record, array $arguments): ?string => ($question = self::question($record, $arguments)) !== null
                ? Str::limit(Str::squish(Html::toText($question->body)), 300)
                : null)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('filament-tma::questions.answer.submit'))
            ->schema([NoteActions::bodyField(__('filament-tma::questions.answer.placeholder'))->required(), TimelineActions::attachmentsField()])
            ->visible(fn (ProjectTask $record, array $arguments): bool => ($question = self::question($record, $arguments)) !== null
                && app(Questions::class)->canAnswer($question, Context::current()))
            ->action(function (ProjectTask $record, array $data, array $arguments, Action $action, Component $livewire): void {
                $uploads = Uploads::fromForm($data);

                self::run($action, fn () => app(Questions::class)->answer(self::question($record, $arguments, orFail: true), $data['body'] ?? null, Context::current(), $uploads));

                $record->refresh();
                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('filament-tma::questions.answer.sent'))->send();
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('withdrawQuestion')
            ->label(__('filament-tma::questions.withdraw.label'))
            ->icon('heroicon-m-x-circle')
            ->requiresConfirmation()
            ->modalHeading(__('filament-tma::questions.withdraw.heading'))
            ->modalDescription(__('filament-tma::questions.withdraw.description'))
            ->modalSubmitActionLabel(__('filament-tma::common.withdraw'))
            ->visible(fn (ProjectTask $record, array $arguments): bool => ($question = self::question($record, $arguments)) !== null
                && app(Questions::class)->canWithdraw($question, Context::current()))
            ->action(function (ProjectTask $record, array $arguments, Action $action, Component $livewire): void {
                self::run($action, fn () => app(Questions::class)->withdraw(self::question($record, $arguments, orFail: true), Context::current()));

                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('filament-tma::common.question_withdrawn'))->send();
            });
    }

    /** La question désignée par l'argument `entry`, sur cette tâche. */
    public static function question(ProjectTask $record, array $arguments, bool $orFail = false): ?Entry
    {
        $question = NoteActions::message($record, $arguments, $orFail);

        if ($question !== null && ! $question->isQuestion()) {
            return $orFail ? abort(404) : null;
        }

        return $question;
    }

    /** Fait l'écriture ; une erreur de droit, de contenu ou d'échange s'affiche, et la fenêtre reste ouverte. */
    private static function run(Action $action, \Closure $write): mixed
    {
        try {
            return $write();
        } catch (AuthorizationException|ValidationException|SyncException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }
    }
}
