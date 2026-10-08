<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make(__('Tâche'))->columnSpan(['lg' => 2])->schema([
                    TextEntry::make('description')->hiddenLabel()->placeholder(__('Pas de description.'))
                        ->state(fn (ProjectTask $record): ?HtmlString => filled($record->description) ? new HtmlString($record->renderRichContent('description')) : null)
                        ->prose(),
                    ViewEntry::make('attachments')->hiddenLabel()->view('filament-tma::attachments')
                        ->viewData(['collection' => ProjectTask::ATTACHMENTS_COLLECTION]),
                ]),
                Section::make(__('Suivi'))->columnSpan(1)->schema([
                    TextEntry::make('state')->label(__('État'))->badge()
                        ->helperText(fn (ProjectTask $record): ?string => $record->state->getDescription()),
                    TextEntry::make('targetInstance.name')->label(__('Application'))->visible(fn (): bool => Tma::isMaster()),
                    TextEntry::make('project')->label(__('Projet'))->placeholder('—'),
                    TextEntry::make('period')->label(__('Période'))
                        ->state(fn (ProjectTask $record): ?string => $record->starts_on || $record->ends_on
                            ? trim(($record->starts_on?->format('d/m/Y') ?? '…').' → '.($record->ends_on?->format('d/m/Y') ?? '…'))
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('publish_at')->label(__('Publication prévue'))->dateTime('d/m/Y H:i')
                        ->visible(fn (ProjectTask $record): bool => Tma::isMaster() && $record->publish_at !== null),
                    TextEntry::make('published_at')->label(__('Publiée le'))->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('time')->label(__('Temps passé'))
                        ->state(fn (ProjectTask $record): string => Duration::format(TimeTotals::for($record)['total_minutes'])),
                    TextEntry::make('sync_status')->label(__('Synchro'))->badge()
                        ->visible(fn (ProjectTask $record): bool => $record->isShared())
                        ->helperText(fn (ProjectTask $record): ?string => $record->last_sync_error),
                    TextEntry::make('last_activity_at')->label(__('Dernière activité'))->since(),
                ]),
            ]),
            Section::make(__('Chronologie'))->columnSpanFull()->schema([
                ViewEntry::make('timeline')->hiddenLabel()->view('filament-tma::timeline.ticket'),
            ]),
        ]);
    }
}
