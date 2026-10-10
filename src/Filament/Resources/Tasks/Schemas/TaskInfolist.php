<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TaskInfolist
{
    /** Le volet principal de la fiche : la tâche, puis sa chronologie. */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Tâche'))->columnSpanFull()->schema([
                TextEntry::make('description')->hiddenLabel()->placeholder(__('Pas de description.'))
                    ->state(fn (ProjectTask $record): ?HtmlString => filled($record->description) ? new HtmlString($record->renderRichContent('description')) : null)
                    ->prose(),
                ViewEntry::make('attachments')->hiddenLabel()->view('filament-tma::attachments')
                    ->viewData(['collection' => ProjectTask::ATTACHMENTS_COLLECTION]),
            ]),
            Section::make(__('Chronologie'))->columnSpanFull()
                ->headerActions([Help::title('tma.chronologie.commentaire-ou-note', __('Message ou note interne ?'))])
                ->schema([
                    ViewEntry::make('timeline')->hiddenLabel()->view('filament-tma::timeline.ticket'),
                    // La zone de réponse de la page (`Filament\Concerns\HasComposer`), sous la chronologie.
                    EmbeddedSchema::make('composer')
                        ->visible(fn ($livewire): bool => method_exists($livewire, 'hasComposer') && $livewire->hasComposer()),
                ]),
        ]);
    }

    /**
     * Le volet latéral de la fiche (voir `ViewTask`) : où en est la tâche.
     *
     * @return array<int, Component>
     */
    public static function side(): array
    {
        return [
            Section::make(__('Suivi'))->columnSpanFull()->schema([
                TextEntry::make('agent_mode')->hiddenLabel()
                    ->state(fn ($record): ?string => Context::current()->forSubject($record)->isAgent()
                        ? __('Vous intervenez ici au nom du Support.')
                        : null)
                    ->visible(fn ($record): bool => Context::current()->forSubject($record)->isAgent())
                    ->badge()->color('info')->icon('heroicon-m-lifebuoy')
                    ->hintAction(Help::action('tma.agents', __('Membre du Support'))),
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
                    ->hintAction(Help::action('tma.synchronisation.erreurs', __('Que veut dire ce statut ?')))
                    ->visible(fn (ProjectTask $record): bool => $record->isShared())
                    ->helperText(fn (ProjectTask $record): ?string => $record->last_sync_error),
                TextEntry::make('last_activity_at')->label(__('Dernière activité'))->since(),
            ]),
        ];
    }
}
