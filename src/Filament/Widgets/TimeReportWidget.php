<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Le tableau de bord : le suivi des temps d'un mois (le mois en cours d'abord, puis le précédent, le suivant), en jours
 * et heures (« 1 j 3 h », une journée de `time.day_minutes`). Une ligne pour la TMA (dans le maître, une par
 * application cliente), puis une par projet ; sous chacune, en petit, ses tickets ou ses tâches et leur temps du mois.
 * Pour un projet, en plus, son temps cumulé et sa fourchette.
 *
 * Tous les temps, quels que soient les destinataires : le widget ne s'affiche qu'avec le droit
 * `tma.task.time-report`. Les temps viennent des saisies dans le maître, des totaux reçus chez le client
 * (`time_by_month`).
 */
class TimeReportWidget extends TableWidget
{
    /** Les tickets ou les tâches nommés sous une ligne, avant « et N autres ». */
    public const ITEMS = 5;

    protected static ?int $sort = 21;

    /** Le mois suivi (`Y-m`). */
    public string $month = '';

    /** @var array<string, array<string, mixed>>|null les lignes du mois, le temps d'une requête */
    protected ?array $rows = null;

    public static function canView(): bool
    {
        return TmaGate::allows(auth()->user(), TmaGate::TIME_REPORT, new ProjectTask);
    }

    public function mount(): void
    {
        $this->month = static::currentMonth();
    }

    public function updatedMonth(): void
    {
        $this->rows = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->heading(__('Suivi des temps'))
            ->description(fn (): string => __(':month : :total au total.', ['month' => $this->monthLabel(), 'total' => Duration::format($this->monthTotal())]))
            ->paginated(false)
            ->recordUrl(fn (array $record): ?string => $record['url'])
            ->columns([
                TextColumn::make('title')->label(__('TMA et projets'))
                    ->description(fn (array $record): ?string => $record['context'], position: 'above')
                    ->description(fn (array $record): HtmlString => $this->items($record))
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                TextColumn::make('minutes')->label(__('Temps du mois'))
                    ->formatStateUsing(fn (int $state): string => Duration::format($state))
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),
                TextColumn::make('total')->label(__('Cumul / fourchette'))
                    ->state(fn (array $record): ?string => $record['total'] === null ? null
                        : Duration::format($record['total']).($record['estimate'] !== null ? ' / '.$record['estimate'] : ''))
                    ->color(fn (array $record): ?string => $record['over'] ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->headerActions([
                Action::make('previousMonth')
                    ->label(__('Mois précédent'))
                    ->tooltip(__('Mois précédent'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-left')
                    ->disabled(fn (): bool => ! $this->hasMonthBefore())
                    ->action(fn () => $this->showMonth($this->shiftMonth(-1))),
                Action::make('currentMonth')
                    ->label(fn (): string => $this->monthLabel())
                    ->tooltip(__('Revenir au mois en cours'))
                    ->button()
                    ->outlined()
                    ->color('gray')
                    ->size(Size::Small)
                    ->action(fn () => $this->showMonth(static::currentMonth())),
                Action::make('nextMonth')
                    ->label(__('Mois suivant'))
                    ->tooltip(__('Mois suivant'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-right')
                    ->disabled(fn (): bool => $this->month() >= static::currentMonth())
                    ->action(fn () => $this->showMonth($this->shiftMonth(1))),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading(fn (): string => __('Aucun temps en :month', ['month' => mb_strtolower($this->monthLabel())]))
            ->emptyStateDescription(null);
    }

    public function showMonth(string $month): void
    {
        $this->month = $month;
        $this->rows = null;
        $this->flushCachedTableRecords();
    }

    /**
     * Les lignes du mois : la TMA d'abord (une par application dans le maître), puis les projets, du plus de temps au
     * moins.
     *
     * @return array<string, array{title: string, context: ?string, minutes: int, items: array<int, array{label: string, minutes: int}>, total: ?int, estimate: ?string, over: bool, url: ?string}>
     */
    public function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $tickets = $this->spent(Ticket::class);
        $tasks = $this->spent(ProjectTask::class, ['project.targetInstance']);

        $tma = $tickets->groupBy(fn (array $spent): string => $spent['subject']->source_instance_uid)
            ->map(fn (Collection $spent, string $uid): array => [
                'title' => __('TMA'),
                'context' => Tma::isMaster() ? Terms::clientOrInstance($uid) : null,
                'minutes' => (int) $spent->sum('minutes'),
                'items' => $this->itemsOf($spent),
                'total' => null,
                'estimate' => null,
                'over' => false,
                'url' => TicketResource::canViewAny() ? TicketResource::getUrl('index') : null,
            ])
            ->sortByDesc('minutes')
            ->mapWithKeys(fn (array $row, string $uid): array => ['tma-'.$uid => $row]);

        $projects = $tasks->groupBy(fn (array $spent): string => (string) ($spent['subject']->project_id ?? 0))
            ->map(function (Collection $spent): array {
                /** @var Project|null $project */
                $project = $spent->first()['subject']->project;
                $total = $project?->timeTotals()['total_minutes'];

                return [
                    'title' => $project?->title ?? __('Sans projet'),
                    'context' => Tma::isMaster() ? $project?->targetInstance?->name : null,
                    'minutes' => (int) $spent->sum('minutes'),
                    'items' => $this->itemsOf($spent),
                    'total' => $total,
                    'estimate' => $project?->estimateLabel(),
                    'over' => $project !== null && $project->isOverEstimate((int) $total),
                    'url' => $project !== null && ProjectResource::canView($project) ? ProjectResource::getUrl('view', ['record' => $project]) : null,
                ];
            })
            ->sortByDesc('minutes')
            ->mapWithKeys(fn (array $row, string $id): array => ['project-'.$id => $row]);

        return $this->rows = [...$tma->all(), ...$projects->all()];
    }

    /**
     * Le temps passé ce mois-là sur chaque ticket ou tâche (pas ceux supprimés) : dans le maître, la somme des saisies ;
     * chez le client, les totaux reçus.
     *
     * @param  class-string<Ticket|ProjectTask>  $model
     * @return Collection<int, array{subject: Trackable, minutes: int}>
     */
    protected function spent(string $model, array $with = []): Collection
    {
        if (! Tma::isMaster()) {
            return $model::query()->with($with)->whereNotNull('time_by_month')->get()
                ->map(fn (Trackable $subject): array => ['subject' => $subject, 'minutes' => (int) (((array) $subject->time_by_month)[$this->month()] ?? 0)])
                ->filter(fn (array $spent): bool => $spent['minutes'] > 0)
                ->values();
        }

        $minutes = TimeEntry::query()
            ->where('subject_type', (new $model)->getMorphClass())
            ->whereBetween('worked_on', $this->monthBounds())
            ->get(['subject_id', 'minutes'])
            ->groupBy('subject_id')
            ->map(fn (Collection $entries): int => (int) $entries->sum('minutes'));

        return $model::query()->with($with)->whereKey($minutes->keys()->all())->get()
            ->map(fn (Trackable $subject): array => ['subject' => $subject, 'minutes' => $minutes[$subject->getKey()]])
            ->filter(fn (array $spent): bool => $spent['minutes'] > 0)
            ->values();
    }

    /**
     * @param  Collection<int, array{subject: Trackable, minutes: int}>  $spent
     * @return array<int, array{label: string, minutes: int}>
     */
    protected function itemsOf(Collection $spent): array
    {
        return $spent->sortByDesc('minutes')
            ->map(fn (array $item): array => ['label' => $item['subject']->number.' '.Str::limit($item['subject']->title, 40), 'minutes' => $item['minutes']])
            ->values()->all();
    }

    /** Sous une ligne, en petit : ses tickets ou ses tâches et leur temps du mois, « et N autres » au-delà de `ITEMS`. */
    protected function items(array $record): HtmlString
    {
        $items = collect($record['items']);
        $lines = $items->take(self::ITEMS)->map(fn (array $item): string => e($item['label']).' · <strong>'.e(Duration::format($item['minutes'])).'</strong>');

        if ($items->count() > self::ITEMS) {
            $lines->push(e(trans_choice('et :n autre|et :n autres', $items->count() - self::ITEMS, ['n' => $items->count() - self::ITEMS])));
        }

        return new HtmlString($lines->implode('<br>'));
    }

    /** Le temps du mois, tout compris. */
    protected function monthTotal(): int
    {
        return (int) collect($this->rows())->sum('minutes');
    }

    /** Du temps a été passé avant le mois montré : on peut y revenir. */
    protected function hasMonthBefore(): bool
    {
        if (Tma::isMaster()) {
            return TimeEntry::query()->where('worked_on', '<', $this->monthBounds()[0])->exists();
        }

        return collect([Ticket::class, ProjectTask::class])
            ->flatMap(fn (string $model): Collection => $model::query()->whereNotNull('time_by_month')->pluck('time_by_month'))
            ->flatMap(fn (?array $months): array => array_keys(array_filter($months ?? [])))
            ->contains(fn (string $month): bool => $month < $this->month());
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
