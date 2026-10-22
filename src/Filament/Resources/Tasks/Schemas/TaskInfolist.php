<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketInfolist;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsSection;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;

class TaskInfolist
{
    /** Le volet principal de la fiche : l'historique (la tâche, puis les échanges), et dessous le nouveau message. */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Historique'))->columnSpanFull()
                ->headerActions([Help::title('tma.chronologie.notes', __('Messages et notes'))])
                ->schema([
                    ViewEntry::make('timeline')->hiddenLabel()->view('filament-tma::timeline.ticket'),
                ]),
            TicketInfolist::composerSection(),
        ]);
    }

    /**
     * Le volet latéral de la fiche (voir `ViewTask`) : où en est la tâche et dans quel projet (sa fourchette, son temps ;
     * le maître le modifie dans un volet glissant), son calendrier, les tickets qui en sont partis, son temps. Pas de « qui doit agir » : c'est le Support qui fait la tâche.
     *
     * Le temps passé (`$time`, un seul bloc : voir `HasInfoPane::timeBlock()`) vient en tête dans le maître ; chez le
     * client, il ferme le volet, en lecture. Le calendrier et l'application ne s'affichent qu'à la demande (« Tout
     * afficher »).
     *
     * @return array<int, Component>
     */
    public static function side(Component $time): array
    {
        $agentMode = TextEntry::make('agent_mode')->hiddenLabel()
            ->state(fn ($record): ?string => Context::current()->forSubject($record)->isAgent()
                ? __('Vous intervenez ici au nom du Support.')
                : null)
            ->visible(fn ($record): bool => Context::current()->forSubject($record)->isAgent())
            ->badge()->color('info')->icon('heroicon-m-lifebuoy')
            ->hintAction(Help::action('tma.agents', __('Membre du Support')));

        $about = [
            Section::make(__('Suivi'))->compact()->columnSpanFull()
                ->headerActions([Help::title('tma.projets', __('Les projets'))])
                ->schema([
                    TextEntry::make('state')->label(__('État'))->badge()
                        ->helperText(fn (ProjectTask $record): ?string => $record->state->getDescription()),
                    // Le projet : un lien vers sa fiche ; « Modifier le projet » l'ouvre dans un volet glissant (maître).
                    TextEntry::make('project.title')->label(__('Projet'))->placeholder(__('Sans projet'))
                        ->weight(FontWeight::SemiBold)
                        ->url(fn (ProjectTask $record): ?string => $record->project !== null && ProjectResource::canView($record->project)
                            ? ProjectResource::getUrl('view', ['record' => $record->project])
                            : null)
                        ->hintAction(ProjectResource::editAction()->label(__('Modifier'))->link()->size(Size::Small)),
                    TextEntry::make('project_estimate')->label(__('Fourchette du projet'))
                        ->state(fn (ProjectTask $record): ?string => $record->project?->estimateLabel())
                        ->visible(fn (ProjectTask $record): bool => $record->project?->estimateLabel() !== null),
                    TextEntry::make('project_time')->label(__('Temps du projet'))
                        ->state(fn (ProjectTask $record): ?string => $record->project !== null ? Duration::format($record->project->timeTotals()['total_minutes']) : null)
                        ->color(fn (ProjectTask $record): ?string => $record->project?->isOverEstimate($record->project->timeTotals()['total_minutes']) ? 'danger' : null)
                        ->visible(fn (ProjectTask $record): bool => $record->project !== null),
                    TextEntry::make('targetInstance.name')->label(__('Application'))
                        ->visible(fn (): bool => Tma::isMaster())
                        ->onDemand(),
                ]),
            RecipientsSection::make(),
            Section::make(__('Calendrier'))->compact()->columnSpanFull()
                ->headerActions([Help::title('tma.taches.periode', __('Début et échéance'))])
                ->columns(2)
                ->onDemand()
                ->schema([
                    TextEntry::make('starts_on')->label(__('Début'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('due_on')->label(__('Échéance prévue'))->date('d/m/Y')->placeholder('—')
                        ->color(fn (ProjectTask $record): ?string => $record->done_at === null && $record->due_on?->isPast() ? 'danger' : null),
                    TextEntry::make('done_at')->label(__('Terminée le'))->date('d/m/Y')
                        ->visible(fn (ProjectTask $record): bool => $record->done_at !== null),
                    TextEntry::make('publish_at')->label(__('Publication prévue'))->dateTime('d/m/Y H:i')
                        ->visible(fn (ProjectTask $record): bool => Tma::isMaster() && $record->publish_at !== null && ! $record->isShared()),
                    TextEntry::make('published_at')->label(__('Publiée le'))->date('d/m/Y')
                        ->visible(fn (ProjectTask $record): bool => $record->published_at !== null),
                ]),
            Section::make(__('Tickets'))->compact()->columnSpanFull()
                ->visible(fn (ProjectTask $record): bool => $record->tickets()->exists())
                ->schema([
                    TextEntry::make('tickets')->hiddenLabel()
                        ->state(fn (ProjectTask $record): string => $record->tickets()->latest()->get()
                            ->map(fn (Ticket $ticket): string => TicketResource::canView($ticket)
                                ? '<a href="'.e(TicketResource::getUrl('view', ['record' => $ticket])).'" class="fi-link">'.e($ticket->number.' — '.$ticket->title).'</a> <span class="fi-tma-entry-instance">('.e($ticket->state->getLabel()).')</span>'
                                : e($ticket->number.' — '.$ticket->title))
                            ->implode('<br>'))
                        ->html(),
                ]),
            Section::make()->compact()->columnSpanFull()->schema([
                TextEntry::make('sync_status')->label(__('Synchro'))->badge()
                    ->hintAction(Help::action('tma.synchronisation.erreurs', __('Que veut dire ce statut ?')))
                    ->visible(fn (ProjectTask $record): bool => $record->isShared())
                    ->helperText(fn (ProjectTask $record): ?string => $record->last_sync_error),
                TextEntry::make('last_activity_at')->label(__('Dernière activité'))->since(),
            ]),
        ];

        return Tma::isMaster()
            ? [$agentMode, $time, ...$about]
            : [$agentMode, ...$about, $time];
    }
}
