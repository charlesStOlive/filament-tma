<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Les notes et les messages qu'on modifie, toujours en fenêtre :
 *
 * - `notes` : la grande fenêtre d'un message (son texte, ses fichiers en vignettes) avec, à droite, ses notes ; sans
 *   message, les notes sur le ticket entier, à côté de la demande. Arguments : `entry` (le message), `note` (celle à
 *   montrer) ;
 * - `createNote`, `editNote`, `archiveNote`, `deleteNote` : depuis la page, ou par-dessus la grande fenêtre (elle les
 *   déclare : `registerModalActions()`), qui se met à jour en revenant ;
 * - `editMessage` : son propre message, tant que personne d'autre n'a écrit après lui.
 *
 * Les droits sont ceux de `Workflow\Timeline`, contrôlés côté serveur à chaque écriture.
 */
final class NoteActions
{
    public static function board(): Action
    {
        return Action::make('notes')
            ->label(__('Notes'))
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->modalHeading(fn (Trackable $record, array $arguments): string => ($message = self::message($record, $arguments)) !== null
                ? __('Le message de :author et ses notes', ['author' => $message->author?->name ?? '?'])
                : __('Les notes sur :subject', ['subject' => $record->number]))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (Trackable $record, array $arguments, Action $action) => view('filament-tma::notes.board', [
                'subject' => $record,
                'message' => self::message($record, $arguments),
                'focus' => (int) ($arguments['note'] ?? 0),
                'action' => $action,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Fermer'))
            ->registerModalActions([self::create(), self::edit(), self::archive(), self::delete()])
            ->visible(fn (): bool => Timeline::notesEnabled());
    }

    public static function create(): Action
    {
        return Action::make('createNote')
            ->label(__('Ajouter une note'))
            ->icon('heroicon-m-plus')
            ->color('warning')
            ->modalHeading(fn (Trackable $record, array $arguments): string => ($message = self::message($record, $arguments)) !== null
                ? __('Une note sur le message de :author', ['author' => $message->author?->name ?? '?'])
                : __('Une note sur :subject', ['subject' => $record->number]))
            ->modalDescription(__('Tout le monde sur le ticket la lira, sauf si vous la gardez pour vous ; elle ne change pas son état.'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('Ajouter la note'))
            ->overlayParentActions()
            ->schema([self::bodyField(), self::imagesField(), self::privateField()])
            ->visible(fn (Trackable $record): bool => app(Timeline::class)->canAdd($record, EntryType::Note, Context::current()))
            ->action(function (Trackable $record, array $data, array $arguments, Action $action): void {
                $note = self::run($action, fn (): Entry => app(Timeline::class)->add(
                    $record,
                    EntryType::Note,
                    $data['body'] ?? null,
                    Context::current(),
                    self::message($record, $arguments),
                    uploads: Uploads::fromForm($data),
                    private: (bool) ($data['private'] ?? false),
                ));

                Notification::make()->success()->title(__(':note ajoutée', ['note' => $note->noteLabel()]))->send();
            });
    }

    public static function edit(): Action
    {
        return Action::make('editNote')
            ->label(__('Modifier'))
            ->icon('heroicon-m-pencil')
            ->color('gray')
            ->modalHeading(fn (Trackable $record, array $arguments): string => __('Modifier la note n° :number', ['number' => self::note($record, $arguments)->number]))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->overlayParentActions()
            ->fillForm(fn (Trackable $record, array $arguments): array => [
                'body' => self::note($record, $arguments)->body,
                'private' => self::note($record, $arguments)->private,
            ])
            ->schema(fn (Trackable $record, array $arguments): array => [
                self::bodyField(),
                CheckboxList::make('remove_files')
                    ->label(__('Retirer'))
                    ->options(self::note($record, $arguments)->getMedia(Entry::ATTACHMENTS_COLLECTION)
                        ->mapWithKeys(fn (Media $media): array => [Uploads::uuidOf($media) => $media->file_name])->all())
                    ->visible(fn (): bool => self::note($record, $arguments)->getMedia(Entry::ATTACHMENTS_COLLECTION)->isNotEmpty())
                    ->columns(2),
                self::imagesField()->label(__('Ajouter des images')),
                self::privateField(),
            ])
            ->visible(fn (Trackable $record, array $arguments): bool => ($note = self::note($record, $arguments, orFail: false)) !== null
                && app(Timeline::class)->canEdit($note, Context::current()))
            ->action(function (Trackable $record, array $data, array $arguments, Action $action): void {
                $note = self::note($record, $arguments);

                self::run($action, fn (): Entry => app(Timeline::class)->edit(
                    $note,
                    $data['body'] ?? null,
                    Context::current(),
                    Uploads::fromForm($data),
                    array_values((array) ($data['remove_files'] ?? [])),
                    (bool) ($data['private'] ?? false),
                ));

                Notification::make()->success()->title(__(':note modifiée', ['note' => $note->noteLabel()]))->send();
            });
    }

    /** Archiver une note (ou la ressortir) : sans fenêtre. */
    public static function archive(): Action
    {
        return Action::make('archiveNote')
            ->label(fn (Trackable $record, array $arguments): string => self::note($record, $arguments, orFail: false)?->isArchived() ? __('Ressortir') : __('Archiver'))
            ->icon(fn (Trackable $record, array $arguments): string => self::note($record, $arguments, orFail: false)?->isArchived() ? 'heroicon-m-arrow-uturn-up' : 'heroicon-m-archive-box')
            ->color('gray')
            ->visible(fn (Trackable $record, array $arguments): bool => ($note = self::note($record, $arguments, orFail: false)) !== null
                && app(Timeline::class)->canArchive($note, Context::current()))
            ->action(function (Trackable $record, array $arguments, Action $action): void {
                $note = self::note($record, $arguments);
                $archived = ! $note->isArchived();

                self::run($action, fn (): Entry => app(Timeline::class)->archive($note, $archived, Context::current()));

                Notification::make()->success()
                    ->title($archived ? __(':note archivée', ['note' => $note->noteLabel()]) : __(':note ressortie', ['note' => $note->noteLabel()]))
                    ->send();
            });
    }

    public static function delete(): Action
    {
        return Action::make('deleteNote')
            ->label(__('Supprimer'))
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Trackable $record, array $arguments): string => __('Supprimer la note n° :number ?', ['number' => self::note($record, $arguments)->number]))
            ->modalDescription(__('Elle disparaît, des deux côtés. Son numéro n’est pas redonné.'))
            ->modalSubmitActionLabel(__('Supprimer'))
            ->overlayParentActions()
            ->visible(fn (Trackable $record, array $arguments): bool => ($note = self::note($record, $arguments, orFail: false)) !== null
                && app(Timeline::class)->canDelete($note, Context::current()))
            ->action(function (Trackable $record, array $arguments, Action $action): void {
                $note = self::note($record, $arguments);

                self::run($action, fn (): Entry => app(Timeline::class)->delete($note, Context::current()));

                Notification::make()->success()->title(__(':note supprimée', ['note' => $note->noteLabel()]))->send();
            });
    }

    /** Son propre message, tant que personne d'autre n'a écrit après lui ; l'argument `entry` le désigne. */
    public static function editMessage(): Action
    {
        return Action::make('editMessage')
            ->label(__('Modifier'))
            ->icon('heroicon-m-pencil')
            ->color('gray')
            ->modalHeading(__('Modifier votre message'))
            ->modalDescription(__('Possible tant que personne d’autre n’a écrit après lui. Tout le monde verra qu’il a été modifié.'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->fillForm(fn (Trackable $record, array $arguments): array => ['body' => self::message($record, $arguments, orFail: true)->body])
            ->schema([self::bodyField(__('Votre message…'))->required()])
            ->visible(fn (Trackable $record, array $arguments): bool => ($message = self::message($record, $arguments)) !== null
                && app(Timeline::class)->canEdit($message, Context::current()))
            ->action(function (Trackable $record, array $data, array $arguments, Action $action): void {
                $message = self::message($record, $arguments, orFail: true);

                self::run($action, fn (): Entry => app(Timeline::class)->edit($message, $data['body'] ?? null, Context::current()));

                Notification::make()->success()->title(__('Message modifié'))->send();
            });
    }

    public static function bodyField(?string $placeholder = null): RichEditor
    {
        return RichEditor::make('body')
            ->hiddenLabel()
            ->placeholder($placeholder ?? __('Votre note…'))
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                ['undo', 'redo'],
            ]);
    }

    /** Une note qu'on garde pour soi : personne d'autre ne la voit, des deux côtés ; on peut la montrer plus tard. */
    public static function privateField(): Toggle
    {
        return Toggle::make('private')
            ->label(__('Visible de moi seul'))
            ->helperText(__('Personne d’autre ne la verra. Vous pourrez la montrer à tous plus tard.'));
    }

    /** Les images d'une note : celles de la liste blanche des pièces jointes (`filament-tma.attachments`). */
    public static function imagesField(): FileUpload
    {
        $images = array_values(array_filter(
            (array) config('filament-tma.attachments.accepted_types', []),
            fn (string $type): bool => str_starts_with($type, 'image/'),
        ));

        return FileUpload::make('attachments')
            ->label(__('Images'))
            ->hintAction(Help::action('tma.chronologie.notes', __('Les notes')))
            ->image()
            ->multiple()
            ->disk(config('filament-tma.attachments.disk', 'local'))
            ->directory('tma-uploads')
            ->visibility('private')
            ->storeFileNamesIn('attachment_names')
            ->acceptedFileTypes($images)
            ->maxSize((int) config('filament-tma.attachments.max_size_kb', 10240))
            ->maxFiles((int) config('filament-tma.attachments.max_files', 10));
    }

    /** Le message désigné par l'argument `entry` : un message de premier niveau du ticket (sans lui, le ticket entier). */
    public static function message(Trackable $record, array $arguments, bool $orFail = false): ?Entry
    {
        $id = $arguments['entry'] ?? null;

        if (blank($id)) {
            return $orFail ? abort(404) : null;
        }

        $query = $record->entries()->messages()->whereNull('parent_id')->whereKey($id);

        return $orFail ? $query->firstOrFail() : $query->first();
    }

    /** La note désignée par l'argument `note`, archivée ou non, si l'utilisateur peut la lire. */
    public static function note(Trackable $record, array $arguments, bool $orFail = true): ?Entry
    {
        $query = $record->entries()->notes()->visibleTo(auth()->user())->whereKey($arguments['note'] ?? null);

        return $orFail ? $query->firstOrFail() : $query->first();
    }

    /**
     * Fait l'écriture ; une erreur de droit, de contenu ou d'échange avec le Support s'affiche, et la fenêtre reste
     * ouverte avec ce qu'on a écrit.
     *
     * @template T
     *
     * @param  \Closure(): T  $write
     * @return T
     */
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
