<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsFields;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Help;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    /** L'application du projet choisi. */
    public static function instanceUid(Get $get): ?string
    {
        return Project::query()->find($get('project_id'))?->target_instance_uid;
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
                ->hintAction(Help::action('tma.projets', __('Les projets')))
                ->live()
                ->columnSpanFull(),
            // Ses destinataires : son auteur d'office ; avant sa publication, au moins un principal chez le client.
            Grid::make(2)->columnSpanFull()->schema(RecipientsFields::make(
                fn (Get $get): ?string => self::instanceUid($get),
                fn (?ProjectTask $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                    if (! $record?->isShared() && ! collect((array) $value)->contains(fn (mixed $key): bool => str_starts_with((string) $key, 'c:'))) {
                        $fail(__('Choisissez au moins un destinataire principal chez le client, ou « Tout le monde ».'));
                    }
                },
            )),
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
