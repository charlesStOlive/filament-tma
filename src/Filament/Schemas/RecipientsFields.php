<?php

namespace CharlesStOlive\FilamentTma\Filament\Schemas;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Support\Addressables;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

/**
 * Les deux listes de destinataires d'un ticket ou d'une tâche (`Workflow\Recipients`), avec « Tout le monde » sur
 * chacune : principaux (prévenus tout de suite) et secondaires (dans le bilan). Les personnes possibles viennent
 * d'`Support\Addressables` ; `$clientInstanceUid` dit de quelle application cliente il s'agit.
 */
final class RecipientsFields
{
    /**
     * @param  \Closure(Get, ?Model): ?string  $clientInstanceUid
     * @param  \Closure|null  $primaryRule  une règle de plus sur les principaux (un principal chez le client…)
     * @return array<int, Select>
     */
    public static function make(\Closure $clientInstanceUid, ?\Closure $primaryRule = null): array
    {
        $options = fn (Get $get, ?Model $record): array => Addressables::options($clientInstanceUid($get, $record), $record instanceof Trackable ? $record : null);

        return [
            self::field('primary_recipients', 'secondary_recipients', $options)
                ->label(__('Destinataires principaux'))
                ->helperText(__('Prévenus tout de suite, par courriel ou dans l’application selon leurs réglages.'))
                ->rules($primaryRule !== null ? [$primaryRule] : []),
            self::field('secondary_recipients', 'primary_recipients', $options)
                ->label(__('Destinataires secondaires'))
                ->helperText(__('Prévenus seulement dans le bilan.')),
        ];
    }

    /** La liste des principaux d'un mouvement (une publication…), parmi une partie des personnes possibles. */
    public static function primaries(string $name, \Closure $options): Select
    {
        return self::field($name, null, $options)->label(__('Destinataires principaux'));
    }

    private static function field(string $name, ?string $other, \Closure $options): Select
    {
        return Select::make($name)
            ->multiple()
            ->searchable()
            ->options($options)
            ->hintAction(Action::make('everyone_'.$name)
                ->label(__('Tout le monde'))
                ->icon('heroicon-m-user-group')
                ->action(function (Get $get, Set $set, ?Model $record, Select $component) use ($name, $other): void {
                    $taken = $other !== null ? (array) $get($other) : [];
                    $everyone = array_diff(Addressables::everyone($component->getOptions()), $taken);
                    $set($name, array_values(array_unique([...(array) $get($name), ...$everyone])));
                }));
    }
}
