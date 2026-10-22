<?php

namespace CharlesStOlive\FilamentTma\Filament\Schemas;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Support\Addressables;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

/**
 * Les deux listes de destinataires d'un ticket ou d'une tâche (`Workflow\Recipients`) : principaux (prévenus tout de
 * suite) et secondaires (dans le bilan). Les personnes possibles viennent d'`Support\Addressables` ;
 * `$clientInstanceUid` dit de quelle application cliente il s'agit.
 *
 * Au-dessus de chacune : « Tout le monde » ; pour une tâche, « Toute l'équipe projet » (les participants de son
 * projet, `Project::participants()`) ; et « Vider ».
 */
final class RecipientsFields
{
    /**
     * @param  \Closure(Get, ?Model): ?string  $clientInstanceUid
     * @param  \Closure|null  $primaryRule  une règle de plus sur les principaux (un principal chez le client…)
     * @param  (\Closure(Get, ?Model): ?Project)|null  $project  le projet d'une tâche : « Toute l'équipe projet »
     * @return array<int, Select>
     */
    public static function make(\Closure $clientInstanceUid, ?\Closure $primaryRule = null, ?\Closure $project = null): array
    {
        $options = fn (Get $get, ?Model $record): array => Addressables::options($clientInstanceUid($get, $record), $record instanceof Trackable ? $record : null);

        return [
            self::field('primary_recipients', 'secondary_recipients', $options, $project)
                ->label(__('Destinataires principaux'))
                ->helperText(__('Prévenus tout de suite, par courriel ou dans l’application selon leurs réglages.'))
                ->rules($primaryRule !== null ? [$primaryRule] : []),
            self::field('secondary_recipients', 'primary_recipients', $options, $project)
                ->label(__('Destinataires secondaires'))
                ->helperText(__('Prévenus seulement dans le bilan.')),
        ];
    }

    /** La liste des principaux d'un mouvement (une publication…), parmi une partie des personnes possibles. */
    public static function primaries(string $name, \Closure $options, ?\Closure $project = null): Select
    {
        return self::field($name, null, $options, $project)->label(__('Destinataires principaux'));
    }

    /**
     * Les clés (`Addressables`) de l'équipe d'un projet, parmi les personnes possibles : on ne propose pas quelqu'un
     * qu'on ne peut plus nommer (un correspondant qui a perdu son droit…).
     *
     * @param  array<string, array<string, string>>  $options  les personnes possibles, par groupe
     * @return array<int, string>
     */
    public static function teamKeys(?Project $project, array $options): array
    {
        if ($project === null) {
            return [];
        }

        $known = array_keys(array_merge(...array_values($options ?: [[]])));

        return $project->participants()
            ->map(fn (Actor $actor): string => Addressables::keyFor($actor))
            ->unique()
            ->filter(fn (string $key): bool => in_array($key, $known, true))
            ->values()->all();
    }

    private static function field(string $name, ?string $other, \Closure $options, ?\Closure $project): Select
    {
        // Ajoute des personnes à la liste, sauf celles déjà dans l'autre.
        $add = fn (Get $get, Set $set, array $keys) => $set($name, array_values(array_unique([
            ...(array) $get($name),
            ...array_diff($keys, $other !== null ? (array) $get($other) : []),
        ])));

        return Select::make($name)
            ->multiple()
            ->searchable()
            ->options($options)
            ->hintActions([
                Action::make('everyone_'.$name)
                    ->label(__('Tout le monde'))
                    ->icon('heroicon-m-user-group')
                    ->action(fn (Get $get, Set $set, Select $component) => $add($get, $set, Addressables::everyone($component->getOptions()))),
                Action::make('team_'.$name)
                    ->label(__('Toute l’équipe projet'))
                    ->icon('heroicon-m-users')
                    ->visible(fn (Get $get, ?Model $record): bool => $project !== null && $project($get, $record) !== null)
                    ->action(fn (Get $get, Set $set, ?Model $record, Select $component) => $add($get, $set, self::teamKeys($project($get, $record), $component->getOptions()))),
                Action::make('clear_'.$name)
                    ->label(__('Vider'))
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->action(fn (Set $set) => $set($name, [])),
            ]);
    }
}
