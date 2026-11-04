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
 * - **Par projet** (`BY_PROJECT`) : les derniers projets où du temps a été passé (`LAST_PROJECTS`), sans la TMA, avec
 *   leur temps de tous les mois et leur fourchette ; sous chacun, en petit, une ligne par mois : ses tâches (le numéro
 *   et le début du titre) et le temps du mois, « Octobre 2026 (T-0001 Maquettes, T-0002 Export) · 5 h ».
 *
 * Les sommes (une ligne du tableau, le cumul, le total) sont en jours quel que soit `time.display_unit`, un ticket, une
 * tâche ou un mois sous une ligne en `Duration::AUTO` (jours, heures ou minutes selon sa durée). Le bouton « Copier »
 * met le tableau montré dans le presse-papiers, en HTML ou en Markdown (`Support\CopyableTable`), le détail de chaque
 * ligne compris.
 *
 * Tous les temps, quels que soient les destinataires : le widget ne s'affiche qu'avec le droit
 * `tma.task.time-report`. Les temps viennent des saisies dans le maître, des totaux reçus chez le client
 * (`time_by_month`).
 */
class TimeReportWidget extends TableWidget
{
    public const BY_MONTH = 'month';

    public const BY_PROJECT = 'project';

    /** Les projets de `BY_PROJECT` : les plus récemment travaillés. */
    public const LAST_PROJECTS = 5;

    /** Les tickets ou les tâches nommés sous une ligne (par projet, dans un mois), avant « et N autres ». */
    public const ITEMS = 5;

    /** Les mois sous une ligne de `BY_PROJECT`, avant « N mois plus tôt ». */
    public const MONTHS = 6;

    /** Le début du titre d'un ticket ou d'une tâche, dans un mois de `BY_PROJECT`. */
    protected const TITLE_START = 20;

    /** L'unité des sommes : une ligne, le cumul d'un projet, le total du mois (« 1,75 j »). */
    protected const SUM_UNIT = Duration::DAYS;

    /** L'unité d'un ticket, d'une tâche ou d'un mois, sous une ligne : « 1,2 j », « 2,5 h », « 30 min ». */
    protected const ITEM_UNIT = Duration::AUTO;

    protected static ?int $sort = 21;

    /** L'affichage (`BY_MONTH`, `BY_PROJECT`). */
    public string $mode = self::BY_MONTH;

    /** Le mois suivi (`Y-m`). */
    public string $month = '';

    /** @var array<string, array<string, mixed>>|null les lignes du mois, le temps d'une requête */
    protected ?array $rows = null;

    /** @var array<string, array<string, mixed>>|null les lignes de `BY_PROJECT`, le temps d'une requête */
    protected ?array $projectRows = null;

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
        if (! in_array($this->mode, [self::BY_MONTH, self::BY_PROJECT], true)) {
            $this->mode = self::BY_MONTH;
        }

        $this->flushCachedTableRecords();
    }

    public function byProject(): bool
    {
        return $this->mode === self::BY_PROJECT;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('filament-tma::widgets.time_report.heading'))
            ->description(fn (): ?string => $this->records() === [] && $this->byProject() ? null : $this->caption())
            ->paginated(false)
            ->recordUrl(fn (array $record): ?string => $record['url'])
            ->columns([
                TextColumn::make('title')->label(fn (): string => $this->scopeLabel())
                    ->description(fn (array $record): ?string => $record['context'], position: 'above')
                    ->description(fn (array $record): HtmlString => $this->items($record))
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                TextColumn::make('minutes')
                    ->label(fn (): string => $this->minutesLabel())
                    ->formatStateUsing(fn (int $state): string => Duration::format($state, self::SUM_UNIT))
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),
                TextColumn::make('total')->label(fn (): string => $this->totalLabel())
                    ->state(fn (array $record): ?string => $this->cumulative($record))
                    ->color(fn (array $record): ?string => $record['over'] ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->headerActions([
                ActionGroup::make([
                    $this->modeAction(self::BY_MONTH, __('filament-tma::widgets.time_report.by_month'), 'heroicon-m-calendar-days'),
                    $this->modeAction(self::BY_PROJECT, __('filament-tma::widgets.time_report.by_project'), 'heroicon-m-rectangle-stack'),
                ])->buttonGroup(),
                Action::make('previousMonth')
                    ->label(__('filament-tma::widgets.time_report.previous'))
                    ->tooltip(__('filament-tma::widgets.time_report.previous'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-left')
                    ->disabled(fn (): bool => ! $this->hasMonthBefore())
                    ->visible(fn (): bool => ! $this->byProject())
                    ->action(fn () => $this->showMonth($this->shiftMonth(-1))),
                Action::make('currentMonth')
                    ->label(fn (): string => $this->monthLabel())
                    ->tooltip(__('filament-tma::widgets.time_report.current'))
                    ->button()
                    ->outlined()
                    ->color('gray')
                    ->size(Size::Small)
                    ->visible(fn (): bool => ! $this->byProject())
                    ->action(fn () => $this->showMonth(static::currentMonth())),
                Action::make('nextMonth')
                    ->label(__('filament-tma::widgets.time_report.next'))
                    ->tooltip(__('filament-tma::widgets.time_report.next'))
                    ->iconButton()
                    ->icon('heroicon-m-chevron-right')
                    ->disabled(fn (): bool => $this->month() >= static::currentMonth())
                    ->visible(fn (): bool => ! $this->byProject())
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
            ->emptyStateHeading(fn (): string => $this->byProject()
                ? __('filament-tma::widgets.time_report.empty_projects')
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
        return $this->byProject() ? $this->projectRows() : $this->rows();
    }

    /** « TMA et projets », ou par projet « Projets ». */
    protected function scopeLabel(): string
    {
        return $this->byProject() ? __('filament-tma::widgets.time_report.projects') : __('filament-tma::widgets.time_report.scope');
    }

    /** « Temps du mois », ou par projet « Temps total ». */
    protected function minutesLabel(): string
    {
        return $this->byProject() ? __('filament-tma::widgets.time_report.total_time') : __('filament-tma::widgets.time_report.month_time');
    }

    /** « Cumul / fourchette », ou par projet « Fourchette » (le temps total est déjà à côté). */
    protected function totalLabel(): string
    {
        return $this->byProject() ? __('filament-tma::widgets.time_report.estimate') : __('filament-tma::widgets.time_report.cumulative');
    }

    /** La phrase sous le titre : « Octobre 2026 : 1,75 j au total. », ou le total des derniers projets. */
    protected function caption(): string
    {
        $total = Duration::format((int) collect($this->records())->sum('minutes'), self::SUM_UNIT);

        return $this->byProject()
            ? __('filament-tma::widgets.time_report.projects_total', ['total' => $total])
            : __('filament-tma::widgets.time_report.total', ['month' => $this->monthLabel(), 'total' => $total]);
    }

    /** Le cumul d'un projet, et sa fourchette : « 1,93 j / 1–2 j » ; par projet, la fourchette seule. */
    protected function cumulative(array $record): ?string
    {
        if ($this->byProject()) {
            return $record['estimate'];
        }

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
     * seulement les `ITEMS` premiers) ; par projet, tous ses mois, et dans chacun ses tâches, titres entiers.
     */
    public function copyableTable(): CopyableTable
    {
        $table = new CopyableTable(__('filament-tma::widgets.time_report.heading'), $this->caption(), [
            ['label' => $this->scopeLabel()],
            ['label' => $this->minutesLabel(), 'end' => true],
            ['label' => $this->totalLabel(), 'end' => true],
        ]);

        foreach ($this->records() as $row) {
            $table->row([$row['title'].($row['context'] !== null ? ' · '.$row['context'] : ''), Duration::format($row['minutes'], self::SUM_UNIT), $this->cumulative($row)], strong: true);

            if ($this->byProject()) {
                foreach ($row['months'] as $month => $spent) {
                    $items = collect($spent['items'])->map(fn (array $item): string => $item['number'].' '.$item['title'])->implode(', ');
                    $table->row([static::monthName($month).' ('.$items.')', Duration::format($spent['minutes'], self::ITEM_UNIT), null], indent: true);
                }

                continue;
            }

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
     * Les lignes de `BY_PROJECT` : les `LAST_PROJECTS` projets les plus récemment travaillés, le plus récent d'abord,
     * sans la TMA ; chacun avec son temps de tous les mois et, sous lui, ses mois (`monthsOf()`).
     *
     * @return array<string, array{title: string, context: ?string, minutes: int, months: array<string, array{minutes: int, items: array<int, array{number: string, title: string, minutes: int}>}>, estimate: ?string, over: bool, url: ?string}>
     */
    public function projectRows(): array
    {
        return $this->projectRows ??= $this->tasksSpent()
            ->groupBy(fn (array $spent): string => 'project-'.($spent['subject']->project_id ?? 0))
            ->sortByDesc(fn (Collection $spent): string => (string) $spent->max('recency'))
            ->take(self::LAST_PROJECTS)
            ->map(function (Collection $spent): array {
                $months = $this->monthsOf($spent);
                $minutes = (int) array_sum(array_column($months, 'minutes'));

                return [...$this->projectRow($spent->first()['subject']->project, $minutes), 'minutes' => $minutes, 'months' => $months];
            })
            ->all();
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
                    ...$this->projectRow($project, (int) $total),
                    'minutes' => (int) $spent->sum('minutes'),
                    'items' => $this->itemsOf($spent),
                    'total' => $total,
                ];
            })
            ->sortByDesc('minutes')
            ->mapWithKeys(fn (array $row, string $id): array => ['project-'.$id => $row]);

        return [...$tma->all(), ...$projects->all()];
    }

    /**
     * Une ligne d'un projet, sans ses temps : sa fourchette, en rouge si son temps total la dépasse.
     *
     * @return array{title: string, context: ?string, estimate: ?string, over: bool, url: ?string}
     */
    protected function projectRow(?Project $project, int $total): array
    {
        return [
            'title' => $project?->title ?? __('filament-tma::common.no_project'),
            'context' => Tma::isMaster() ? $project?->targetInstance?->name : null,
            'estimate' => $project?->estimateLabel(),
            'over' => $project !== null && $project->isOverEstimate($total),
            'url' => $project !== null && ProjectResource::canView($project) ? ProjectResource::getUrl('view', ['record' => $project]) : null,
        ];
    }

    /**
     * Toutes les tâches où du temps a été passé (pas celles supprimées), et leur temps mois par mois : dans le maître,
     * d'après les saisies ; chez le client, d'après les totaux reçus. `recency` range de la plus récemment travaillée à
     * la moins (maître : le dernier jour saisi, puis la dernière saisie ; client : le dernier mois, puis la dernière
     * activité).
     *
     * @return Collection<int, array{subject: ProjectTask, by_month: array<string, int>, recency: string}>
     */
    protected function tasksSpent(): Collection
    {
        $query = ProjectTask::query()->with('project.targetInstance');

        if (! Tma::isMaster()) {
            return $query->whereNotNull('time_by_month')->get()
                ->map(fn (ProjectTask $task): array => [
                    'subject' => $task,
                    'by_month' => array_filter(array_map('intval', (array) $task->time_by_month)),
                    'recency' => sprintf('%s %010d', collect((array) $task->time_by_month)->filter()->keys()->max(), $task->last_activity_at?->getTimestamp() ?? 0),
                ])
                ->filter(fn (array $spent): bool => $spent['by_month'] !== [])
                ->values();
        }

        $days = TimeEntry::query()
            ->whereHasMorph('subject', [ProjectTask::class])
            ->select(['subject_id', 'worked_on'])
            ->selectRaw('sum(minutes) as minutes, max(created_at) as last_created_at')
            ->groupBy('subject_id', 'worked_on')
            ->get()
            ->groupBy('subject_id');

        $tasks = $query->whereKey($days->keys()->all())->get()->keyBy(fn (ProjectTask $task): int => $task->getKey());

        return $days
            ->map(fn (Collection $days, int $id): array => [
                'subject' => $tasks[$id] ?? null,
                'by_month' => $days->groupBy(fn (TimeEntry $day): string => $day->worked_on->format('Y-m'))
                    ->map(fn (Collection $days): int => (int) $days->sum('minutes'))
                    ->filter()
                    ->all(),
                'recency' => (string) $days->max(fn (TimeEntry $day): string => $day->worked_on->toDateString().' '.$day->last_created_at),
            ])
            ->filter(fn (array $spent): bool => $spent['subject'] !== null && $spent['by_month'] !== [])
            ->values();
    }

    /**
     * Les mois d'une ligne de `BY_PROJECT`, du plus ancien au plus récent : leur temps et leurs tâches.
     *
     * @param  Collection<int, array{subject: ProjectTask, by_month: array<string, int>}>  $spent
     * @return array<string, array{minutes: int, items: array<int, array{number: string, title: string, minutes: int}>}>
     */
    protected function monthsOf(Collection $spent): array
    {
        return $spent
            ->flatMap(fn (array $spent): array => collect($spent['by_month'])
                ->map(fn (int $minutes, string $month): array => ['month' => $month, 'subject' => $spent['subject'], 'minutes' => $minutes])
                ->values()->all())
            ->groupBy('month')
            ->sortKeys()
            ->map(fn (Collection $spent): array => ['minutes' => (int) $spent->sum('minutes'), 'items' => $this->itemsOf($spent)])
            ->all();
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
     * Sous une ligne, en petit : ses tickets ou ses tâches et leur temps, « et N autres » au-delà de `ITEMS` ; par
     * projet, ses mois (`months()`).
     */
    protected function items(array $record): HtmlString
    {
        if ($this->byProject()) {
            return $this->months($record);
        }

        $items = collect($record['items']);
        $lines = $items->take(self::ITEMS)->map(fn (array $item): string => e($item['number'].' '.Str::limit($item['title'], 40)).' · <strong>'.e(Duration::format($item['minutes'], self::ITEM_UNIT)).'</strong>');

        if ($items->count() > self::ITEMS) {
            $lines->push(e(trans_choice('filament-tma::common.and_others', $items->count() - self::ITEMS, ['n' => $items->count() - self::ITEMS])));
        }

        return new HtmlString($lines->implode('<br>'));
    }

    /**
     * Sous une ligne de `BY_PROJECT`, une ligne par mois, du plus ancien au plus récent : « Octobre 2026 (T-0001
     * Maquettes, T-0002 Export) · 5 h », le début du titre seulement (en entier au survol), « et N autres » au-delà de
     * `ITEMS`. Au-delà de `MONTHS` mois, les plus anciens sont réunis en tête : « 3 mois plus tôt · 2 j ».
     */
    protected function months(array $record): HtmlString
    {
        $months = collect($record['months']);
        $earlier = $months->slice(0, max(0, $months->count() - self::MONTHS));

        $lines = $months->slice($earlier->count())->map(function (array $spent, string $month): string {
            $items = collect($spent['items']);
            $names = $items->take(self::ITEMS)
                ->map(fn (array $item): string => '<span title="'.e($item['number'].' '.$item['title']).'">'.e($item['number'].' '.Str::limit($item['title'], self::TITLE_START, '…')).'</span>');

            if ($items->count() > self::ITEMS) {
                $names->push(e(trans_choice('filament-tma::common.and_others', $items->count() - self::ITEMS, ['n' => $items->count() - self::ITEMS])));
            }

            return e(static::monthName($month)).' ('.$names->implode(', ').') · <strong>'.e(Duration::format($spent['minutes'], self::ITEM_UNIT)).'</strong>';
        });

        if ($earlier->isNotEmpty()) {
            $lines->prepend(e(trans_choice('filament-tma::widgets.time_report.earlier_months', $earlier->count(), ['n' => $earlier->count()]))
                .' · <strong>'.e(Duration::format((int) $earlier->sum('minutes'), self::ITEM_UNIT)).'</strong>');
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

    /** Le mois montré : « Octobre 2026 ». */
    protected function monthLabel(): string
    {
        return static::monthName($this->month());
    }

    /** « Octobre 2026 », pour « 2026-10 ». */
    protected static function monthName(string $month): string
    {
        return ucfirst(Carbon::createFromFormat('!Y-m', $month)->locale(app()->getLocale())->translatedFormat('F Y'));
    }

    /** Le mois en cours, à l'heure de la personne. */
    protected static function currentMonth(): string
    {
        return now(Dates::timezone(auth()->user()))->format('Y-m');
    }
}
