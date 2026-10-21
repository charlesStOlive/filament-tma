<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Le tableau de bord : les tâches projet (toutes, pas seulement celles dont on est destinataire), en trois onglets, à
 * la manière des listes de Filament (`HasTabs`, les onglets au-dessus du tableau) :
 *
 * - **Tâches en cours** : sans les temps ;
 * - **Suivi des temps** : un mois (le mois en cours d'abord, puis le précédent, le suivant), chaque tâche et le temps
 *   passé ce mois-là ;
 * - **Temps par tâche** : les dernières tâches où du temps a été passé, et leur temps cumulé, tous mois confondus.
 *
 * Le widget s'affiche à qui voit les tâches ; les deux onglets de temps demandent en plus le droit
 * `tma.task.time-report`. Les temps viennent des saisies dans le maître, des totaux reçus chez le client
 * (`time_by_month`, `time_total_minutes`).
 */
class ProjectTasksWidget extends TableWidget
{
    use HasTabs {
        updatedActiveTab as baseUpdatedActiveTab;
    }

    /** Le nombre de tâches de l'onglet « Temps par tâche ». */
    public const LAST_TASKS = 5;

    protected static ?int $sort = 21;

    protected string $view = 'filament-tma::widgets.project-tasks';

    /** Le mois suivi (`Y-m`), dans l'onglet « Suivi des temps ». */
    public string $month = '';

    public static function canView(): bool
    {
        return TaskResource::canViewAny();
    }

    /** Les deux onglets de temps. */
    public static function canSeeTimes(): bool
    {
        return TmaGate::allows(auth()->user(), TmaGate::TIME_REPORT, new ProjectTask);
    }

    public function mount(): void
    {
        $this->month = static::currentMonth();
        $this->loadDefaultActiveTab();
    }

    /** Un onglet auquel on n'a pas droit (la propriété vient du navigateur) ramène au premier. */
    public function updatedActiveTab(): void
    {
        if (! array_key_exists((string) $this->activeTab, $this->getCachedTabs())) {
            $this->activeTab = $this->getDefaultActiveTab();
        }

        $this->baseUpdatedActiveTab();
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'ongoing' => Tab::make(__('Tâches en cours'))
                ->icon('heroicon-m-clipboard-document-list')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('state', TaskInProgress::getMorphClass())),
            ...(static::canSeeTimes() ? [
                'month' => Tab::make(__('Suivi des temps'))
                    ->icon('heroicon-m-calendar-days')
                    ->modifyQueryUsing(fn (Builder $query): Builder => $this->inMonth($query)),
                'tasks' => Tab::make(__('Temps par tâche'))
                    ->icon('heroicon-m-clock')
                    ->modifyQueryUsing(fn (Builder $query): Builder => $this->withTime($query)->limit(static::LAST_TASKS)),
            ] : []),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->tasks())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('last_activity_at'))
            ->heading(fn (): string => match (true) {
                $this->isTab('month') => __('Temps du mois'),
                $this->isTab('tasks') => __('Temps par tâche'),
                default => __('Tâches en cours'),
            })
            ->description(fn (): string => match (true) {
                $this->isTab('month') => __(':month : :total au total.', ['month' => $this->monthLabel(), 'total' => Duration::format($this->monthTotal())]),
                $this->isTab('tasks') => trans_choice('La dernière tâche où du temps a été passé, tous mois confondus.|Les :n dernières tâches où du temps a été passé, tous mois confondus.', static::LAST_TASKS, ['n' => static::LAST_TASKS]),
                default => __('Les tâches projet en cours.'),
            })
            ->paginated(fn (): bool => ! $this->isTab('tasks'))
            ->paginationPageOptions([10])
            ->recordUrl(fn (ProjectTask $record): string => TaskResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label(__('Tâche'))
                    ->description(fn (ProjectTask $record): string => $record->number.($record->project !== null ? ' · '.$record->project->title : ''), position: 'above')
                    ->weight(FontWeight::Medium)
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('due_on')->label(__('Échéance'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (ProjectTask $record): ?string => $record->due_on?->isPast() ? 'danger' : null)
                    ->visible(fn (): bool => $this->isTab('ongoing')),
                TextColumn::make('month_time')->label(__('Temps du mois'))
                    ->state(fn (ProjectTask $record): string => Duration::format($this->minutesInMonth($record)))
                    ->alignEnd()
                    ->visible(fn (): bool => $this->isTab('month')),
                TextColumn::make('total_time')->label(__('Temps total'))
                    ->state(fn (ProjectTask $record): string => Duration::format($this->totalMinutes($record)))
                    ->alignEnd()
                    ->visible(fn (): bool => $this->isTab('tasks')),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Ouvrir'))
                    ->icon('heroicon-m-arrow-right')
                    ->button()
                    ->outlined()
                    ->size(Size::Small)
                    ->url(fn (ProjectTask $record): string => TaskResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('previousMonth')
                    ->label(__('Mois précédent'))
                    ->tooltip(__('Mois précédent'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-left')
                    ->disabled(fn (): bool => ! $this->hasMonthBefore())
                    ->action(fn () => $this->month = $this->shiftMonth(-1))
                    ->visible(fn (): bool => $this->isTab('month')),
                Action::make('currentMonth')
                    ->label(fn (): string => $this->monthLabel())
                    ->tooltip(__('Revenir au mois en cours'))
                    ->button()
                    ->outlined()
                    ->color('gray')
                    ->size(Size::Small)
                    ->action(fn () => $this->month = static::currentMonth())
                    ->visible(fn (): bool => $this->isTab('month')),
                Action::make('nextMonth')
                    ->label(__('Mois suivant'))
                    ->tooltip(__('Mois suivant'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-right')
                    ->disabled(fn (): bool => $this->month() >= static::currentMonth())
                    ->action(fn () => $this->month = $this->shiftMonth(1))
                    ->visible(fn (): bool => $this->isTab('month')),
                Action::make('all')
                    ->label(__('Toutes les tâches'))
                    ->link()
                    ->url(fn (): string => TaskResource::getUrl('index'))
                    ->visible(fn (): bool => $this->isTab('ongoing')),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(fn (): string => match (true) {
                $this->isTab('month') => __('Aucun temps en :month', ['month' => mb_strtolower($this->monthLabel())]),
                $this->isTab('tasks') => __('Aucun temps passé'),
                default => __('Aucune tâche en cours'),
            })
            ->emptyStateDescription(fn (): ?string => $this->isTab('ongoing') ? __('Les tâches projet en cours apparaîtront ici.') : null);
    }

    /** L'onglet actif est celui-là, et la personne y a droit. */
    public function isTab(string $key): bool
    {
        return $this->activeTab === $key && array_key_exists($key, $this->getCachedTabs());
    }

    /** Les tâches projet, toutes. */
    protected function tasks(): Builder
    {
        return ProjectTask::query()->with('project');
    }

    /** Les tâches où du temps a été passé ce mois-là, avec ce temps (dans le maître, la somme de ses saisies). */
    protected function inMonth(Builder $query): Builder
    {
        if (! Tma::isMaster()) {
            return $query->where('time_by_month->'.$this->month(), '>', 0);
        }

        $period = fn (Builder $entries): Builder => $entries->whereBetween('worked_on', $this->monthBounds());

        return $query->whereHas('timeEntries', $period)->withSum(['timeEntries as month_minutes' => $period], 'minutes');
    }

    /** Les tâches où du temps a été passé, avec leur total. */
    protected function withTime(Builder $query): Builder
    {
        return Tma::isMaster()
            ? $query->whereHas('timeEntries')->withSum('timeEntries as total_minutes', 'minutes')
            : $query->where('time_total_minutes', '>', 0);
    }

    protected function minutesInMonth(ProjectTask $task): int
    {
        return Tma::isMaster()
            ? (int) $task->getAttribute('month_minutes')
            : (int) (((array) $task->time_by_month)[$this->month()] ?? 0);
    }

    protected function totalMinutes(ProjectTask $task): int
    {
        return Tma::isMaster() ? (int) $task->getAttribute('total_minutes') : (int) $task->time_total_minutes;
    }

    /** Le temps du mois, toutes tâches confondues. */
    protected function monthTotal(): int
    {
        if (Tma::isMaster()) {
            return (int) $this->entries()->whereBetween('worked_on', $this->monthBounds())->sum('minutes');
        }

        return (int) $this->tasks()->pluck('time_by_month')->sum(fn (?array $months): int => (int) (($months ?? [])[$this->month()] ?? 0));
    }

    /** Du temps a été passé avant le mois montré : on peut y revenir. */
    protected function hasMonthBefore(): bool
    {
        if (Tma::isMaster()) {
            return $this->entries()->where('worked_on', '<', $this->monthBounds()[0])->exists();
        }

        return $this->tasks()->pluck('time_by_month')
            ->flatMap(fn (?array $months): array => array_keys(array_filter($months ?? [])))
            ->contains(fn (string $month): bool => $month < $this->month());
    }

    /** Maître : les saisies de temps sur les tâches (pas celles d'une tâche supprimée). */
    protected function entries(): Builder
    {
        return TimeEntry::query()
            ->where('subject_type', (new ProjectTask)->getMorphClass())
            ->whereIn('subject_id', ProjectTask::query()->select('id'));
    }

    /** Le mois montré, s'il est valide (la propriété vient du navigateur), sinon le mois en cours. */
    protected function month(): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) && $this->month <= static::currentMonth() ? $this->month : static::currentMonth();
    }

    protected function shiftMonth(int $months): string
    {
        return Carbon::createFromFormat('!Y-m', $this->month())->addMonthsNoOverflow($months)->format('Y-m');
    }

    /** @return array{0: string, 1: string} le premier et le dernier jour du mois montré */
    protected function monthBounds(): array
    {
        $start = Carbon::createFromFormat('!Y-m', $this->month());

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    /** « Octobre 2026 ». */
    protected function monthLabel(): string
    {
        return ucfirst(Carbon::createFromFormat('!Y-m', $this->month())->locale(app()->getLocale())->translatedFormat('F Y'));
    }

    /** Le mois en cours, à l'heure de la personne. */
    protected static function currentMonth(): string
    {
        return now(Dates::timezone(auth()->user()))->format('Y-m');
    }
}
