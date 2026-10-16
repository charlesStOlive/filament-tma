<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Illuminate\Support\Str;

/**
 * Le formulaire d'une saisie de temps, chez le Support (`TimeEntriesRelationManager`) comme pour un membre du Support
 * dans une application cliente (`TimelineActions::agentTime()`) : le jour, la durée (les raccourcis la remplissent),
 * et ce sur quoi porte le temps. Le temps ne dit pas lui-même ce qui a été fait : il porte sur un message ou une note
 * de la fiche, ou, sans l'un ni l'autre, sur la fiche entière ; sur une tâche, aussi sur le message écrit ici
 * (`message`). Sur un ticket, pas de texte : un message y va avec un passage.
 */
final class TimeEntryForm
{
    /** Les raccourcis de durée : libellé, valeur, unité. */
    private const PRESETS = [
        '10m' => ['10 min', 10, Duration::MINUTES],
        '15m' => ['15 min', 15, Duration::MINUTES],
        '20m' => ['20 min', 20, Duration::MINUTES],
        '30m' => ['30 min', 30, Duration::MINUTES],
        '45m' => ['45 min', 45, Duration::MINUTES],
        '1h' => ['1 h', 1, Duration::HOURS],
        '2h' => ['2 h', 2, Duration::HOURS],
        '3h' => ['3 h', 3, Duration::HOURS],
        'halfDay' => ['½ j', 0.5, Duration::DAYS],
        '1d' => ['1 j', 1, Duration::DAYS],
    ];

    /**
     * @param  Closure(): Trackable  $subject  le ticket ou la tâche
     * @return array<int, Component>
     */
    public static function components(Closure $subject): array
    {
        return [
            self::presets(),
            Grid::make(3)->columnSpanFull()->schema([
                DatePicker::make('worked_on')->label(__('Le'))->required()->default(now())->maxDate(now()->addDay())
                    ->hintAction(Help::action('tma.temps.saisie', __('Quel jour ?'))),
                TextInput::make('duration')->label(__('Durée'))->required()->numeric()->minValue(0.01)->step('any')
                    ->hintAction(Help::action('tma.temps.saisie', __('Minutes, heures ou jours'))),
                Select::make('unit')->label(__('En'))->options(Duration::units())->required()
                    ->default(config('filament-tma.time.display_unit', Duration::HOURS)),
            ]),
            Select::make('entry_id')->label(__('Sur'))
                ->options(fn (): array => self::entryOptions($subject()))
                ->searchable()
                ->placeholder(fn (): string => self::canWrite($subject()) ? __('Un nouveau message') : __('La fiche entière'))
                ->helperText(fn (): string => self::canWrite($subject())
                    ? __('Un message ou une note de la fiche, ou un nouveau message.')
                    : __('Un message ou une note de la fiche, ou la fiche entière.'))
                ->hintAction(Help::action('tma.temps.saisie', __('Sur quoi porte le temps ?')))
                ->columnSpanFull(),
            RichEditor::make('message')->label(__('Ce qui a été fait'))
                ->placeholder(__('Écrit dans l’historique de la fiche, avec ce temps…'))
                ->helperText(__('Laissé vide, le temps porte sur la fiche entière.'))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->visible(fn (): bool => self::canWrite($subject()))
                // Un message ou une note choisi : le champ disparaît aussitôt, dans le navigateur (son texte est ignoré).
                ->visibleJs(<<<'JS'
                    ! $get('entry_id')
                    JS)
                ->columnSpanFull(),
            Toggle::make('billable')->label(__('Facturable'))->default(true)
                ->hintAction(Help::action('tma.temps.facturable', __('Facturable ou non ?'))),
        ];
    }

    /** La durée saisie, en minutes. */
    public static function minutes(array $data): int
    {
        return Duration::toMinutes($data['duration'], $data['unit']);
    }

    /** Le nouveau message à écrire avec le temps, s'il y en a un (pas de message choisi, et un texte). */
    public static function message(array $data): ?string
    {
        return blank($data['entry_id'] ?? null) && ! Html::isBlank($data['message'] ?? null) ? (string) $data['message'] : null;
    }

    /** Une ligne de boutons au-dessus de la durée : un clic la remplit (valeur et unité). */
    private static function presets(): Actions
    {
        return Actions::make(collect(self::PRESETS)
            ->map(fn (array $preset, string $name): Action => Action::make('duration_'.$name)
                ->label($preset[0])
                ->outlined()
                ->color('gray')
                ->size(Size::ExtraSmall)
                ->action(function (Set $set) use ($preset): void {
                    $set('duration', $preset[1]);
                    $set('unit', $preset[2]);
                }))
            ->values()
            ->all())
            ->key('durationPresets')
            ->columnSpanFull();
    }

    /** On peut écrire un nouveau message avec le temps : sur une tâche (ni annulée), jamais sur un ticket. */
    private static function canWrite(Trackable $subject): bool
    {
        return app(Timeline::class)->canAdd($subject, EntryType::Comment, Context::current());
    }

    /**
     * Les messages (réponses comprises), puis les notes de la fiche que l'utilisateur peut lire, du plus récent au
     * plus ancien.
     *
     * @return array<string, array<int, string>>
     */
    private static function entryOptions(Trackable $subject): array
    {
        [$notes, $messages] = $subject->entries()
            ->visibleTo(auth()->user())
            ->with('author')
            ->latest()
            ->get()
            ->partition(fn (Entry $entry): bool => $entry->isNote());

        return array_filter([
            __('Messages') => $messages->mapWithKeys(fn (Entry $entry): array => [$entry->getKey() => self::entryLabel($entry)])->all(),
            __('Notes') => $notes->mapWithKeys(fn (Entry $entry): array => [$entry->getKey() => self::entryLabel($entry)])->all(),
        ]);
    }

    /** « Note n° 3 — Penser au cache » ; « Message de Ada du 12/10 — Je reproduis… ». */
    public static function entryLabel(Entry $entry, bool $short = false): string
    {
        $label = $entry->isNote()
            ? $entry->noteLabel()
            : __('Message de :author du :date', ['author' => $entry->author?->name ?? '?', 'date' => Dates::format($entry->created_at, 'd/m')]);

        return $label.' — '.Str::limit(Html::toText($entry->body), $short ? 40 : 80);
    }
}
