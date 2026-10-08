<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('Titre'))->required()->maxLength(255)->columnSpanFull(),
            Grid::make(2)->columnSpanFull()->schema([
                Select::make('target_instance_uid')->label(__('Application destinataire'))
                    ->options(fn (): array => Instance::query()->active()->orderBy('name')->pluck('name', 'uid')->all())
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('project')->label(__('Projet'))->maxLength(255),
                DatePicker::make('starts_on')->label(__('Début')),
                DatePicker::make('ends_on')->label(__('Fin prévue'))->afterOrEqual('starts_on'),
                DateTimePicker::make('publish_at')->label(__('Publication programmée'))
                    ->helperText(__('Laisser vide pour publier à la main. La tâche reste invisible du client jusque-là.'))
                    ->minDate(now())->seconds(false)
                    ->visible(fn (?ProjectTask $record): bool => $record === null || ! $record->isShared()),
            ]),
            RichEditor::make('description')->label(__('Description'))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('attachments')->label(__('Pièces jointes'))
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
