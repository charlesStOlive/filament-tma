<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\TicketOpening;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Attachments;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TicketForm
{
    /** Le Support retouche le titre et la priorité ; la demande elle-même (texte, fichiers) reste à son auteur. */
    public static function demandLocked(string $operation, ?Ticket $record): bool
    {
        return $operation === 'edit' && $record !== null && ! app(TicketWorkflow::class)->mayEditDemand($record, Context::current());
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Maître : comment le Support écrit au client (au nom de l'un de ses correspondants, pour lui demander ou
            // proposer quelque chose, pour l'informer) ; puis l'application et la personne.
            ToggleButtons::make('opening')->hiddenLabel()
                ->options(collect(TicketOpening::forSupport())->mapWithKeys(fn (TicketOpening $opening): array => [$opening->value => $opening->getLabel()])->all())
                ->icons(collect(TicketOpening::forSupport())->mapWithKeys(fn (TicketOpening $opening): array => [$opening->value => $opening->getIcon()])->all())
                ->default(TicketOpening::OnBehalf->value)
                ->inline()
                ->required()
                ->live()
                ->helperText(fn (Get $get): ?string => TicketOpening::tryFrom((string) $get('opening'))?->getDescription())
                ->hintAction(Help::action('tma.tickets.prepares', __('filament-tma::tickets.help.three_ways')))
                ->visible(fn (string $operation): bool => Tma::isMaster() && $operation === 'create')
                ->columnSpanFull(),
            Grid::make(3)->columnSpanFull()
                ->visible(fn (string $operation): bool => Tma::isMaster() && $operation === 'create')
                ->schema([
                    Select::make('instance_uid')->label(__('filament-tma::tickets.fields.client_application'))
                        ->options(fn (): array => Instance::query()->active()->orderBy('name')->pluck('name', 'uid')->all())
                        ->required()
                        ->live()
                        ->hintAction(Help::action('tma.tickets.prepares', __('filament-tma::tickets.help.prepare_for_client'))),
                    Select::make('requester_id')->label(fn (Get $get): string => (TicketOpening::tryFrom((string) $get('opening')) ?? TicketOpening::OnBehalf)->requesterLabel())
                        ->options(function (Get $get): array {
                            $instance = Instance::forUid($get('instance_uid'));

                            return $instance === null ? [] : rescue(
                                fn (): array => collect(RemoteUsers::for($instance))->mapWithKeys(fn (array $u): array => [$u['id'] => $u['name'].($u['email'] ? ' — '.$u['email'] : '')])->all(),
                                [],
                            );
                        })
                        ->helperText(__('filament-tma::tickets.fields.requester_help'))
                        ->searchable()
                        ->required()
                        ->hintAction(Help::action('tma.tickets.prepares', __('filament-tma::tickets.help.who_can_be_chosen'))),
                    DateTimePicker::make('publish_at')->label(__('filament-tma::common.scheduled_publication'))
                        ->helperText(__('filament-tma::tickets.fields.publish_at_help'))
                        ->minDate(now())->seconds(false)
                        ->hintAction(Help::action('tma.tickets.prepares', __('filament-tma::tickets.help.when_client_sees'))),
                ]),
            TextInput::make('title')
                ->label(__('filament-tma::common.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make('priority')
                    ->label(__('filament-tma::common.priority'))
                    ->options(Priority::class)
                    ->default(Priority::Normal->value)
                    ->required()
                    ->hintAction(Help::action('tma.tickets.priorite-echeance', __('filament-tma::tickets.help.priority'))),
            ])->columnSpanFull(),
            RichEditor::make('description')
                ->label(__('filament-tma::common.description'))
                ->disabled(fn (string $operation, ?Ticket $record): bool => self::demandLocked($operation, $record))
                ->hintAction(Help::action('tma.chronologie.mise-en-forme', __('filament-tma::common.text_formatting')))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            Attachments::field(SpatieMediaLibraryFileUpload::make('attachments'))
                ->label(__('filament-tma::common.attachments'))
                ->disabled(fn (string $operation, ?Ticket $record): bool => self::demandLocked($operation, $record))
                ->hintAction(Help::action('tma.chronologie.fichiers', __('filament-tma::common.files_help')))
                ->collection(Ticket::ATTACHMENTS_COLLECTION)
                ->disk(config('filament-tma.attachments.disk', 'local'))
                ->visibility('private')
                ->multiple()
                ->downloadable()
                ->maxSize((int) config('filament-tma.attachments.max_size_kb', 10240))
                ->maxFiles((int) config('filament-tma.attachments.max_files', 10))
                ->columnSpanFull(),
        ]);
    }
}
