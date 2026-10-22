<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsFields;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Help;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    /** L'application du projet choisi. */
    public static function instanceUid(Get $get): ?string
    {
        return Project::query()->find($get('project_id'))?->target_instance_uid;
    }

    /**
     * Les destinataires, avec « Toute l'équipe projet » ; à la création, l'équipe du projet d'office.
     *
     * @return array<int, Select>
     */
    private static function recipients(): array
    {
        [$primary, $secondary] = RecipientsFields::make(
            fn (Get $get): ?string => self::instanceUid($get),
            fn (?ProjectTask $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                if (! $record?->isShared() && ! collect((array) $value)->contains(fn (mixed $key): bool => str_starts_with((string) $key, 'c:'))) {
                    $fail(__('Choisissez au moins un destinataire principal chez le client, « Toute l’équipe projet » ou « Tout le monde ».'));
                }
            },
            fn (Get $get): ?Project => Project::query()->find($get('project_id')),
        );

        return [$primary->default(fn (): array => self::teamOf(request()->integer('project') ?: null)), $secondary];
    }

    /**
     * Les clés de l'équipe d'un projet (`RecipientsFields::teamKeys()`), parmi les personnes qu'on peut nommer pour son
     * application.
     *
     * @return array<int, string>
     */
    public static function teamOf(mixed $projectId): array
    {
        $project = filled($projectId) ? Project::query()->find($projectId) : null;

        return $project === null ? [] : RecipientsFields::teamKeys($project, Addressables::options($project->target_instance_uid));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Le projet porte l'application destinataire ; une tâche publiée ne change plus d'application.
            Select::make('project_id')->label(__('Projet'))
                ->options(fn (?ProjectTask $record): array => Project::query()->with('targetInstance')
                    ->when($record?->isShared(), fn (Builder $q): Builder => $q->where('target_instance_uid', $record->target_instance_uid))
                    ->orderBy('title')
                    ->get()
                    ->mapWithKeys(fn (Project $project): array => [$project->getKey() => $project->label()])
                    ->all())
                ->default(fn (): ?int => request()->integer('project') ?: null)
                ->searchable()
                ->required()
                ->createOptionForm(ProjectResource::formFields())
                ->createOptionUsing(fn (array $data): int => ProjectResource::createProject($data)->getKey())
                ->createOptionModalHeading(__('Nouveau projet'))
                // Le projet choisi se modifie sur place, dans un volet glissant : sa description, sa fourchette, ses
                // participants (son application ne change plus une fois des tâches dedans).
                ->editOptionForm(fn (Get $get): array => [
                    TextInput::make('title')->label(__('Titre'))->required()->maxLength(255),
                    ...ProjectResource::detailFields(
                        fn (): ?string => self::instanceUid($get),
                        fn (): ?Project => Project::query()->find($get('project_id')),
                    ),
                ])
                ->fillEditOptionActionFormUsing(fn (Get $get): ?array => ($project = Project::query()->find($get('project_id'))) !== null ? ProjectResource::formData($project) : null)
                ->updateOptionUsing(function (array $data, Get $get): void {
                    if (($project = Project::query()->find($get('project_id'))) !== null && ProjectResource::canEdit($project)) {
                        ProjectResource::updateProject($project, $data);
                    }
                })
                ->editOptionModalHeading(__('Modifier le projet'))
                ->editOptionAction(fn (Action $action): Action => $action->slideOver()->modalWidth('lg')
                    ->visible(fn (Select $component): bool => ($project = Project::query()->find($component->getState())) !== null && ProjectResource::canEdit($project)))
                ->hintAction(Help::action('tma.projets', __('Les projets')))
                ->live()
                // Une nouvelle tâche prend l'équipe de son projet pour destinataires principaux ; un autre projet choisi
                // ensuite apporte la sienne, tant qu'on n'a pas changé la liste à la main.
                ->afterStateUpdated(function (mixed $old, mixed $state, Get $get, Set $set, ?ProjectTask $record): void {
                    if ($record !== null) {
                        return;
                    }

                    $current = (array) $get('primary_recipients');

                    if ($current === [] || array_values($current) === self::teamOf($old)) {
                        $set('primary_recipients', self::teamOf($state));
                    }
                })
                ->columnSpanFull(),
            // Ses destinataires : son auteur d'office ; avant sa publication, au moins un principal chez le client.
            Grid::make(2)->columnSpanFull()->schema(self::recipients()),
            TextInput::make('title')->label(__('Titre'))->required()->maxLength(255)->columnSpanFull(),
            Grid::make(2)->columnSpanFull()->schema([
                DatePicker::make('starts_on')->label(__('Début'))
                    ->helperText(__('Vide : le jour de la création.'))
                    ->hintAction(Help::action('tma.taches.periode', __('Début et échéance'))),
                // Comparée au début saisi, sinon au jour de la création : sans début, la règle de Filament comparerait
                // à une valeur vide et refuserait toute échéance.
                DatePicker::make('due_on')->label(__('Échéance prévue'))
                    ->helperText(__('Facultative : une date visée, pas une fin fixe.'))
                    ->afterOrEqual(fn (Get $get, ?ProjectTask $record): string => $get('starts_on') ?: ($record?->created_at ?? now())->toDateString())
                    ->validationMessages(['after_or_equal' => __('L’échéance ne peut pas précéder le début.')])
                    // Facultative : elle ne s'affiche qu'à la demande (« Tout afficher »).
                    ->onDemand(),
                DateTimePicker::make('publish_at')->label(__('Publication programmée'))
                    ->helperText(__('Vide : la tâche part chez le client dès l’enregistrement. Avec une date, elle reste en brouillon, invisible du client, et se publie à l’heure dite.'))
                    ->minDate(now())->seconds(false)
                    ->hintAction(Help::action('tma.taches.publication', __('Publier une tâche')))
                    ->visible(fn (?ProjectTask $record): bool => $record === null || ! $record->isShared()),
            ]),
            RichEditor::make('description')->label(__('Description'))
                ->hintAction(Help::action('tma.chronologie.mise-en-forme', __('Mise en forme du texte')))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('attachments')->label(__('Pièces jointes'))
                ->hintAction(Help::action('tma.chronologie.fichiers', __('Quels fichiers ?')))
                ->collection(ProjectTask::ATTACHMENTS_COLLECTION)
                ->disk(config('filament-tma.attachments.disk', 'local'))
                ->visibility('private')
                ->multiple()
                ->downloadable()
                ->acceptedFileTypes(config('filament-tma.attachments.accepted_types', []))
                ->maxSize((int) config('filament-tma.attachments.max_size_kb', 10240))
                ->maxFiles((int) config('filament-tma.attachments.max_files', 10))
                ->columnSpanFull(),
        ]);
    }
}
