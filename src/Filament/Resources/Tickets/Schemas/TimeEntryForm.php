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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Illuminate\Support\Str;

/**
 * Le formulaire d'une saisie de temps, chez le Support (`TimeEntriesRelationManager`) comme pour un membre du Support
 * dans une application cliente (`TimelineActions::agentTime()`) : le jour, la durée en jours, heures et minutes (« 2 j
 * 2 h 5 min » ; les raccourcis la remplissent, le total s'affiche dessous), et ce sur quoi porte le temps. Le temps ne dit pas lui-même ce qui a été fait : il porte sur un message ou une note
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

    /** Les trois champs de la durée. */
    public const DAYS = 'duration_days';

    public const HOURS = 'duration_hours';

    public const MINUTES = 'duration_minutes';

    /** Au plus 24 heures par saisie (`Workflow\TimeLog`). */
    public const MAX_MINUTES = 24 * 60;

    /**
     * @param  Closure(): Trackable  $subject  le ticket ou la tâche
     * @return array<int, Component>
     */
    public static function components(Closure $subject): array
    {
        return [
            self::presets(),
            Grid::make(['default' => 3, 'sm' => 4])->columnSpanFull()->schema([
                DatePicker::make('worked_on')->label(__('Le'))->required()->default(now())->maxDate(now()->addDay())
                    ->hintAction(Help::action('tma.temps.saisie', __('Quel jour ?')))
                    ->columnSpan(['default' => 3, 'sm' => 1]),
                self::part(self::DAYS, __('Jours'), __('j')),
                self::part(self::HOURS, __('Heures'), __('h')),
                self::part(self::MINUTES, __('Minutes'), __('min'))
                    // Une seule règle pour les trois : il faut une durée (les minutes, sans jours ni heures).
                    ->required(fn (Get $get): bool => self::minutes([self::DAYS => $get(self::DAYS), self::HOURS => $get(self::HOURS)]) <= 0)
                    ->markAsRequired(false)
                    ->validationMessages(['required' => __('Saisissez une durée.')])
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $minutes = self::minutes([self::DAYS => $get(self::DAYS), self::HOURS => $get(self::HOURS), self::MINUTES => $value]);

                        if ($minutes <= 0) {
                            $fail(__('Saisissez une durée.'));
                        } elseif ($minutes > self::MAX_MINUTES) {
                            // Une saisie, une intervention d'un jour (`TimeLog`).
                            $fail(__('Une saisie va jusqu’à 24 heures (:max) : saisissez les autres jours à part.', ['max' => Duration::format(self::MAX_MINUTES)]));
                        }
                    }),
                // Le total, tel qu'il s'affichera (« Soit 1 j 2 h », une journée de 7 h).
                Text::make(fn (Get $get): string => __('Soit :total (une journée : :day).', [
                    'total' => Duration::format(self::minutes([self::DAYS => $get(self::DAYS), self::HOURS => $get(self::HOURS), self::MINUTES => $get(self::MINUTES)])),
                    'day' => Duration::format(Duration::dayMinutes(), Duration::HOURS),
                ]))->color('gray')->columnSpanFull(),
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

    /** La durée saisie, en minutes : les jours (de `time.day_minutes`), les heures et les minutes. */
    public static function minutes(array $data): int
    {
        $number = fn (mixed $value): float => blank($value) ? 0.0 : (float) str_replace(',', '.', (string) $value);

        return (int) round($number($data[self::DAYS] ?? null) * Duration::dayMinutes()
            + $number($data[self::HOURS] ?? null) * 60
            + $number($data[self::MINUTES] ?? null));
    }

    /**
     * Une durée en minutes, dans les trois champs : 2 j 2 h 5 min ; vide plutôt que 0.
     *
     * @return array<string, int|null>
     */
    public static function fill(int $minutes): array
    {
        $rest = $minutes % Duration::dayMinutes();

        return [
            self::DAYS => intdiv($minutes, Duration::dayMinutes()) ?: null,
            self::HOURS => intdiv($rest, 60) ?: null,
            self::MINUTES => $rest % 60 ?: null,
        ];
    }

    /** Un des trois champs de la durée : un nombre, son unité à droite ; le total se refait quand on le quitte. */
    private static function part(string $name, string $label, string $suffix): TextInput
    {
        return TextInput::make($name)->label($label)
            ->numeric()->minValue(0)->step('any')
            ->suffix($suffix)
            ->placeholder('0')
            ->live(onBlur: true);
    }

    /** Le nouveau message à écrire avec le temps, s'il y en a un (pas de message choisi, et un texte). */
    public static function message(array $data): ?string
    {
        return blank($data['entry_id'] ?? null) && ! Html::isBlank($data['message'] ?? null) ? (string) $data['message'] : null;
    }

    /** Une ligne de boutons au-dessus de la durée : un clic la remplit (jours, heures, minutes). */
    private static function presets(): Actions
    {
        return Actions::make(collect(self::PRESETS)
            ->map(fn (array $preset, string $name): Action => Action::make('duration_'.$name)
                ->label($preset[0])
                ->outlined()
                ->color('gray')
                ->size(Size::ExtraSmall)
                ->action(function (Set $set) use ($preset): void {
                    foreach (self::fill(Duration::toMinutes($preset[1], $preset[2])) as $field => $value) {
                        $set($field, $value);
                    }
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
