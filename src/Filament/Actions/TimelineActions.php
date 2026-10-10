<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
 * Les boutons de la chronologie qui ne sont pas dans la zone de réponse (`Filament\Concerns\HasComposer`) : partager
 * une note, saisir son temps (membre du Support) ; et le champ des pièces jointes. Chacun repasse par
 * `Workflow\Timeline`, qui contrôle les droits côté serveur.
 */
final class TimelineActions
{
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

    public static function shareNote(): Action
    {
        return Action::make('shareNote')
            ->label(__('Partager'))
            ->requiresConfirmation()
            ->modalHeading(__('Partager cette note'))
            ->modalDescription(__('L’autre équipe (le Support, ou l’équipe du client) pourra la lire ; le demandeur, non. Ce partage ne se retire pas.'))
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
}
