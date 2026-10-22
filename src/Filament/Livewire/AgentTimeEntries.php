<?php

namespace CharlesStOlive\FilamentTma\Filament\Livewire;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TimeEntryForm;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Le temps passé, pour un membre du Support dans une application cliente : comme chez le Support
 * (`TimeEntriesRelationManager`), les totaux puis une ligne par saisie, lues dans le maître avec son jeton (le client
 * n'en garde que les totaux, addendum §7). Il en saisit, les modifie et les supprime là-bas, en son nom ; une saisie
 * facturée ne bouge plus. Chaque changement revient ici avec l'état du ticket ou de la tâche (`Mirror::snapshot()`).
 */
class AgentTimeEntries extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Le ticket ou la tâche. */
    public Model $record;

    /** @var array<string, array<string, mixed>>|null les saisies lues dans le maître, le temps d'une requête */
    protected ?array $entries = null;

    protected ?string $error = null;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->entries())
            ->heading(__('Temps passé'))
            ->description(fn (): string => $this->error ?? TimeEntriesRelationManager::totals($this->subject()))
            ->paginated(false)
            ->emptyStateHeading(fn (): string => $this->error !== null ? __('Le Support ne répond pas') : __('Pas encore de temps saisi'))
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                Split::make([
                    TextColumn::make('actor')->label(__('Intervenant'))->weight(FontWeight::Medium)->limit(24),
                    TextColumn::make('worked_on')->label(__('Le'))->date('d/m')->color('gray')->grow(false),
                    TextColumn::make('minutes')->label(__('Durée'))
                        ->formatStateUsing(fn (int $state): string => Duration::format($state))
                        ->weight(FontWeight::SemiBold)->alignEnd()->grow(false),
                ]),
                TextColumn::make('details')->label(__('Détail'))
                    ->state(fn (array $record): ?string => collect([
                        ($entry = $this->entryOf($record['entry_ulid'])) !== null ? __('Sur : :entry', ['entry' => TimeEntryForm::entryLabel($entry, short: true)]) : null,
                        $record['billable'] ? null : __('Non facturable'),
                        $record['invoiced'] ? __('Facturée') : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->wrap()->color('gray')->size(TextSize::ExtraSmall),
            ])
            ->headerActions([
                Action::make('logTime')
                    ->label(__('Saisir du temps'))
                    ->icon('heroicon-m-plus')
                    ->size(Size::Small)
                    ->modalDescription(__('Enregistré chez le Support, en votre nom.'))
                    ->schema(fn (): array => TimeEntryForm::components(fn (): Trackable => $this->subject()))
                    ->action(function (array $data, Action $action): void {
                        $this->send($action, fn (): array => TimelineActions::logAgentTime($this->subject(), $data));
                        Notification::make()->success()->title(__('Temps enregistré chez le Support'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('Modifier'))
                    ->icon('heroicon-m-pencil-square')
                    ->iconButton()
                    ->tooltip(__('Modifier'))
                    ->hidden(fn (array $record): bool => $record['invoiced'])
                    ->fillForm(fn (array $record): array => [
                        'worked_on' => $record['worked_on'],
                        ...TimeEntryForm::fill((int) $record['minutes']),
                        'entry_id' => $this->entryOf($record['entry_ulid'])?->getKey(),
                        'billable' => $record['billable'],
                    ])
                    ->schema(fn (): array => TimeEntryForm::components(fn (): Trackable => $this->subject()))
                    ->action(function (array $record, array $data, Action $action): void {
                        $on = filled($data['entry_id'] ?? null) ? $this->subject()->entries()->find($data['entry_id']) : null;

                        $this->send($action, fn (): array => app(MasterClient::class)->agentChangeTime($this->agent(), $this->subject(), $record['ulid'], [
                            'minutes' => TimeEntryForm::minutes($data),
                            'worked_on' => Carbon::parse($data['worked_on'])->toDateString(),
                            'billable' => (bool) ($data['billable'] ?? true),
                            'summary' => TimeEntryForm::message($data),
                            'entry_ulid' => $on?->ulid,
                        ]));
                        Notification::make()->success()->title(__('Temps modifié chez le Support'))->send();
                    }),
                Action::make('delete')
                    ->label(__('Supprimer'))
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip(__('Supprimer'))
                    ->hidden(fn (array $record): bool => $record['invoiced'])
                    ->requiresConfirmation()
                    ->modalHeading(__('Supprimer cette saisie ?'))
                    ->action(function (array $record, Action $action): void {
                        $this->send($action, fn (): array => app(MasterClient::class)->agentChangeTime($this->agent(), $this->subject(), $record['ulid'], ['delete' => true]));
                        Notification::make()->success()->title(__('Saisie supprimée chez le Support'))->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('filament-tma::time.agent-entries');
    }

    /**
     * Les saisies, lues dans le maître (une fois par requête) ; si le Support ne répond pas, aucune, et l'erreur à la
     * place des totaux.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        try {
            return $this->entries = collect(app(MasterClient::class)->agentTimeEntries($this->agent(), $this->subject()))
                ->keyBy('ulid')->all();
        } catch (SyncException $e) {
            $this->error = $e->getMessage();

            return $this->entries = [];
        }
    }

    /** Envoie au maître, recopie l'état reçu, relit les saisies ; l'historique de la page se relit aussi. */
    protected function send(Action $action, \Closure $call): void
    {
        try {
            $snapshot = $call();
            DB::transaction(fn () => Mirror::snapshot($snapshot));
        } catch (SyncException|AuthorizationException|ValidationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }

        $this->record->refresh();
        $this->entries = null;
        $this->flushCachedTableRecords();
        $this->dispatch(TimeEntriesRelationManager::TIMELINE_CHANGED);
    }

    /** Le message ou la note sur lequel porte une saisie (les entrées ont le même ulid des deux côtés). */
    protected function entryOf(?string $ulid): ?Entry
    {
        return $ulid === null ? null : $this->subject()->entries()->visibleTo(auth()->user())->where('ulid', $ulid)->first();
    }

    protected function subject(): Trackable
    {
        /** @var Trackable */
        return $this->record;
    }

    protected function agent(): Agent
    {
        return Context::current()->forSubject($this->subject())->agent
            ?? throw new AuthorizationException(__('Réservé aux membres du Support.'));
    }
}
