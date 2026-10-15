<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Le nouveau message, sous l'historique d'un ticket ou d'une tâche : un texte lu de tous, demandeur compris, avec des
 * fichiers. Les notes s'écrivent à part, en fenêtre (`HasNotes`).
 *
 * Ses boutons suivent qui doit agir. La partie qu'on attend y trouve ses réponses, qui font avancer le ticket avec ce
 * texte (`Move::replyField()`) : le demandeur « Répondre », ou « Confirmer et clore » / « Retour au Support » un ticket résolu ;
 * l'équipe qui traite « Demander des précisions » ou « Marquer comme résolu » — sur un ticket nouveau, elle le prend
 * en charge au passage. L'autre partie n'a qu'« Envoyer le message » : une précision, qui ne change pas l'état.
 */
trait HasComposer
{
    /** @var array<string, mixed>|null */
    public ?array $composerData = [];

    /** @var array<int, Move>|null les réponses possibles, le temps d'une requête (voir `composerReplies()`) */
    protected ?array $composerRepliesCache = null;

    public function mountHasComposer(): void
    {
        $this->getSchema('composer')->fill();
    }

    public function composer(Schema $schema): Schema
    {
        return $schema
            ->statePath('composerData')
            ->components([
                Text::make(fn (): string => $this->composerHint())->color('gray'),
                RichEditor::make('body')
                    ->hiddenLabel()
                    ->placeholder(__('Votre message…'))
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                        ['undo', 'redo'],
                    ]),
                TimelineActions::attachmentsField(),
                Actions::make($this->composerActions())->key('composerActions')->alignEnd(),
            ]);
    }

    /** La zone de réponse a quelque chose à proposer à cet utilisateur. */
    public function hasComposer(): bool
    {
        return $this->composerCanMessage() || $this->composerReplies() !== [];
    }

    /** @return array<int, Action> */
    protected function composerActions(): array
    {
        $actions = [
            Action::make('composerMessage')
                ->label(__('Envoyer le message'))
                ->icon('heroicon-m-paper-airplane')
                ->color(fn (): string => $this->composerReplies() === [] ? 'primary' : 'gray')
                ->visible(fn (): bool => $this->composerCanMessage())
                ->action(fn () => $this->composerWrite()),
        ];

        foreach (app(TicketWorkflow::class)->moves() as $move) {
            if ($move->replyField() === null) {
                continue;
            }

            $actions[] = Action::make('composerReply_'.str_replace('-', '_', $move->key()))
                ->label(fn (): string => $this->getRecord() instanceof Ticket ? $move->labelFor($this->getRecord()) : $move->label())
                ->icon($move->icon())
                ->color($move->color())
                ->visible(fn (): bool => in_array($move, $this->composerReplies(), true))
                ->action(fn () => $this->composerReply($move));
        }

        return $actions;
    }

    protected function composerCanComment(): bool
    {
        /** @var Trackable $record */
        $record = $this->getRecord();

        return app(Timeline::class)->canAdd($record, EntryType::Comment, Context::current());
    }

    /**
     * « Envoyer le message » : pour qui peut écrire un message, sauf qui répond pour le demandeur qu'on attend — son
     * message est sa réponse (« Répondre », « Retour au Support »…), pas une précision à côté.
     */
    protected function composerCanMessage(): bool
    {
        if (! $this->composerCanComment()) {
            return false;
        }

        foreach ($this->composerReplies() as $move) {
            if (in_array(Side::Requester, $move->sides(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Les réponses que cet utilisateur peut faire maintenant : celles de la partie qu'on attend, s'il en est.
     *
     * @return array<int, Move>
     */
    protected function composerReplies(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Ticket) {
            return [];
        }

        $workflow = app(TicketWorkflow::class);
        $context = Context::current();

        // Le demandeur répond en demandeur ; tous les autres, au nom de l'équipe qui traite. Sur un ticket de l'équipe du
        // client, une même application tient les deux rôles : c'est la personne qui les départage. Sur un ticket
        // transmis au Support, l'équipe du client (qui peut lui parler) répond aussi pour le demandeur.
        $isRequester = (bool) $record->requester?->isUser(auth()->user());
        $speaksForRequester = $isRequester || ($record->handling_mode === HandlingMode::ServiceDesk
            && Tma::isClient()
            && TmaGate::allows(auth()->user(), TmaGate::workflowAction('transmit'), $record));

        return $this->composerRepliesCache ??= array_values(array_filter(
            $workflow->moves(),
            fn (Move $move): bool => $move->replyField() !== null
                && (in_array(Side::Requester, $move->sides(), true) ? $speaksForRequester : ! $isRequester)
                && ($workflow->allows($record, $move, $context) || $this->composerTakesChargeFirst($record, $move)),
        ));
    }

    /**
     * Sur un ticket nouveau, l'équipe qui le traite répond (« Demander des précisions », « Marquer comme résolu ») en le
     * prenant en charge au passage.
     */
    protected function composerTakesChargeFirst(Ticket $ticket, Move $move): bool
    {
        $workflow = app(TicketWorkflow::class);
        $context = Context::current()->forSubject($ticket);

        return $ticket->state instanceof Submitted
            && in_array(InProgress::class, $move->from(), true)
            && $workflow->allows($ticket, 'take-charge', $context)
            && ($context->isAgent() || TmaGate::allows($context->user, TmaGate::workflowAction($move->key()), $ticket));
    }

    /** Ce que la zone dit, au-dessus du texte : qui on attend, et ce que fera le bouton. */
    protected function composerHint(): string
    {
        $record = $this->getRecord();

        if (! $record instanceof Ticket) {
            return __('Tout le monde lira ce message.');
        }

        $replies = $this->composerReplies();
        $waitingOn = $record->waitingOnFor(auth()->user());

        return match (true) {
            $record->state instanceof Draft => __('Ce brouillon n’est encore confié à personne : votre message reste dans cette application.'),
            collect($replies)->contains(fn (Move $move): bool => $move->key() === 'answer') => __(':team attend votre réponse.', ['team' => $record->handledBy() ?? __('L’équipe')]),
            collect($replies)->contains(fn (Move $move): bool => $move->key() === 'validate') => __('Le travail est fait : confirmez pour clore, ou renvoyez la demande en disant ce qui ne va pas.'),
            $replies !== [] => __('« Envoyer le message » garde la main ; « Demander des précisions » ou « Marquer comme résolu » la passe au demandeur.'),
            $waitingOn === null => __('Tout le monde lira ce message.'),
            default => __('En attente de : :who. Votre message ne change pas l’état du ticket.', ['who' => $waitingOn]),
        };
    }

    protected function composerWrite(): void
    {
        $data = $this->getSchema('composer')->getState();
        $uploads = Uploads::fromForm($data);

        $this->composerRun(fn () => app(Timeline::class)->add($this->getRecord(), EntryType::Comment, $data['body'] ?? null, Context::current(), uploads: $uploads));
    }

    protected function composerReply(Move $move): void
    {
        $data = $this->getSchema('composer')->getState();
        $uploads = Uploads::fromForm($data);
        $ticket = $this->getRecord();
        $body = $data['body'] ?? null;

        $this->composerRun(function () use ($move, $ticket, $body, $uploads): void {
            $workflow = app(TicketWorkflow::class);
            $context = Context::current();

            // La question, la réponse, ce qui ne va pas : sans texte, le passage n'a pas de sens.
            if (Html::isBlank($body) && in_array('required', (array) ($move->rules($ticket)[$move->replyField()] ?? []), true)) {
                throw ValidationException::withMessages(['body' => __('Le message est vide.')]);
            }

            if ($this->composerTakesChargeFirst($ticket, $move)) {
                $workflow->run($ticket, 'take-charge', [], $context);
                $ticket->refresh();
            }

            if ($move->replyField() === '') {
                // Un passage sans texte à lui (« Confirmer et clore ») : le texte, s'il y en a, part en message avant,
                // lié au passage (l'historique les montre ensemble).
                $transitionUlid = (string) Str::ulid();

                if (! Html::isBlank($body) || $uploads !== []) {
                    app(Timeline::class)->forTransition($transitionUlid, fn () => app(Timeline::class)->add($ticket, EntryType::Comment, $body, $context, uploads: $uploads));
                }

                $workflow->run($ticket, $move, [], $context, $transitionUlid);

                return;
            }

            $workflow->run($ticket, $move, [$move->replyField() => $body, 'files' => Uploads::toBase64($uploads)], $context);
            Uploads::discard($uploads);
        });
    }

    /** Fait l'écriture, et le dit ; une erreur de droit, de contenu ou d'échange s'affiche et garde le texte. */
    protected function composerRun(\Closure $write): void
    {
        try {
            $write();
        } catch (AuthorizationException|ValidationException|SyncException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->composerRepliesCache = null;
        $this->getRecord()->refresh();
        $this->getSchema('composer')->fill();
        Notification::make()->success()->title(__('Envoyé'))->send();
    }
}
