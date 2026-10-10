<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Maître : un ticket préparé pour une application cliente, au nom de l'un de ses utilisateurs.
            Grid::make(3)->columnSpanFull()
                ->visible(fn (string $operation): bool => Tma::isMaster() && $operation === 'create')
                ->schema([
                    Select::make('instance_uid')->label(__('Application du client'))
                        ->options(fn (): array => Instance::query()->active()->orderBy('name')->pluck('name', 'uid')->all())
                        ->required()
                        ->live()
                        ->hintAction(Help::action('tma.tickets.prepares', __('Préparer un ticket pour un client'))),
                    Select::make('requester_id')->label(__('Demandeur'))
                        ->options(function (Get $get): array {
                            $instance = Instance::forUid($get('instance_uid'));

                            return $instance === null ? [] : rescue(
                                fn (): array => collect(RemoteUsers::for($instance))->mapWithKeys(fn (array $u): array => [$u['id'] => $u['name'].($u['email'] ? ' — '.$u['email'] : '')])->all(),
                                [],
                            );
                        })
                        ->helperText(__('Les utilisateurs de l’application qui ont accès au Support.'))
                        ->searchable()
                        ->required()
                        ->hintAction(Help::action('tma.tickets.prepares', __('Qui peut être demandeur ?'))),
                    DateTimePicker::make('publish_at')->label(__('Publication programmée'))
                        ->helperText(__('Vide : vous publierez à la main.'))
                        ->minDate(now())->seconds(false)
                        ->hintAction(Help::action('tma.tickets.prepares', __('Quand le client le verra-t-il ?'))),
                ]),
            TextInput::make('title')
                ->label(__('Titre'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make('priority')
                    ->label(__('Priorité'))
                    ->options(Priority::class)
                    ->default(Priority::Normal->value)
                    ->required()
                    ->hintAction(Help::action('tma.tickets.priorite-echeance', __('À quoi sert la priorité ?'))),
            ])->columnSpanFull(),
            RichEditor::make('description')
                ->label(__('Description'))
                ->hintAction(Help::action('tma.chronologie.mise-en-forme', __('Mise en forme du texte')))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('attachments')
                ->label(__('Pièces jointes'))
                ->hintAction(Help::action('tma.chronologie.fichiers', __('Quels fichiers ?')))
                ->collection(Ticket::ATTACHMENTS_COLLECTION)
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
