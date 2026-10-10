<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Commenter, noter, répondre, partager une note : les boutons de la chronologie d'un ticket. Chacun repasse par
 * `Workflow\Timeline`, qui contrôle les droits côté serveur.
 */
final class TimelineActions
{
    public static function comment(): Action
    {
        return self::writeAction('comment', EntryType::Comment)
            ->label(__('Commenter'))
            ->icon('heroicon-m-chat-bubble-left-right')
            ->modalDescription(__('Visible des deux parties. Un commentaire ne change pas l’état du ticket.'));
    }

    public static function note(): Action
    {
        return self::writeAction('note', EntryType::Note)
            ->label(__('Ajouter une note'))
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->modalDescription(fn (Trackable $record): string => Context::current()->forSubject($record)->isAgent()
                ? __('Votre note est enregistrée chez le Support, et n’est visible que là-bas.')
                : __('Une note reste dans cette application, sauf si vous la partagez ensuite.'));
    }

    /**
     * Un membre du Support, dans une application cliente : il saisit son temps dans le maître, en son nom.
     */
    public static function agentTime(): Action
    {
        return Action::make('agentTime')
            ->label(__('Saisir du temps'))
            ->icon('heroicon-m-clock')
            ->modalDescription(__('Enregistré chez le Support, en votre nom ; ici, seuls les totaux s’affichent.'))
            ->visible(fn (Trackable $record): bool => Context::current()->forSubject($record)->isAgent())
            ->schema([
                Grid::make(3)->schema([
                    DatePicker::make('worked_on')->label(__('Le'))->required()->default(now()),
                    TextInput::make('duration')->label(__('Durée'))->required()->numeric()->minValue(0.01)->step(0.25),
                    Select::make('unit')->label(__('En'))->options(Duration::units())->required()
                        ->default(config('filament-tma.time.display_unit', Duration::HOURS)),
                ]),
                Textarea::make('description')->label(__('Ce qui a été fait'))->rows(3),
                Toggle::make('billable')->label(__('Facturable'))->default(true),
            ])
            ->action(function (Trackable $record, array $data, Action $action): void {
                $context = Context::current()->forSubject($record);

                try {
                    $snapshot = app(MasterClient::class)->agentTime(
                        $context->agent,
                        $record,
                        Duration::toMinutes($data['duration'], $data['unit']),
                        Carbon::parse($data['worked_on'])->toDateString(),
                        $data['description'] ?? null,
                        (bool) ($data['billable'] ?? true),
                    );
                    Mirror::snapshot($snapshot);
                } catch (SyncException|AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('Temps enregistré chez le Support'))->send();
            });
    }

    /** Monté depuis la chronologie avec l'entrée à laquelle on répond : `mountAction('reply', { entry: 12 })`. */
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label(__('Répondre'))
            ->modalHeading(__('Répondre'))
            ->schema(self::entrySchema())
            ->action(function (Trackable $record, array $data, array $arguments, Action $action): void {
                $parent = Entry::query()->findOrFail($arguments['entry'] ?? null);

                self::write($action, fn (): Entry => app(Timeline::class)->add($record, $parent->type, $data['body'] ?? null, Context::current(), $parent, uploads: Uploads::fromForm($data)));
            });
    }

    public static function shareNote(): Action
    {
        return Action::make('shareNote')
            ->label(__('Partager'))
            ->requiresConfirmation()
            ->modalHeading(__('Partager cette note'))
            ->modalDescription(__('L’autre partie pourra la lire. Ce partage ne se retire pas.'))
            ->action(function (Trackable $record, array $arguments, Action $action): void {
                $note = $record->entries()->findOrFail($arguments['entry'] ?? null);

                try {
                    app(Timeline::class)->share($note, Context::current());
                } catch (AuthorizationException|SyncException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('Note partagée'))->send();
            });
    }

    private static function writeAction(string $name, EntryType $type): Action
    {
        return Action::make($name)
            ->modalHeading($type->getLabel())
            ->schema(self::entrySchema())
            ->visible(fn (Trackable $record): bool => app(Timeline::class)->canAdd($record, $type, Context::current()))
            ->action(function (Trackable $record, array $data, Action $action) use ($type): void {
                self::write($action, fn (): Entry => app(Timeline::class)->add($record, $type, $data['body'] ?? null, Context::current(), uploads: Uploads::fromForm($data)));
            });
    }

    /** @return array<int, mixed> */
    public static function entrySchema(): array
    {
        return [
            RichEditor::make('body')
                ->label(__('Message'))
                ->required()
                ->hintAction(Help::action('tma.chronologie.commentaire-ou-note', __('Qui le lira ?')))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ]),
            self::attachmentsField(),
        ];
    }

    public static function attachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('Pièces jointes'))
            ->hintAction(Help::action('tma.chronologie.fichiers', __('Quels fichiers ?')))
            ->multiple()
            ->disk(config('filament-tma.attachments.disk', 'local'))
            ->directory('tma-uploads')
            ->visibility('private')
            ->storeFileNamesIn('attachment_names')
            ->acceptedFileTypes(config('filament-tma.attachments.accepted_types', []))
            ->maxSize((int) config('filament-tma.attachments.max_size_kb', 10240))
            ->maxFiles((int) config('filament-tma.attachments.max_files', 10));
    }

    /** Écrit l'entrée (avec ses fichiers) et le dit ; une erreur de droit, de contenu ou d'échange s'affiche. */
    private static function write(Action $action, \Closure $create): void
    {
        try {
            $create();
        } catch (AuthorizationException|ValidationException|SyncException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title(__('Ajouté à la chronologie'))->send();
    }
}
