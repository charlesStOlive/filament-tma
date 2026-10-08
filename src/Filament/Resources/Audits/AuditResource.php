<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Audits;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\Pages\ListAudits;
use CharlesStOlive\FilamentTma\Models\Audit;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** « Journal d'audit » : en lecture seule (CDC §23). Droit `tma.audit.viewany`. */
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static ?string $slug = 'tma/audits';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $permissionPrefix = 'tma.audit';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('entrée du journal');
    }

    public static function getPluralModelLabel(): string
    {
        return __('journal d’audit');
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Le'))->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('action')->label(__('Action'))->badge()->searchable(),
                TextColumn::make('subject_label')->label(__('Objet'))->searchable(),
                TextColumn::make('actor.name')->label(__('Par'))->placeholder(__('Système')),
                TextColumn::make('origin')->label(__('Origine'))->badge(),
                TextColumn::make('changes')->label(__('Changement'))
                    ->state(fn (Audit $record): HtmlString => self::summary($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('Action'))->options(fn (): array => Audit::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('origin')->label(__('Origine'))->options(Origin::class),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('Du')),
                    DatePicker::make('until')->label(__('Au')),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('Détail'))
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

        return new HtmlString(e(\Illuminate\Support\Str::limit(implode(', ', $keys), 80)));
    }

    public static function getPages(): array
    {
        return ['index' => ListAudits::route('/')];
    }
}
