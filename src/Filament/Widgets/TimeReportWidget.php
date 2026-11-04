<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\CopyableTable;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

/**
 * Le tableau de bord : le suivi des temps, en deux affichages (un groupe de boutons dans l'en-tête, comme
 * `MySupportWidget`) :
 *
 * - **Par mois** (`BY_MONTH`) : le temps d'un mois (le mois en cours d'abord, puis le précédent, le suivant). Une ligne
 *   pour la TMA (dans le maître, une par application cliente), puis une par projet ; sous chacune, en petit, ses
 *   tickets ou ses tâches et leur temps du mois. Pour un projet, en plus, son temps cumulé et sa fourchette.
 * - **Par tâche** (`BY_TASK`) : les derniers tickets et tâches où du temps a été passé (`LAST_TASKS`), regroupés de la
 *   même façon (la TMA, puis les projets), avec leur temps de tous les mois.
 *
 * Les sommes (une ligne du mois, le cumul, le total) sont en jours quel que soit `time.display_unit`, un ticket ou une
 * tâche en `Duration::AUTO` (jours, heures ou minutes selon sa durée). Le bouton « Copier » met le tableau montré dans
 * le presse-papiers, en HTML ou en Markdown (`Support\CopyableTable`), tickets et tâches de chaque ligne compris.
 *
 * Tous les temps, quels que soient les destinataires : le widget ne s'affiche qu'avec le droit
 * `tma.task.time-report`. Les temps viennent des saisies dans le maître, des totaux reçus chez le client
 * (`time_by_month`).
 */
class TimeReportWidget extends TableWidget
{
    public const BY_MONTH = 'month';

    public const BY_TASK = 'task';

    /** Les tickets et les tâches de `BY_TASK` : les plus récemment travaillés. */
    public const LAST_TASKS = 10;

    /** Les tickets ou les tâches nommés sous une ligne, avant « et N autres ». */
    public const ITEMS = 5;

    /** L'unité des sommes : une ligne, le cumul d'un projet, le total du mois (« 1,75 j »). */
    protected const SUM_UNIT = Duration::DAYS;

    /** L'unité d'un ticket ou d'une tâche, sous une ligne : « 1,2 j », « 2,5 h », « 30 min ». */
    protected const ITEM_UNIT = Duration::AUTO;

    protected static ?int $sort = 21;

    /** L'affichage (`BY_MONTH`, `BY_TASK`). */
    public string $mode = self::BY_MONTH;

    /** Le mois suivi (`Y-m`). */
    public string $month = '';

    /** @var array<string, array<string, mixed>>|null les lignes du mois, le temps d'une requête */
    protected ?array $rows = null;

    /** @var array<string, array<string, mixed>>|null les lignes de `BY_TASK`, le temps d'une requête */
    protected ?array $taskRows = null;

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

    /** Un autre affichage (la propriété vient du navigateur : par mois si elle n'en est pas un). */
    public function updatedMode(): void
    {
        if (! in_array($this->mode, [self::BY_MONTH, self::BY_TASK], true)) {
            $this->mode = self::BY_MONTH;
        }

        $this->flushCachedTableRecords();
    }

    public function byTask(): bool
    {
        return $this->mode === self::BY_TASK;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('filament-tma::widgets.time_report.heading'))
            ->description(fn (): ?string => $this->records() === [] && $this->byTask() ? null : $this->caption())
            ->paginated(false)
            ->recordUrl(fn (array $record): ?string => $record['url'])
            ->columns([
                TextColumn::make('title')->label(__('filament-tma::widgets.time_report.scope'))
                    ->description(fn (array $record): ?string => $record['context'], position: 'above')
                    ->description(fn (array $record): HtmlString => $this->items($record))
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                TextColumn::make('minutes')
                    ->label(fn (): string => $this->byTask() ? __('filament-tma::widgets.time_report.task_time') : __('filament-tma::widgets.time_report.month_time'))
                    ->formatStateUsing(fn (int $state): string => Duration::format($state, self::SUM_UNIT))
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),
                TextColumn::make('total')->label(__('filament-tma::widgets.time_report.cumulative'))
                    ->state(fn (array $record): ?string => $this->cumulative($record))
                    ->color(fn (array $record): ?string => $record['over'] ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->headerActions([
                ActionGroup::make([
                    $this->modeAction(self::BY_MONTH, __('filament-tma::widgets.time_report.by_month'), 'heroicon-m-calendar-days'),
                    $this->modeAction(self::BY_TASK, __('filament-tma::widgets.time_report.by_task'), 'heroicon-m-queue-list'),
                ])->buttonGroup(),
                Action::make('previousMonth')
                    ->label(__('filament-tma::widgets.time_report.previous'))
                    ->tooltip(__('filament-tma::widgets.time_report.previous'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-left')
                    ->disabled(fn (): bool => ! $this->hasMonthBefore())
                    ->visible(fn (): bool => ! $this->byTask())
                    ->action(fn () => $this->showMonth($this->shiftMonth(-1))),
                Action::make('currentMonth')
                    ->label(fn (): string => $this->monthLabel())
                    ->tooltip(__('filament-tma::widgets.time_report.current'))
                    ->button()
                    ->outlined()
                    ->color('gray')
                    ->size(Size::Small)
                    ->visible(fn (): bool => ! $this->byTask())
                    ->action(fn () => $this->showMonth(static::currentMonth())),
                Action::make('nextMonth')
                    ->label(__('filament-tma::widgets.time_report.next'))
                    ->tooltip(__('filament-tma::widgets.time_report.next'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-right')
                    ->disabled(fn (): bool => $this->month() >= static::currentMonth())
                    ->visible(fn (): bool => ! $this->byTask())
                    ->action(fn () => $this->showMonth($this->shiftMonth(1))),
                ActionGroup::make([
                    Action::make('copyHtml')->label(__('filament-tma::widgets.time_report.copy_html'))
                        ->icon('heroicon-m-code-bracket')
                        ->alpineClickHandler(fn (): string => $this->copyJs(html: true)),
                    Action::make('copyMarkdown')->label(__('filament-tma::widgets.time_report.copy_markdown'))
                        ->icon('heroicon-m-hashtag')
                        ->alpineClickHandler(fn (): string => $this->copyJs(html: false)),
                ])
                    ->label(__('filament-tma::widgets.time_report.copy'))
                    ->tooltip(__('filament-tma::widgets.time_report.copy'))
                    ->icon('heroicon-m-clipboard-document')
                    ->iconButton()
                    ->color('gray')
                    ->visible(fn (): bool => $this->records() !== []),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading(fn (): string => $this->byTask()
                ? __('filament-tma::widgets.time_report.empty_tasks')
                : __('filament-tma::widgets.time_report.empty', ['month' => mb_strtolower($this->monthLabel())]))
            ->emptyStateDescription(null);
    }

    /** Un bouton du groupe d'affichages : en couleur quand il est montré. */
    protected function modeAction(string $mode, string $label, string $icon): Action
    {
        return Action::make($mode.'Mode')
            ->label($label)
            ->icon($icon)
            ->size(Size::Small)
            ->color(fn (): string => $this->mode === $mode ? 'primary' : 'gray')
            ->action("\$set('mode', '{$mode}')");
    }

    /** @return array<string, array<string, mixed>> les lignes de l'affichage montré */
    public function records(): array
    {
        return $this->byTask() ? $this->taskRows() : $this->rows();
    }

    /** La phrase sous le titre : « Octobre 2026 : 1,75 j au total. », ou le total des derniers tickets et tâches. */
    protected function caption(): string
    {
        $total = Duration::format((int) collect($this->records())->sum('minutes'), self::SUM_UNIT);

        return $this->byTask()
            ? __('filament-tma::widgets.time_report.tasks_total', ['total' => $total])
            : __('filament-tma::widgets.time_report.total', ['month' => $this->monthLabel(), 'total' => $total]);
    }

    /** Le cumul d'un projet, et sa fourchette : « 1,93 j / 1–2 j ». */
    protected function cumulative(array $record): ?string
    {
        return $record['total'] === null ? null
            : Duration::format($record['total'], self::SUM_UNIT).($record['estimate'] !== null ? ' / '.$record['estimate'] : '');
    }

    public function showMonth(string $month): void
    {
        $this->month = $month;
        $this->rows = null;
        $this->flushCachedTableRecords();
    }

    /**
     * Le tableau montré, à copier : une ligne en gras pour la TMA ou un projet, puis tous ses tickets ou ses tâches (pas
     * seulement les `ITEMS` premiers).
     */
    public function copyableTable(): CopyableTable
    {
        $table = new CopyableTable(__('filament-tma::widgets.time_report.heading'), $this->caption(), [
            ['label' => __('filament-tma::widgets.time_report.scope')],
            ['label' => $this->byTask() ? __('filament-tma::widgets.time_report.task_time') : __('filament-tma::widgets.time_report.month_time'), 'end' => true],
            ['label' => __('filament-tma::widgets.time_report.cumulative'), 'end' => true],
        ]);

        foreach ($this->records() as $row) {
            $table->row([$row['title'].($row['context'] !== null ? ' · '.$row['context'] : ''), Duration::format($row['minutes'], self::SUM_UNIT), $this->cumulative($row)], strong: true);

            foreach ($row['items'] as $item) {
                $table->row([$item['number'].' '.$item['title'], Duration::format($item['minutes'], self::ITEM_UNIT), null], indent: true);
            }
        }

        return $table;
    }

    /**
     * Le clic d'un bouton « Copier » : le tableau, préparé à l'affichage, part dans le presse-papiers sans appel au
     * serveur (le navigateur n'accepte la copie que pendant le clic). En HTML, deux versions : le tableau, pour ce qui
     * lit le HTML (un courriel, un document), et son code pour le reste ; en Markdown, le code.
     */
    protected function copyJs(bool $html): string
    {
        $table = $this->copyableTable();
        $text = Js::from($html ? $table->html() : $table->markdown());
        $copied = Js::from(__('filament-tma::widgets.time_report.copied'));
        $failed = Js::from(__('filament-tma::widgets.time_report.copy_failed'));
        $write = $html
            ? "typeof ClipboardItem === 'undefined' ? window.navigator.clipboard.writeText(text) : window.navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([text], { type: 'text/html' }), 'text/plain': new Blob([text], { type: 'text/plain' }) })])"
            : 'window.navigator.clipboard.writeText(text)';

        return <<<JS
            const text = {$text}
            try {
                await ({$write})
                new FilamentNotification().title({$copied}).success().send()
            } catch (error) {
                new FilamentNotification().title({$failed}).danger().send()
            }
            JS;
    }

    /**
     * Les lignes du mois (`grouped()`).
     *
     * @return array<string, array{title: string, context: ?string, minutes: int, items: array<int, array{number: string, title: string, minutes: int}>, total: ?int, estimate: ?string, over: bool, url: ?string}>
     */
    public function rows(): array
    {
        return $this->rows ??= $this->grouped($this->spent(Ticket::class), $this->spent(ProjectTask::class, ['project.targetInstance']));
    }

    /**
     * Les lignes de `BY_TASK` (`grouped()`) : les derniers tickets et tâches, et leur temps de tous les mois.
     *
     * @return array<string, array{title: string, context: ?string, minutes: int, items: array<int, array{number: string, title: string, minutes: int}>, total: ?int, estimate: ?string, over: bool, url: ?string}>
     */
    public function taskRows(): array
    {
        if ($this->taskRows !== null) {
            return $this->taskRows;
        }

        $spent = $this->lastSpent();

        return $this->taskRows = $this->grouped(
            $spent->filter(fn (array $spent): bool => $spent['subject'] instanceof Ticket)->values(),
            $spent->filter(fn (array $spent): bool => $spent['subject'] instanceof ProjectTask)->values(),
        );
    }

    /**
     * La TMA d'abord (une ligne par application dans le maître), puis les projets, du plus de temps au moins ; sous
     * chaque ligne, ses tickets ou ses tâches.
     *
     * @param  Collection<int, array{subject: Ticket, minutes: int}>  $tickets
     * @param  Collection<int, array{subject: ProjectTask, minutes: int}>  $tasks
     * @return array<string, array{title: string, context: ?string, minutes: int, items: array<int, array{number: string, title: string, minutes: int}>, total: ?int, estimate: ?string, over: bool, url: ?string}>
     */
    protected function grouped(Collection $tickets, Collection $tasks): array
    {
        $tma = $tickets->groupBy(fn (array $spent): string => $spent['subject']->source_instance_uid)
            ->map(fn (Collection $spent, string $uid): array => [
                'title' => __('filament-tma::widgets.time_report.tma'),
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
                    'title' => $project?->title ?? __('filament-tma::common.no_project'),
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

        return [...$tma->all(), ...$projects->all()];
    }

    /**
     * Les derniers tickets et tâches où du temps a été passé (pas ceux supprimés), le plus récent d'abord, et tout leur
     * temps : dans le maître, d'après les saisies (le dernier jour saisi, puis la dernière saisie) ; chez le client,
     * d'après les totaux reçus (le dernier mois, puis la dernière activité).
     *
     * @return Collection<int, array{subject: Trackable, minutes: int}>
     */
    protected function lastSpent(): Collection
    {
        $models = [Ticket::class => [], ProjectTask::class => ['project.targetInstance']];

        if (! Tma::isMaster()) {
            return collect($models)
                ->flatMap(fn (array $with, string $model): Collection => $model::query()->with($with)->where('time_total_minutes', '>', 0)->get())
                ->map(fn (Trackable $subject): array => [
                    'subject' => $subject,
                    'minutes' => (int) $subject->time_total_minutes,
                    'month' => (string) collect((array) $subject->time_by_month)->filter()->keys()->max(),
                    'activity' => $subject->last_activity_at?->getTimestamp() ?? 0,
                ])
                ->sortBy([['month', 'desc'], ['activity', 'desc']])
                ->take(self::LAST_TASKS)
                ->map(fn (array $spent): array => ['subject' => $spent['subject'], 'minutes' => $spent['minutes']])
                ->values();
        }

        $latest = TimeEntry::query()
            ->whereHasMorph('subject', array_keys($models))
            ->select(['subject_type', 'subject_id'])
            ->selectRaw('sum(minutes) as minutes, max(worked_on) as last_worked_on, max(created_at) as last_created_at')
            ->groupBy('subject_type', 'subject_id')
            ->havingRaw('sum(minutes) > 0')
            ->orderByDesc('last_worked_on')
            ->orderByDesc('last_created_at')
            ->limit(self::LAST_TASKS)
            ->get();

        $subjects = collect($models)
            ->flatMap(fn (array $with, string $model): Collection => $model::query()->with($with)
                ->whereKey($latest->where('subject_type', (new $model)->getMorphClass())->pluck('subject_id')->all())->get())
            ->keyBy(fn (Trackable $subject): string => $subject->getMorphClass().'-'.$subject->getKey());

        return $latest
            ->map(fn (TimeEntry $spent): array => ['subject' => $subjects[$spent->subject_type.'-'.$spent->subject_id] ?? null, 'minutes' => (int) $spent->minutes])
            ->filter(fn (array $spent): bool => $spent['subject'] !== null)
            ->values();
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
     * @return array<int, array{number: string, title: string, minutes: int}>
     */
    protected function itemsOf(Collection $spent): array
    {
        return $spent->sortByDesc('minutes')
            ->map(fn (array $item): array => ['number' => $item['subject']->number, 'title' => $item['subject']->title, 'minutes' => $item['minutes']])
            ->values()->all();
    }

    /**
     * Sous une ligne, en petit : ses tickets ou ses tâches et leur temps, « et N autres » au-delà de `ITEMS` (par tâche,
     * tous : ils ne sont que `LAST_TASKS`).
     */
    protected function items(array $record): HtmlString
    {
        $items = collect($record['items']);
        $shown = $this->byTask() ? self::LAST_TASKS : self::ITEMS;
        $lines = $items->take($shown)->map(fn (array $item): string => e($item['number'].' '.Str::limit($item['title'], 40)).' · <strong>'.e(Duration::format($item['minutes'], self::ITEM_UNIT)).'</strong>');

        if ($items->count() > $shown) {
            $lines->push(e(trans_choice('filament-tma::common.and_others', $items->count() - $shown, ['n' => $items->count() - $shown])));
        }

        return new HtmlString($lines->implode('<br>'));
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
