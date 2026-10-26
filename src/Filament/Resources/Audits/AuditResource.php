<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Audits;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\Pages\ListAudits;
use CharlesStOlive\FilamentTma\Models\Audit;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/** « Journal d'audit » : en lecture seule (CDC §23). Droit `tma.audit.viewany`. */
class AuditResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Audit::class;

    protected static ?string $slug = 'tma/audits';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $permissionPrefix = 'tma.audit';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('filament-tma::audits.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-tma::audits.model.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Le menu d'aide de la page : l'audit et le nettoyage. */
    public static function getDocumentation(): array|string
    {
        return ['tma.audit-nettoyage'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('filament-tma::common.date_on'))->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('action')->label(__('filament-tma::audits.columns.action'))->badge()->searchable(),
                TextColumn::make('subject_label')->label(__('filament-tma::audits.columns.subject'))->searchable(),
                TextColumn::make('actor.name')->label(__('filament-tma::audits.columns.causer'))->placeholder(__('filament-tma::common.system')),
                TextColumn::make('origin')->label(__('filament-tma::audits.columns.origin'))->badge(),
                TextColumn::make('changes')->label(__('filament-tma::audits.columns.change'))
                    ->state(fn (Audit $record): HtmlString => self::summary($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('filament-tma::audits.columns.action'))->options(fn (): array => Audit::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('origin')->label(__('filament-tma::audits.columns.origin'))->options(Origin::class),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('filament-tma::common.date_from')),
                    DatePicker::make('until')->label(__('filament-tma::common.date_to')),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('filament-tma::common.detail'))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Audit $record): HtmlString => new HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:.75rem">'.e(json_encode([
                            'avant' => $record->old_values, 'après' => $record->new_values, 'meta' => $record->meta,
                            'application' => $record->instance_uid,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>',
                    )),
            ]);
    }

    private static function summary(Audit $record): HtmlString
    {
        $keys = array_keys($record->new_values ?? $record->old_values ?? []);

        return new HtmlString(e(Str::limit(implode(', ', $keys), 80)));
    }

    public static function getPages(): array
    {
        return ['index' => ListAudits::route('/')];
    }
}
