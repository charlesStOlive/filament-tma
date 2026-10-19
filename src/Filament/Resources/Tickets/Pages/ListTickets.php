<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Filament\Actions\NotificationActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Au-dessus de la liste, des raccourcis (« En attente de moi », « Actifs »…) qui ne filtrent rien eux-mêmes : chacun
 * remplit les filtres de la liste, qu'on peut ensuite affiner. Le raccourci allumé est celui dont les filtres sont en
 * place ; aucun quand on les a changés.
 */
class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    /**
     * Les raccourcis : leur libellé, et les filtres qu'ils remplissent (les autres sont vidés).
     *
     * @return array<string, array{label: string, filters: array<string, array<string, mixed>>}>
     */
    protected function shortcuts(): array
    {
        $active = ['state' => ['values' => TicketState::active()]];

        return array_filter([
            'awaiting' => ['label' => __('En attente de moi'), 'filters' => ['awaiting' => ['isActive' => true]]],
            'mine' => Tma::isClient() ? ['label' => __('Mes demandes'), 'filters' => ['mine' => ['isActive' => true], ...$active]] : null,
            'active' => ['label' => __('Actifs'), 'filters' => $active],
            'all' => ['label' => __('Tous'), 'filters' => []],
        ]);
    }

    public function getTabs(): array
    {
        return array_map(fn (array $shortcut): Tab => Tab::make($shortcut['label']), $this->shortcuts());
    }

    /** Le raccourci des filtres d'office (`TicketResource::table()`). */
    public function getDefaultActiveTab(): string|int|null
    {
        return TicketResource::showsOwnRequestsFirst() ? 'mine' : 'active';
    }

    public function mount(): void
    {
        parent::mount();

        // Une adresse qui ne donne qu'un raccourci (?tab=awaiting) ouvre la liste avec ses filtres.
        if (blank($this->tableFilters) && isset($this->shortcuts()[$this->activeTab])) {
            $this->tableFilters = $this->shortcuts()[$this->activeTab]['filters'];
        }
    }

    /** Un clic sur un raccourci remplit les filtres, comme si on les avait choisis un à un. */
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $shortcut = $this->shortcuts()[$this->activeTab] ?? null;

        if ($shortcut === null) {
            return;
        }

        $this->getTableFiltersForm()->fill($shortcut['filters']);

        if ($this->getTable()->hasDeferredFilters()) {
            $this->applyTableFilters();

            return;
        }

        $this->handleTableFilterUpdates();
    }

    /** Le raccourci allumé suit les filtres en place, quelle que soit la façon dont on les a changés. */
    public function rendering(): void
    {
        $current = $this->meaningful($this->tableFilters);

        $this->activeTab = collect($this->shortcuts())
            ->search(fn (array $shortcut): bool => $this->meaningful($shortcut['filters']) == $current) ?: null;
    }

    /**
     * Ce qui filtre vraiment : un interrupteur allumé, une valeur choisie. Les listes sont triées pour se comparer.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array<string, array<string, mixed>>
     */
    private function meaningful(?array $filters): array
    {
        return collect($filters ?? [])
            ->map(fn (mixed $state): array => collect(is_array($state) ? $state : [])
                ->map(fn (mixed $value): mixed => is_array($value) ? collect($value)->filter(fn (mixed $v): bool => filled($v))->sort()->values()->all() : $value)
                ->filter(fn (mixed $value): bool => filled($value) && $value !== false && $value !== [])
                ->all())
            ->filter()
            ->all();
    }

    protected function getHeaderWidgets(): array
    {
        return [AllowanceOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Nouvelle demande')),
            ActionGroup::make([
                NotificationActions::profileLink(),
                NotificationActions::sendNow(),
            ])
                ->label(__('Notifications'))
                ->icon('heroicon-o-bell')
                ->color('gray')
                ->button(),
        ];
    }
}
