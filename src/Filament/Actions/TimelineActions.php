<?php

namespace CharlesStOlive\FilamentTma\Filament\Actions;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TimeEntryForm;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisir son temps (membre du Support), et le champ des pièces jointes de la zone de réponse
 * (`Filament\Concerns\HasComposer`). Les notes et la modification d'un message sont dans `NoteActions`.
 */
final class TimelineActions
{
    /**
     * Un membre du Support, dans une application cliente : il saisit son temps dans le maître, en son nom, sur un
     * message ou une note de la fiche, ou sur le message qu'il écrit avec (qui revient ici avec l'état recopié).
     */
    public static function agentTime(): Action
    {
        return Action::make('agentTime')
            ->label(__('Saisir du temps'))
            ->icon('heroicon-m-clock')
            ->modalDescription(__('Enregistré chez le Support, en votre nom ; ici, seuls les totaux s’affichent.'))
            ->visible(fn (Trackable $record): bool => Context::current()->forSubject($record)->isAgent())
            ->schema(fn (Trackable $record): array => TimeEntryForm::components(fn (): Trackable => $record))
            ->action(function (Trackable $record, array $data, Action $action): void {
                $context = Context::current()->forSubject($record);
                $on = filled($data['entry_id'] ?? null) ? $record->entries()->find($data['entry_id']) : null;

                try {
                    $snapshot = app(MasterClient::class)->agentTime(
                        $context->agent,
                        $record,
                        TimeEntryForm::minutes($data),
                        Carbon::parse($data['worked_on'])->toDateString(),
                        (bool) ($data['billable'] ?? true),
                        TimeEntryForm::message($data),
                        $on?->ulid,
                    );
                    DB::transaction(fn () => Mirror::snapshot($snapshot));
                    $record->refresh(); // l'historique montre le nouveau message
                } catch (SyncException|AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('Temps enregistré chez le Support'))->send();
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
