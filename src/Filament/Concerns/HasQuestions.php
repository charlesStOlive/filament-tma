<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Filament\Actions\QuestionActions;
use Filament\Actions\Action;

/** Les questions du Support d'une tâche, dans sa chronologie : y répondre, la retirer (voir `QuestionActions`). */
trait HasQuestions
{
    public function answerQuestionAction(): Action
    {
        return QuestionActions::answer();
    }

    public function withdrawQuestionAction(): Action
    {
        return QuestionActions::withdraw();
    }
}
