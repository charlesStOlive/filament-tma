<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Le tableau de bord : le suivi de la personne, en deux onglets (un groupe de boutons dans l'en-tête du tableau, plus
 * compact que les onglets d'une liste) :
 *
 * - **Tickets** : les tickets en cours (ceux de la liste « Actifs ») dont on est destinataire, principal ou
 *   secondaire ; ceux qui vous attendent d'abord, puis les plus récemment actifs. Le petit nombre de l'onglet : ceux qui
 *   vous attendent.
 * - **Projets** : les projets pas terminés où l'on participe (nommé, ou destinataire d'une de leurs tâches), avec leur
 *   fourchette et leur temps, en jours et heures. Le petit nombre de l'onglet : leurs tâches en cours.
 *
 * Dix au plus ; au-delà, la dernière ligne dit combien il en reste (« + 3 autres tickets »), en lien vers la liste.
 * Chaque onglet s'affiche à qui voit les tickets, les projets.
 */
class MySupportWidget extends TableWidget
{
    /** Le nombre de lignes montrées. */
    public const LIMIT = 10;

    public const TICKETS = 'tickets';

    public const PROJECTS = 'projects';

    protected static ?int $sort = 20;

    /** L'onglet montré (`TICKETS`, `PROJECTS`). */
    public string $tab = self::TICKETS;

    public static function canView(): bool
    {
        return static::tabs() !== [];
    }

    /** @return array<int, string> les onglets auxquels la personne a droit */
    public static function tabs(): array
    {
        return array_values(array_filter([
            TicketResource::canViewAny() ? self::TICKETS : null,
            ProjectResource::canViewAny() ? self::PROJECTS : null,
        ]));
    }

    public function mount(): void
    {
        $this->tab = static::tabs()[0] ?? self::TICKETS;
    }

    /** Un autre onglet : le tableau se refait (il ne montre plus les mêmes enregistrements). */
    public function updatedTab(): void
    {
        if (! in_array($this->tab, static::tabs(), true)) {
            $this->tab = static::tabs()[0] ?? self::TICKETS;
        }

        $this->resetTable();
    }

    public function isTab(string $tab): bool
    {
        return $this->tab === $tab && in_array($tab, static::tabs(), true);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->heading(fn (): string => $this->isTab(self::PROJECTS) ? __('Mes projets') : __('Mes tickets en cours'))
            ->description(fn (): string => $this->description($user))
            ->query(fn (): Builder => $this->isTab(self::PROJECTS)
                ? static::projects($user)->limit(static::LIMIT)
                : static::tickets($user)->limit(static::LIMIT))
            ->defaultSort(fn (Builder $query): Builder => $this->isTab(self::PROJECTS)
                ? $query->orderByDesc('tasks_max_last_activity_at')->orderBy('title')
                : static::awaitingFirst($query, $user))
            ->paginated(false)
            // Au-delà de `LIMIT`, la dernière ligne dit combien il en reste : « + 3 autres tickets ».
            ->contentFooter(fn (): ?View => $this->moreRows($user))
            ->recordUrl(fn (Model $record): ?string => $this->url($record))
            ->columns([
                // Les tickets.
                TextColumn::make('title')->label(__('Ticket'))
                    // Chez le Support, le nom court du client (« EDM »).
                    ->description(fn (Ticket $record): string => $record->number.(Tma::isMaster() ? ' · '.Terms::clientOrInstance($record->source_instance_uid) : ''), position: 'above')
                    ->weight(FontWeight::Medium)
                    ->limit(70)
                    ->wrap()
                    ->visible(fn (): bool => ! $this->isTab(self::PROJECTS)),
                TextColumn::make('recipient_role')->label(__('Destinataire'))->badge()
                    ->state(fn (Ticket $record): ?RecipientRole => $record->recipients
                        ->sortBy(fn (Recipient $recipient): int => $recipient->role === RecipientRole::Primary ? 0 : 1)
                        ->first()?->role)
                    ->visible(fn (): bool => ! $this->isTab(self::PROJECTS)),
                TextColumn::make('state')->label(__('État'))->badge()
                    ->visible(fn (): bool => ! $this->isTab(self::PROJECTS)),
                TextColumn::make('waiting_on')->label(__('En attente de'))->badge()->placeholder('—')
                    ->state(fn (Ticket $record): ?string => $record->waitingOnFor($user))
                    ->color(fn (Ticket $record): string => match (true) {
                        $record->waitingOnFor($user) === __('Vous') => 'warning',
                        $record->sideInCharge() === Side::Handler => 'info',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => ! $this->isTab(self::PROJECTS)),
                // Les projets.
                TextColumn::make('project')->label(__('Projet'))
                    ->state(fn (Project $record): string => $record->title)
                    ->description(fn (Project $record): ?string => Tma::isMaster() ? $record->targetInstance?->name : null, position: 'above')
                    ->weight(FontWeight::Medium)
                    ->limit(70)
                    ->wrap()
                    ->visible(fn (): bool => $this->isTab(self::PROJECTS)),
                TextColumn::make('estimate')->label(__('Fourchette'))
                    ->state(fn (Project $record): ?string => $record->estimateLabel())
                    ->placeholder('—')
                    ->visible(fn (): bool => $this->isTab(self::PROJECTS)),
                TextColumn::make('time')->label(__('Temps passé'))
                    ->state(fn (Project $record): string => Duration::format($record->timeTotals()['total_minutes']))
                    ->color(fn (Project $record): ?string => $record->isOverEstimate($record->timeTotals()['total_minutes']) ? 'danger' : null)
                    ->weight(FontWeight::SemiBold)
                    ->visible(fn (): bool => $this->isTab(self::PROJECTS)),
                TextColumn::make('ongoing_tasks_count')->label(__('Tâches en cours'))
                    ->badge()
                    ->color(fn (Project $record): string => $record->ongoing_tasks_count > 0 ? 'info' : 'gray')
                    ->visible(fn (): bool => $this->isTab(self::PROJECTS)),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Ouvrir'))
                    ->icon('heroicon-m-arrow-right')
                    ->button()
                    ->outlined()
                    ->size(Size::Small)
                    ->url(fn (Model $record): ?string => $this->url($record)),
            ])
            ->headerActions([
                ActionGroup::make([
                    $this->tabAction(self::TICKETS, __('Tickets'), 'heroicon-m-lifebuoy')
                        ->badge($awaiting = $user === null ? null : (static::tickets($user)->awaiting($user)->count() ?: null))
                        ->badgeColor('warning')
                        ->tooltip($awaiting !== null ? __('Le nombre : ceux qui vous attendent') : null),
                    $this->tabAction(self::PROJECTS, __('Projets'), 'heroicon-m-folder')
                        ->badge($ongoing = $user === null ? null : (static::ongoingTasks($user)->count() ?: null))
                        ->badgeColor('info')
                        ->tooltip($ongoing !== null ? __('Le nombre : leurs tâches en cours') : null),
                ])->buttonGroup()
                    ->visible(fn (): bool => count(static::tabs()) > 1),
                Action::make('all')
                    ->label(fn (): string => $this->isTab(self::PROJECTS) ? __('Tous les projets') : __('Tous les tickets'))
                    ->link()
                    ->url(fn (): string => $this->isTab(self::PROJECTS) ? ProjectResource::getUrl('index') : TicketResource::getUrl('index')),
            ])
            ->emptyStateIcon(fn (): string => $this->isTab(self::PROJECTS) ? 'heroicon-o-folder' : 'heroicon-o-lifebuoy')
            ->emptyStateHeading(fn (): string => $this->isTab(self::PROJECTS) ? __('Aucun projet en cours') : __('Aucun ticket en cours'))
            ->emptyStateDescription(fn (): string => $this->isTab(self::PROJECTS)
                ? __('Les projets où vous participez apparaîtront ici.')
                : __('Les tickets dont vous êtes destinataire apparaîtront ici.'));
    }

    /** Un bouton du groupe d'onglets : en couleur quand il est montré ; il change l'onglet sans appel de serveur à part. */
    protected function tabAction(string $tab, string $label, string $icon): Action
    {
        return Action::make($tab.'Tab')
            ->label($label)
            ->icon($icon)
            ->size(Size::Small)
            ->color(fn (): string => $this->isTab($tab) ? 'primary' : 'gray')
            ->action("\$set('tab', '{$tab}')");
    }

    protected function description(?Authenticatable $user): string
    {
        return $this->isTab(self::PROJECTS)
            ? __('Ceux où vous participez ; le temps passé, en jours et heures.')
            : __('Ceux dont vous êtes destinataire ; ceux qui vous attendent d’abord.');
    }

    /** « + 3 autres tickets », en lien vers la liste, quand il y en a plus que `LIMIT`. */
    protected function moreRows(?Authenticatable $user): ?View
    {
        $projects = $this->isTab(self::PROJECTS);
        $more = ($user === null ? 0 : ($projects ? static::projects($user) : static::tickets($user))->count()) - static::LIMIT;

        return $more <= 0 ? null : view('filament-tma::widgets.more-rows', [
            'label' => $projects
                ? trans_choice('+ :n autre projet|+ :n autres projets', $more, ['n' => $more])
                : trans_choice('+ :n autre ticket|+ :n autres tickets', $more, ['n' => $more]),
            'url' => $projects ? ProjectResource::getUrl('index') : TicketResource::getUrl('index'),
        ]);
    }

    protected function url(Model $record): ?string
    {
        return match (true) {
            $record instanceof Ticket => TicketUrl::view($record),
            $record instanceof Project => ProjectResource::canView($record) ? ProjectResource::getUrl('view', ['record' => $record]) : null,
            default => null,
        };
    }

    /** Les tickets en cours (`TicketState::active()`) dont la personne est destinataire, principale ou secondaire. */
    public static function tickets(?Authenticatable $user): Builder
    {
        $mine = Actor::idsFor($user);

        return Ticket::query()
            ->whereIn('state', TicketState::active())
            ->whereHas('recipients', fn (Builder $query): Builder => $query->whereIn('actor_id', $mine))
            ->with(['requester', 'assignee', 'recipients' => fn ($query) => $query->whereIn('actor_id', $mine)]);
    }

    /** Les projets pas terminés où la personne participe (`Project::scopeInvolving()`), avec leurs tâches en cours. */
    public static function projects(?Authenticatable $user): Builder
    {
        return Project::query()
            ->involving(Actor::idsFor($user))
            ->ongoing()
            ->with(['targetInstance', 'tasks'])
            ->withCount(['ongoingTasks as ongoing_tasks_count'])
            ->withMax('tasks', 'last_activity_at');
    }

    /** Les tâches en cours des projets de la personne : le petit nombre de l'onglet « Projets ». */
    public static function ongoingTasks(?Authenticatable $user): Builder
    {
        return ProjectTask::query()
            ->where('state', TaskInProgress::getMorphClass())
            ->whereHas('project', fn (Builder $project): Builder => $project->involving(Actor::idsFor($user)));
    }

    /** Ceux qui attendent la personne d'abord (`Ticket::awaiting()`), puis les plus récemment actifs. */
    protected static function awaitingFirst(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user !== null) {
            $awaiting = Ticket::query()->awaiting($user)->select('id')->toBase();
            $query->orderByRaw('case when '.$query->getModel()->getQualifiedKeyName().' in ('.$awaiting->toSql().') then 0 else 1 end', $awaiting->getBindings());
        }

        return $query->orderByDesc('last_activity_at');
    }
}
