<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\Questions;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Le nouveau message, sous l'historique d'un ticket ou d'une tâche : un texte lu de tous, demandeur compris, avec des
 * fichiers. Les notes s'écrivent à part, en fenêtre (`HasNotes`).
 *
 * Sur un ticket, un message va toujours avec un passage (addendum §3.2) : seule la partie dont c'est le tour écrit, par
 * les passages que le moteur lui permet (`Move::replyField()`). Le demandeur « Répond », ou « Confirme et clôt » /
 * renvoie au Support un ticket résolu ; le Support « Demande des précisions » ou « Marque comme résolu ». Il n'y a pas
 * de message à part : hors de son tour, ou pour un point d'étape, la zone renvoie aux notes. Sur une tâche projet, on
 * « Envoie le message », pour information ; dans le maître, le Support peut aussi « Poser une question » au client,
 * qui engage les personnes choisies (`Workflow\Questions`).
 *
 * À côté des boutons, « Orthographe » corrige le message avant l'envoi, quand l'IA est là (`HasOrthography`).
 */
trait HasComposer
{
    use HasOrthography;

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
                    ->visible(fn (): bool => $this->composerCanWrite())
                    ->placeholder(__('Votre message…'))
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                        ['undo', 'redo'],
                    ]),
                TimelineActions::attachmentsField()->visible(fn (): bool => $this->composerCanWrite()),
                Actions::make($this->composerActions())->key('composerActions')->alignEnd()
                    ->visible(fn (): bool => $this->composerCanWrite()),
            ]);
    }

    /**
     * La zone de réponse se montre : sur un ticket en cours d'échange, toujours (hors de son tour, elle le dit) ; sur
     * une tâche, si on peut y écrire.
     */
    public function hasComposer(): bool
    {
        $record = $this->getRecord();

        if ($record instanceof Ticket) {
            return $record->isShared() && $record->acceptsMessages();
        }

        return $this->composerCanWrite();
    }

    /** Cet utilisateur peut écrire maintenant : un message, ou une réponse. */
    public function composerCanWrite(): bool
    {
        return $this->composerCanMessage() || $this->composerReplies() !== [];
    }

    /** @return array<int, Action> */
    protected function composerActions(): array
    {
        $actions = [
            ...$this->orthographyActions('composer', ['body' => __('Message')]),
            Action::make('composerMessage')
                ->label(__('Envoyer le message'))
                ->icon('heroicon-m-paper-airplane')
                ->color(fn (): string => $this->composerReplies() === [] ? 'primary' : 'gray')
                ->visible(fn (): bool => $this->composerCanMessage())
                ->action(fn () => $this->composerWrite()),
            Action::make('composerQuestion')
                ->label(__('Poser une question'))
                ->icon('heroicon-m-question-mark-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->composerCanAsk())
                ->modalHeading(__('Poser la question au client'))
                ->modalDescription(__('Votre message part dans la tâche comme une question : les personnes choisies l’apprennent comme un ticket qui les attend (la cloche, puis l’e-mail direct ou le bilan du matin), jusqu’à ce que quelqu’un du client y réponde.'))
                ->modalSubmitActionLabel(__('Poser la question'))
                ->fillForm(fn (): array => ['to' => Questions::defaultAddressees($this->getRecord())])
                ->schema(fn (): array => [
                    CheckboxList::make('to')
                        ->label(__('À qui'))
                        ->options(fn (): array => Questions::addressables($this->getRecord()))
                        ->required(),
                ])
                ->action(fn (array $data) => $this->composerAsk((array) ($data['to'] ?? []))),
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

    /** « Envoyer le message » : sur une tâche, qui a le droit d'y écrire ; sur un ticket, jamais (un message va avec un passage). */
    protected function composerCanMessage(): bool
    {
        /** @var Trackable $record */
        $record = $this->getRecord();

        return app(Timeline::class)->canAdd($record, EntryType::Comment, Context::current());
    }

    /** « Poser une question » : le Support, dans le maître, sur une tâche publiée (`Questions::canAsk()`). */
    protected function composerCanAsk(): bool
    {
        $record = $this->getRecord();

        return $record instanceof ProjectTask && app(Questions::class)->canAsk($record, Context::current());
    }

    /**
     * Les réponses que cet utilisateur peut faire maintenant : celles de la partie dont c'est le tour, s'il est la
     * personne attendue (le moteur en décide, `TicketWorkflow::allows()`).
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

        return $this->composerRepliesCache ??= array_values(array_filter(
            $workflow->moves(),
            fn (Move $move): bool => $move->replyField() !== null && $workflow->allows($record, $move, $context),
        ));
    }

    /** Ce que la zone dit, au-dessus du texte : qui on attend, et ce que fera le bouton. */
    protected function composerHint(): string
    {
        $record = $this->getRecord();

        if (! $record instanceof Ticket) {
            return $this->composerCanAsk()
                ? __('Tout le monde lira ce message, pour information. Vous attendez une réponse du client ? « Poser une question ».')
                : __('Tout le monde lira ce message.');
        }

        $replies = $this->composerReplies();
        $waitingOn = $record->waitingOnFor(auth()->user());

        return match (true) {
            collect($replies)->contains(fn (Move $move): bool => $move->key() === 'answer') => __(':support attend votre réponse.', ['support' => Terms::support()]),
            collect($replies)->contains(fn (Move $move): bool => $move->key() === 'validate') => __('Le travail est fait : confirmez pour clore, ou renvoyez la demande en disant ce qui ne va pas.'),
            $replies !== [] => __('« Demander des précisions » ou « Marquer comme résolu » passe la main au demandeur. Pour un point d’étape, écrivez une note.'),
            default => __('En attente de : :who. Ce n’est pas votre tour : vous pouvez écrire une note, ou modifier votre dernier message tant qu’il est le dernier.', ['who' => $waitingOn ?? '—']),
        };
    }

    protected function composerWrite(): void
    {
        $data = $this->getSchema('composer')->getState();
        $uploads = Uploads::fromForm($data);

        $this->composerRun(fn () => app(Timeline::class)->add($this->getRecord(), EntryType::Comment, $data['body'] ?? null, Context::current(), uploads: $uploads));
    }

    /** @param  array<int, string>  $to  à qui la poser (`Questions::addressables()`) */
    protected function composerAsk(array $to): void
    {
        $data = $this->getSchema('composer')->getState();
        $uploads = Uploads::fromForm($data);

        $this->composerRun(fn () => app(Questions::class)->ask($this->getRecord(), $data['body'] ?? null, $to, Context::current(), $uploads));
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
