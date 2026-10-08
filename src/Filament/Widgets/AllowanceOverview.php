<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Models\AllowanceStatus;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/**
 * Côté client : la consommation du forfait de TMA sur la période en cours, telle que le maître l'a envoyée. Affiché
 * en tête de la liste des tickets.
 */
class AllowanceOverview extends StatsOverviewWidget
{
    /** Lu par filament-permission-manager : `widgets.allowanceoverview.viewany`. */
    public static bool $requiresPermission = true;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Tma::isClient()
            && AllowanceStatus::query()->exists()
            && (! Gate::has(static::class) || Gate::allows(static::class));
    }

    protected function getStats(): array
    {
        return AllowanceStatus::query()->get()->map(function (AllowanceStatus $row): Stat {
            $s = $row->status;
            $percent = $s['percent'] ?? 0;

            return Stat::make(
                __('Forfait :label (:period)', ['label' => $s['label'], 'period' => $s['period_key']]),
                Duration::format((int) $s['consumed_minutes']).' / '.Duration::format((int) $s['allocated_minutes']),
            )
                ->description($s['overage_minutes'] > 0
                    ? __('Dépassé de :o', ['o' => Duration::format((int) $s['overage_minutes'])])
                    : __(':percent % consommé · reste :r', ['percent' => $percent, 'r' => Duration::format((int) $s['remaining_minutes'])]))
                ->color(match (true) {
                    $percent >= 100 => 'danger',
                    $percent >= 80 => 'warning',
                    default => 'success',
                })
                ->chart(array_values($s['by_month'] ?? []));
        })->all();
    }
}
