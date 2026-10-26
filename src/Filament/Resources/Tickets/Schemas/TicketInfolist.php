<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Enums\TicketOpening;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsSection;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketInfolist
{
    /**
     * Le volet principal de la fiche : l'historique (la demande, puis les échanges, chacun avec ses notes), et
     * dessous, à part, le nouveau message.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-tma::common.history'))
                ->columnSpanFull()
                ->headerActions([Help::title('tma.chronologie.notes', __('filament-tma::common.messages_and_notes'))])
                ->schema([
                    ViewEntry::make('timeline')
                        ->hiddenLabel()
                        ->view('filament-tma::timeline.ticket'),
                ]),
            self::composerSection(),
        ]);
    }

    /** Le nouveau message, sous l'historique (`Filament\Concerns\HasComposer`). */
    public static function composerSection(): Section
    {
        return Section::make(__('filament-tma::common.new_message'))
            ->columnSpanFull()
            ->headerActions([Help::title('tma.chronologie', __('filament-tma::tickets.help.who_answers'))])
            ->visible(fn ($livewire): bool => method_exists($livewire, 'hasComposer') && $livewire->hasComposer())
            ->schema([EmbeddedSchema::make('composer')]);
    }

    /**
     * Le volet latéral de la fiche (voir `ViewTicket`) : où en est le ticket, et qui doit agir.
     *
     * @return array<int, Component>
     */
    public static function side(): array
    {
        return [
            Section::make(__('filament-tma::common.follow_up'))
                ->columnSpanFull()
                ->headerActions([Help::title('tma.comprendre.la-main', __('filament-tma::tickets.help.who_acts'))])
                ->schema([
                    TextEntry::make('agent_mode')->hiddenLabel()
                        ->state(fn ($record): ?string => Context::current()->forSubject($record)->isAgent()
                            ? __('filament-tma::common.acting_for_support')
                            : null)
                        ->visible(fn ($record): bool => Context::current()->forSubject($record)->isAgent())
                        ->badge()->color('info')->icon('heroicon-m-lifebuoy')
                        ->hintAction(Help::action('tma.agents', __('filament-tma::common.support_member'))),
                    TextEntry::make('state')->label(__('filament-tma::common.state'))->badge()
                        ->helperText(fn (Ticket $record): ?string => $record->state->getDescription()),
                    TextEntry::make('waiting_on')->label(__('filament-tma::common.waiting_for'))
                        ->state(fn (Ticket $record): ?string => $record->waitingOnFor(auth()->user()))
                        ->placeholder('—'),
                    TextEntry::make('priority')->label(__('filament-tma::common.priority'))->badge(),
                    // « Demandeur », ou « Adressé à » quand le Support écrit au client ; au nom d'un client, qui l'a écrit.
                    TextEntry::make('requester.name')->label(fn (Ticket $record): string => $record->opening->requesterLabel())
                        ->helperText(fn (Ticket $record): ?string => match (true) {
                            $record->opening === TicketOpening::OnBehalf && $record->createdBy !== null => __('filament-tma::tickets.created_on_behalf', ['author' => $record->createdBy->name]),
                            $record->opening === TicketOpening::Notice => __('filament-tma::tickets.for_information'),
                            default => null,
                        }),
                    TextEntry::make('assignee.name')->label(__('filament-tma::common.support_contact'))->placeholder('—'),
                    TextEntry::make('paused_until')->label(__('filament-tma::common.resumes_on'))->dateTime('d/m/Y H:i')
                        ->visible(fn (Ticket $record): bool => $record->paused_until !== null),
                    TextEntry::make('sourceInstance.name')->label(__('filament-tma::common.application'))
                        ->visible(fn (): bool => Tma::isMaster()),
                    TextEntry::make('task.number')->label(__('filament-tma::tickets.from_task'))
                        ->formatStateUsing(fn (Ticket $record): string => $record->task->number.' — '.$record->task->title)
                        ->url(fn (Ticket $record): ?string => TaskResource::canView($record->task) ? TaskResource::getUrl('view', ['record' => $record->task]) : null)
                        ->visible(fn (Ticket $record): bool => $record->task !== null),
                    TextEntry::make('sync_status')->label(__('filament-tma::common.sync'))->badge()
                        ->hintAction(Help::action('tma.synchronisation.erreurs', __('filament-tma::common.status_help')))
                        ->visible(fn (Ticket $record): bool => $record->isShared())
                        ->helperText(fn (Ticket $record): ?string => $record->last_sync_error),
                    TextEntry::make('created_at')->label(__('filament-tma::tickets.created_at'))->dateTime('d/m/Y H:i'),
                    TextEntry::make('last_activity_at')->label(__('filament-tma::common.last_activity'))->since(),
                ]),
            RecipientsSection::make(),
        ];
    }
}
