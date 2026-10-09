<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Allowances;

use CharlesStOlive\FilamentTma\Enums\AllowancePeriod;
use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages\CreateAllowance;
use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages\EditAllowance;
use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages\ListAllowances;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * « Forfaits » (maître) : le volume de TMA alloué à un client ou à une application, par mois ou par trimestre, et sa
 * consommation sur la période en cours. Les seuils (80, 90, 100, 110 %) déclenchent `AllowanceThresholdReached`.
 */
class AllowanceResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Allowance::class;

    protected static ?string $slug = 'tma/allowances';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $permissionPrefix = 'tma.allowance';

    /** @var array<int, string> */
    protected static array $specificPermissions = ['reset'];

    /** @var array<string, string> */
    protected static array $permissionLabels = ['reset' => 'Réinitialiser les seuils d’une période'];

    public static function getModelLabel(): string
    {
        return __('forfait');
    }

    public static function getPluralModelLabel(): string
    {
        return __('forfaits');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function canAccess(): bool
    {
        return Tma::isMaster() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Tma::isMaster() && parent::shouldRegisterNavigation();
    }

    /** Le menu d'aide de la page : les forfaits et ce qui les consomme. */
    public static function getDocumentation(): array|string
    {
        return ['tma.forfaits', 'tma.temps'];
    }

    public static function form(Schema $schema): Schema
    {
        $clientModel = config('filament-tma.models.client');
        $title = config('filament-tma.models.client_title', 'name');

        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                Select::make('client_id')->label(__('Client'))
                    ->options(fn (): array => $clientModel ? $clientModel::query()->orderBy($title)->pluck($title, (new $clientModel)->getKeyName())->all() : [])
                    ->searchable()
                    ->required(fn ($get): bool => blank($get('instance_uid')))
                    ->helperText(__('Sans application choisie, le forfait est partagé entre toutes celles du client.')),
                Select::make('instance_uid')->label(__('Application'))
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all())
                    ->placeholder(__('Toutes les applications du client')),
                TextInput::make('contract')->label(__('Contrat'))->maxLength(255),
                Select::make('period')->label(__('Rythme'))->options(AllowancePeriod::class)->default(AllowancePeriod::Monthly->value)->required(),
                TextInput::make('volume')->label(__('Volume par période'))->numeric()->minValue(0.25)->step(0.25)->required(),
                Select::make('unit')->label(__('En'))->options([Duration::HOURS => __('heures'), Duration::DAYS => __('jours')])->default(Duration::DAYS)->required(),
                DatePicker::make('starts_on')->label(__('Du'))->required()->default(now()->startOfMonth()),
                DatePicker::make('ends_on')->label(__('Au'))->afterOrEqual('starts_on'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label(__('Forfait'))->state(fn (Allowance $record): string => $record->label()),
                TextColumn::make('period')->label(__('Rythme'))->badge(),
                TextColumn::make('allocated')->label(__('Alloué'))->state(fn (Allowance $record): string => Duration::format($record->minutes)),
                TextColumn::make('consumed')->label(__('Consommé (période en cours)'))
                    ->state(function (Allowance $record): HtmlString {
                        $s = AllowanceMeter::status($record);

                        return new HtmlString(e(Duration::format($s['consumed_minutes'])).' <small>('.e((string) $s['percent']).' %)</small>');
                    })
                    ->color(fn (Allowance $record): string => match (true) {
                        (AllowanceMeter::status($record)['percent'] ?? 0) >= 100 => 'danger',
                        (AllowanceMeter::status($record)['percent'] ?? 0) >= 80 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('remaining')->label(__('Restant'))
                    ->state(fn (Allowance $record): string => Duration::format(AllowanceMeter::status($record)['remaining_minutes'])),
                TextColumn::make('starts_on')->label(__('Du'))->date('d/m/Y'),
                TextColumn::make('ends_on')->label(__('Au'))->date('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('months')
                    ->label(__('Détail'))
                    ->icon('heroicon-m-calendar')
                    ->modalSubmitAction(false)
                    ->modalContent(function (Allowance $record): HtmlString {
                        $s = AllowanceMeter::status($record);
                        $rows = collect($s['by_month'])->map(fn (int $minutes, string $month): string => '<tr><td>'.e($month).'</td><td>'.e(Duration::format($minutes)).'</td></tr>')->implode('');

                        return new HtmlString('<p>'.e(__('Période :p : :c consommées sur :a.', ['p' => $s['period_key'], 'c' => Duration::format($s['consumed_minutes']), 'a' => Duration::format($s['allocated_minutes'])])).'</p>'
                            .($rows !== '' ? '<table class="fi-tma-months">'.$rows.'</table>' : ''));
                    }),
                Action::make('resetThresholds')
                    ->label(__('Réinitialiser les seuils'))
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalDescription(__('Les seuils de la période en cours pourront se déclencher de nouveau.'))
                    ->authorize(static::class.'.reset')
                    ->action(function (Allowance $record): void {
                        AllowanceMeter::resetThresholds($record, AllowanceMeter::periodKey($record));
                        Notification::make()->success()->title(__('Seuils réinitialisés'))->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllowances::route('/'),
            'create' => CreateAllowance::route('/create'),
            'edit' => EditAllowance::route('/{record}/edit'),
        ];
    }

    /**
     * Le formulaire saisit un volume en heures ou en jours ; on garde des minutes, et le client va avec l'application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepare(array $data): array
    {
        $data['minutes'] = Duration::toMinutes($data['volume'], $data['unit']);
        unset($data['volume'], $data['unit']);

        if (filled($data['instance_uid'] ?? null)) {
            $instance = Instance::query()->where('uid', $data['instance_uid'])->first();
            $data['client_type'] = $instance?->client_type;
            $data['client_id'] = $instance?->client_id ?? $data['client_id'] ?? null;
        } else {
            $model = config('filament-tma.models.client');
            $data['client_type'] = $model ? (new $model)->getMorphClass() : null;
        }

        return $data;
    }
}
