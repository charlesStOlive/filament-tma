<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Filament\Actions\NoteActions;
use Filament\Actions\Action;

/**
 * Les notes d'une fiche (ticket ou tâche) et la modification de ses messages, en fenêtres : voir `NoteActions`. Les
 * vignettes de la chronologie et l'onglet « Notes » du volet les ouvrent.
 */
trait HasNotes
{
    /** L'onglet « Notes » du volet montre aussi les notes archivées. */
    public bool $showArchivedNotes = false;

    public function notesAction(): Action
    {
        return NoteActions::board();
    }

    public function createNoteAction(): Action
    {
        return NoteActions::create();
    }

    public function editNoteAction(): Action
    {
        return NoteActions::edit();
    }

    public function archiveNoteAction(): Action
    {
        return NoteActions::archive();
    }

    public function deleteNoteAction(): Action
    {
        return NoteActions::delete();
    }

    public function editMessageAction(): Action
    {
        return NoteActions::editMessage();
    }
}
