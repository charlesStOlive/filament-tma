<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Enums\NoticeTone;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Qui prévenir, ici, de ce qui arrive à un ticket ou à une tâche, et comment. Un ticket engage, une tâche informe :
 *
 * - **Ticket.** Ses destinataires principaux voient tout dans la cloche. Quand le ticket attend l'un d'eux (le Support
 *   le lui ouvre, ou la main lui revient), c'est une action (`NoticeTone::Action`) : un courriel s'il a choisi l'e-mail
 *   direct, sinon son bilan du matin. Une fin de ticket (`NoticeTone::Outcome`) va dans la cloche et le bilan. Une
 *   relance faite à la main part toujours par courriel ; le rappel de ce qui ne bouge plus, seulement dans le bilan.
 * - **Tâche.** Chez le client, la cloche et le bilan de la semaine, jamais de courriel, sauf « Informer par courriel »
 *   (un message marqué `meta.notify`). Dans le maître, le Support n'apprend que ce que le client écrit sur une tâche,
 *   comme une action.
 * - Un secondaire ne l'apprend que dans son bilan (« En copie ») ; les autres, rien.
 *
 * Chaque application prévient ses propres utilisateurs (addendum §4) : ce qui est fait ici passe par les événements du
 * workflow (`Listeners\NotifyFollowers`), ce qui arrive de l'autre application par l'état recopié
 * (`Events\SnapshotMirrored`, `Sync\Mirror`). Une même chose n'est dite qu'une fois à une personne (`Notice::$key`), et
 * jamais à son auteur.
 */
final class Notifier
{
    /** Les passages qui terminent un ticket. */
    private const OUTCOMES = ['validate', 'refuse', 'cancel', 'force-close', 'auto-close'];

    /** Les noms des champs d'une tâche, pour dire ce qui a changé. */
    private const TASK_FIELDS = ['title' => 'titre', 'description' => 'description', 'starts_on' => 'début', 'due_on' => 'échéance', 'project_id' => 'projet'];

    private static bool $muted = false;

    /** Fait le travail sans prévenir personne (les exemples de `tma:demo`). */
    public static function muted(\Closure $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /**
     * Ce qui vient d'arriver de l'autre application, dans l'ordre : les passages, puis les entrées.
     *
     * @param  array<int, StateTransition>  $transitions
     * @param  array<int, Entry>  $entries
     */
    public static function received(Trackable $subject, array $transitions, array $entries): void
    {
        foreach ($transitions as $transition) {
            self::transition($subject, $transition);
        }

        foreach ($entries as $entry) {
            self::entry($entry);
        }
    }

    /** Un passage de ticket ou de tâche, fait ici ou reçu. */
    public static function transition(Trackable $subject, StateTransition $transition): void
    {
        if (self::$muted) {
            return;
        }

        if ($subject instanceof ProjectTask) {
            self::taskTransition($subject, $transition);

            return;
        }

        if (! $subject instanceof Ticket) {
            return;
        }

        $move = $transition->move;
        $side = rescue(fn (): ?Side => TicketState::make($transition->to_state, $subject)->inHandsOf(), null, false);
        $requesterSide = self::requesterSide($subject);
        // Le Support ouvre un ticket pour le client : chez le client, il attend ses destinataires.
        $opened = in_array($move, ['publish', 'auto-publish'], true) && Tma::isClient();
        // Une demande qui arrive, ou un ticket ouvert pour soi : on cite ce qui est demandé.
        $excerpt = in_array($move, ['transmit', 'publish', 'auto-publish'], true)
            ? self::excerpt($subject->description)
            : self::transitionExcerpt($subject, $transition);

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'role' => $role]) {
            if ($transition->actor?->isUser($user)) {
                continue;
            }

            $holds = match ($side) {
                Side::Requester => $requesterSide->has((string) $user->getAuthIdentifier()),
                // Sans contact, le Support tout entier est attendu.
                Side::Handler => $subject->assignee !== null
                    ? $subject->assignee->isUser($user)
                    : $subject->isHandledHere() && Addressables::isSupportMember($user),
                default => false,
            };
            $target = $holds || $opened;
            $tone = match (true) {
                $target => NoticeTone::Action,
                in_array($move, self::OUTCOMES, true) => NoticeTone::Outcome,
                default => NoticeTone::Info,
            };

            self::send($user, $target ? NoticeEvent::Turn : ($move === 'auto-close' ? NoticeEvent::AutoClosed : NoticeEvent::Progress), $subject, "transition:{$transition->ulid}", [
                'line' => self::moveLine($subject, $transition, $target),
                'excerpt' => $excerpt,
                ...($target ? self::expected($subject, $move, $transition->actor, $user) : []),
            ], $role, $tone);
        }
    }

    /** Un message ou une note, écrit ici ou reçu. Le message d'un passage est dit avec le passage. */
    public static function entry(Entry $entry): void
    {
        if (self::$muted || $entry->private || $entry->trashed() || filled($entry->meta['transition'] ?? null)) {
            return;
        }

        $subject = $entry->subject;

        if ($subject instanceof ProjectTask) {
            self::taskEntry($subject, $entry);

            return;
        }

        if (! $subject instanceof Ticket) {
            return;
        }

        $author = $entry->author;
        $who = self::who($author);
        $key = "entry:{$entry->ulid}";
        $excerpt = self::excerpt($entry->body);

        // Une relance, faite à la main : à qui a la main, toujours par courriel.
        if (! empty($entry->meta['reminder'])) {
            foreach (self::holders($subject) as $user) {
                if (! $author?->isUser($user)) {
                    self::send($user, NoticeEvent::Reminder, $subject, $key, [
                        'line' => __(':who vous relance : ce ticket attend votre retour.', ['who' => $who]),
                        'headline' => __('Relance : ce ticket attend votre retour'),
                        'excerpt' => $excerpt,
                        ...self::expectedNow($subject, $user),
                    ], RecipientRole::Primary, NoticeTone::Action, forceMail: true);
                }
            }

            return;
        }

        $line = $entry->isNote()
            ? __(':who a écrit une note.', ['who' => $who])
            : __(':who a écrit un message.', ['who' => $who]);

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'role' => $role]) {
            if (! $author?->isUser($user)) {
                self::send($user, $entry->isNote() ? NoticeEvent::Note : NoticeEvent::Message, $subject, $key, ['line' => $line, 'excerpt' => $excerpt], $role, NoticeTone::Info);
            }
        }
    }

    /**
     * Des personnes viennent d'être nommées destinataires principaux (pas par un automatisme) : elles l'apprennent
     * dans la cloche, sauf l'auteur du changement.
     *
     * @param  array<int, int>  $actorIds
     */
    public static function recipientsAdded(Trackable $subject, array $actorIds, ?Actor $by = null): void
    {
        if (self::$muted || $actorIds === [] || ($subject instanceof ProjectTask && Tma::isMaster())) {
            return;
        }

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'actor' => $actor, 'role' => $role]) {
            // Un secondaire le voit à sa place dans le bilan (« En copie ») : inutile de le lui dire.
            if (! in_array((int) $actor->getKey(), $actorIds, true) || $by?->isUser($user) || $role === RecipientRole::Secondary) {
                continue;
            }

            $line = $by !== null ? __(':who vous a ajouté(e) aux destinataires principaux.', ['who' => self::who($by)]) : __('Vous êtes désormais destinataire principal.');

            self::send($user, NoticeEvent::Recipient, $subject, 'recipient:'.$subject->getMorphClass().':'.$subject->getKey().':'.$actor->getKey().':'.now()->getTimestamp(), ['line' => $line], $role, NoticeTone::Info);
        }
    }

    /**
     * Client : une tâche recopiée du maître a changé sans passage ni message (le Support l'a modifiée, ou du temps y a
     * été saisi). Ses destinataires l'apprennent dans la cloche et le bilan de la semaine.
     *
     * @param  array<int, string>  $fields  les champs changés (`title`, `description`, `starts_on`, `due_on`, `project_id`)
     */
    public static function taskChanged(ProjectTask $task, array $fields, ?int $minutesBefore = null): void
    {
        if (self::$muted || Tma::isMaster() || $task->trashed() || $task->published_at === null) {
            return;
        }

        $lines = [];

        if ($fields !== []) {
            $lines[] = __('Le Support a modifié la tâche (:fields).', ['fields' => implode(', ', array_map(fn (string $field): string => __(self::TASK_FIELDS[$field] ?? $field), $fields))]);
        }

        $minutes = (int) $task->time_total_minutes;

        if ($minutesBefore !== null && $minutes !== $minutesBefore) {
            $lines[] = $minutes > $minutesBefore
                ? __('Temps passé sur la tâche : :total (+ :added).', ['total' => Duration::format($minutes), 'added' => Duration::format($minutes - $minutesBefore)])
                : __('Temps passé sur la tâche : :total.', ['total' => Duration::format($minutes)]);
        }

        if ($lines === []) {
            return;
        }

        foreach (app(Recipients::class)->local($task) as ['user' => $user, 'role' => $role]) {
            self::send($user, NoticeEvent::Task, $task, "task-changed:{$task->getKey()}:{$task->sync_version}", ['line' => implode(' ', $lines)], $role, NoticeTone::Info);
        }
    }

    /**
     * L'avertissement de clôture automatique, au demandeur : une action, puisqu'une réponse suffit à garder le ticket
     * ouvert (un courriel s'il a choisi l'e-mail direct, sinon son bilan du matin).
     */
    public static function autoCloseWarning(Ticket $ticket, \DateTimeInterface $closesOn): void
    {
        if (self::$muted) {
            return;
        }

        foreach (self::requesterSide($ticket) as $user) {
            $date = Dates::format($closesOn, 'd/m/Y', $user);

            self::send($user, NoticeEvent::AutoCloseWarning, $ticket, 'auto-close-warning:'.$ticket->getKey().':'.($ticket->auto_close_warned_at?->getTimestamp() ?? now()->getTimestamp()), [
                'line' => __('Ce ticket attend votre retour : sans activité de votre part, il sera clos automatiquement le :date.', ['date' => $date]),
                'headline' => __('Sans réponse, ce ticket sera clos le :date', ['date' => $date]),
                'action' => __('Répondre, ou écrire une simple note, suffit à le garder ouvert.'),
                'closes_on' => $closesOn->format(\DateTimeInterface::ATOM),
                'button' => __('Ouvrir le ticket'),
            ], RecipientRole::Primary, NoticeTone::Action);
        }
    }

    /**
     * Rappelle à qui a la main un ticket ou une tâche qui ne bouge plus depuis `$days` jours, dans son bilan (pas à
     * qui ne le reçoit pas), une fois par période de `notifications.stale.after_days` jours.
     *
     * @return int le nombre de personnes rappelées
     */
    public static function stale(Trackable $subject, int $days, bool $dryRun = false): int
    {
        if (self::$muted) {
            return 0;
        }

        $period = (int) config('filament-tma.notifications.stale.after_days', 7);
        $line = match (true) {
            $subject instanceof ProjectTask => __('Rien n’a bougé sur cette tâche depuis :days jours.', ['days' => $days]),
            $subject instanceof Ticket && $subject->state instanceof Submitted => __('Cette demande attend la première réponse du Support depuis :days jours.', ['days' => $days]),
            default => __('Ce ticket attend votre retour depuis :days jours.', ['days' => $days]),
        };
        $sent = 0;

        foreach (self::holders($subject) as $user) {
            $recent = Notice::query()->for($user)
                ->where('event', NoticeEvent::Stale->value)
                ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('created_at', '>', now()->subDays($period))
                ->exists();

            if ($recent || ! NoticeChannels::digest($user)) {
                continue;
            }

            $sent++;

            if (! $dryRun) {
                self::send($user, NoticeEvent::Stale, $subject, 'stale:'.$subject->getMorphClass().':'.$subject->getKey().':'.now()->toDateString(), ['line' => $line],
                    RecipientRole::Primary, NoticeTone::Action, mode: NoticeMode::Grouped);
            }
        }

        return $sent;
    }

    /**
     * Qui a la main, ici : le demandeur, ou l'intervenant ; un ticket soumis que personne n'a pris, qui peut le
     * prendre ; une tâche, son intervenant.
     *
     * @return Collection<string, Authenticatable>
     */
    public static function holders(Trackable $subject): Collection
    {
        if ($subject instanceof ProjectTask) {
            return self::keyed(collect([self::localUser($subject->assignee)]));
        }

        if (! $subject instanceof Ticket || $subject->state->isFinal()) {
            return collect();
        }

        return match ($subject->sideInCharge()) {
            Side::Requester => self::requesterSide($subject),
            Side::Handler => match (true) {
                $subject->assignee !== null => self::keyed(collect([self::localUser($subject->assignee)])),
                $subject->state instanceof Submitted && $subject->isHandledHere() => self::mayTakeCharge(),
                default => collect(),
            },
            default => collect(),
        };
    }

    /**
     * Le côté demandeur d'un ticket, ici : le demandeur (celui qui a pris la main, s'il y en a un).
     *
     * @return Collection<string, Authenticatable>
     */
    public static function requesterSide(Ticket $ticket): Collection
    {
        return self::keyed(collect([self::localUser($ticket->requester)]));
    }

    /** Client : un passage de tâche fait par le Support. Dans le maître, rien : c'est le Support qui les fait. */
    private static function taskTransition(ProjectTask $task, StateTransition $transition): void
    {
        if (Tma::isMaster()) {
            return;
        }

        $who = self::who($transition->actor);
        $line = match ($transition->move) {
            'publish', 'auto-publish' => __('Le Support a publié une tâche pour vous.'),
            'complete' => __(':who a terminé la tâche.', ['who' => $who]),
            'cancel' => __(':who a annulé la tâche.', ['who' => $who]),
            'reopen' => __(':who a rouvert la tâche.', ['who' => $who]),
            default => __(':who : « :move ».', ['who' => $who, 'move' => rescue(fn (): string => app(TaskWorkflow::class)->move($transition->move)->labelFor($task), $transition->move, false)]),
        };

        foreach (app(Recipients::class)->local($task) as ['user' => $user, 'role' => $role]) {
            if (! $transition->actor?->isUser($user)) {
                self::send($user, NoticeEvent::Task, $task, "transition:{$transition->ulid}", ['line' => $line, 'excerpt' => self::transitionExcerpt($task, $transition)], $role, NoticeTone::Info);
            }
        }
    }

    /**
     * Un message ou une note sur une tâche. Dans le maître, seulement ce qu'écrit le client : le Support y répond s'il
     * le faut. Chez le client, pour information (cloche, bilan de la semaine), sauf « Informer par courriel ».
     */
    private static function taskEntry(ProjectTask $task, Entry $entry): void
    {
        $author = $entry->author;
        $who = self::who($author);
        $key = "entry:{$entry->ulid}";
        $excerpt = self::excerpt($entry->body);

        if (Tma::isMaster()) {
            if ($author === null || $author->isLocal()) {
                return;
            }

            foreach (app(Recipients::class)->local($task) as ['user' => $user, 'role' => $role]) {
                self::send($user, $entry->isNote() ? NoticeEvent::Note : NoticeEvent::Message, $task, $key, [
                    'line' => $entry->isNote() ? __(':who a écrit une note sur la tâche.', ['who' => $who]) : __(':who a commenté la tâche.', ['who' => $who]),
                    'headline' => __(':who a commenté la tâche', ['who' => self::who($author, short: true)]),
                    'excerpt' => $excerpt,
                    'action' => __('Lui répondre dans la tâche, si besoin.'),
                    'button' => __('Ouvrir la tâche'),
                ], $role, NoticeTone::Action);
            }

            return;
        }

        $informed = ! empty($entry->meta['notify']);
        $line = match (true) {
            $informed => __(':who vous informe :', ['who' => $who]),
            $entry->isNote() => __(':who a écrit une note.', ['who' => $who]),
            default => __(':who a écrit un message.', ['who' => $who]),
        };

        foreach (app(Recipients::class)->local($task) as ['user' => $user, 'role' => $role]) {
            if (! $author?->isUser($user)) {
                self::send($user, $entry->isNote() ? NoticeEvent::Note : NoticeEvent::Message, $task, $key, [
                    'line' => $line,
                    'excerpt' => $informed ? null : $excerpt,
                    'message' => $informed ? Html::toText($entry->body) : null,
                    'headline' => $informed ? __('Le Support vous informe') : null,
                ], $role, NoticeTone::Info, forceMail: $informed && $role === RecipientRole::Primary);
            }
        }
    }

    /**
     * Écrit la notification d'une personne et l'envoie :
     *
     * - un secondaire : rien tout de suite, son bilan (du matin, ou de la semaine pour une tâche) s'il le reçoit ;
     * - un principal : la cloche toujours (si l'application l'a) ; un courriel pour une action s'il a choisi l'e-mail
     *   direct, ou quand c'est imposé (`$forceMail` : relance, « Informer par courriel ») ; une action sans courriel,
     *   une fin de ticket et ce qui arrive à une tâche vont aussi dans son bilan.
     *
     * `$mode` à `Grouped` : seulement le bilan (le rappel de ce qui ne bouge plus). Sans bilan, ce qui devait y aller
     * est seulement noté (`NoticeMode::Skipped`).
     *
     * @param  array<string, mixed>  $text  `line`, et selon le cas `excerpt`, `headline`, `action`, `closes_on`, `button`, `message`
     */
    private static function send(Authenticatable $user, NoticeEvent $event, Trackable $subject, string $key, array $text, ?RecipientRole $role, NoticeTone $tone, bool $forceMail = false, ?NoticeMode $mode = null): void
    {
        if (! method_exists($user, 'notify')) {
            return;
        }

        $role ??= RecipientRole::Primary;
        $task = $subject instanceof ProjectTask;
        // Une tâche qui informe va dans le bilan de la semaine ; le reste (les tickets, ce qu'on attend du Support sur une tâche), dans celui du matin.
        $digest = $task && $tone !== NoticeTone::Action ? NoticeChannels::WEEKLY : NoticeChannels::DIGEST;
        $wantsDigest = $digest === NoticeChannels::WEEKLY ? NoticeChannels::weekly($user) : NoticeChannels::digest($user);
        $channels = [];

        if ($role === RecipientRole::Secondary || $mode === NoticeMode::Grouped) {
            $mode = NoticeMode::Grouped;
        } else {
            if (NoticeChannels::bell()) {
                $channels[] = NoticeChannels::APP;
            }

            $mailed = $forceMail || ($tone === NoticeTone::Action && NoticeChannels::mail($user));

            if ($mailed) {
                $channels[] = NoticeChannels::MAIL;
            }

            $mode = (! $mailed && $tone === NoticeTone::Action) || $tone === NoticeTone::Outcome || ($task && $tone === NoticeTone::Info)
                ? NoticeMode::Grouped
                : NoticeMode::Immediate;
        }

        if ($mode === NoticeMode::Grouped && ! $wantsDigest) {
            $mode = $channels === [] ? NoticeMode::Skipped : NoticeMode::Immediate;
        }

        if ($mode === NoticeMode::Immediate && $channels === []) {
            $mode = NoticeMode::Skipped;
        }

        $notice = Notice::query()->createOrFirst(
            ['user_id' => (string) $user->getAuthIdentifier(), 'key' => $key],
            [
                'event' => $event,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'mode' => $mode,
                'data' => array_filter([
                    'kind' => $subject->kind(),
                    'ref' => $subject->number,
                    'title' => $subject->title,
                    'state' => $subject->state->getLabel(),
                    'priority' => $subject instanceof Ticket && $tone === NoticeTone::Action && Tma::isMaster() ? $subject->priority?->getLabel() : null,
                    'project' => $task ? $subject->project?->title : null,
                    'role' => $role->value,
                    'tone' => $tone->value,
                    'digest' => $digest,
                    ...$text,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'sent_at' => $mode === NoticeMode::Grouped ? null : now(),
                'created_at' => now(),
            ],
        );

        if ($notice->wasRecentlyCreated && $channels !== []) {
            $user->notify(new NoticeNotification($notice, $channels));
        }
    }

    /**
     * Ce qu'on attend de qui reçoit la main par ce passage : le titre court (objet du courriel), l'action, la date de
     * clôture automatique quand c'est au demandeur, le libellé du bouton.
     *
     * @return array<string, string|null>
     */
    private static function expected(Ticket $ticket, string $move, ?Actor $actor, Authenticatable $user): array
    {
        $who = self::who($actor, short: true);

        return match ($move) {
            'transmit' => [
                'headline' => __('Nouvelle demande de :who', ['who' => $who]),
                'action' => __('Prendre la demande en main : y répondre, poser une question, la mettre en pause ou la refuser.'),
                'button' => __('Ouvrir la demande'),
            ],
            'answer' => [
                'headline' => __(':who a répondu', ['who' => $who]),
                'action' => __('Reprendre le traitement avec cette réponse.'),
                'button' => __('Ouvrir le ticket'),
            ],
            'reopen' => [
                'headline' => __(':who vous renvoie la demande', ['who' => $who]),
                'action' => __('Reprendre le traitement : le résultat ne convient pas encore.'),
                'button' => __('Ouvrir le ticket'),
            ],
            'resume', 'auto-resume' => [
                'headline' => __('Fin de la pause : la demande reprend'),
                'action' => __('Reprendre le traitement de la demande.'),
                'button' => __('Ouvrir le ticket'),
            ],
            'publish', 'auto-publish' => [
                'headline' => __('Le Support a ouvert un ticket pour vous'),
                'action' => __('En prendre connaissance.'),
                'button' => __('Voir le ticket'),
            ],
            'request-answer', 'submit-for-validation' => [
                'headline' => $move === 'request-answer' ? __('Le Support attend votre réponse') : __('Votre demande est résolue : à confirmer'),
                ...self::expectedNow($ticket, $user, $move === 'request-answer' ? AwaitingRequester::class : PendingValidation::class),
            ],
            default => [
                'headline' => __('Ce ticket attend votre action'),
                ...self::expectedNow($ticket, $user),
            ],
        };
    }

    /**
     * Ce qu'on attend de qui a la main, selon l'état du ticket (une relance, un passage sans texte prévu).
     *
     * @param  class-string<TicketState>|null  $state  l'état à considérer, s'il n'est pas encore celui du ticket
     * @return array<string, string|null>
     */
    private static function expectedNow(Ticket $ticket, Authenticatable $user, ?string $state = null): array
    {
        $state ??= $ticket->state::class;
        $closesOn = config('filament-tma.auto_close.enabled', true) && in_array($state, [AwaitingRequester::class, PendingValidation::class], true)
            ? now()->addDays((int) config('filament-tma.auto_close.after_days', 28))
            : null;

        return [
            ...match ($state) {
                AwaitingRequester::class => ['action' => __('Répondre à la question, dans le ticket.'), 'button' => __('Répondre')],
                PendingValidation::class => ['action' => __('Vérifier le résultat, puis confirmer pour clore le ticket, ou le renvoyer au Support.'), 'button' => __('Vérifier et confirmer')],
                default => ['action' => __('Donner suite à la demande.'), 'button' => __('Ouvrir le ticket')],
            },
            'closes_on' => $closesOn?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** Ce qu'un passage dit, pour le citer : le message écrit avec lui, ou son motif. */
    private static function transitionExcerpt(Trackable $subject, StateTransition $transition): ?string
    {
        $message = $subject->entries()->where('meta->transition', $transition->ulid)->oldest('id')->first();

        return self::excerpt($message?->body ?? $transition->reason);
    }

    private static function excerpt(?string $html): ?string
    {
        $text = Str::squish(Html::toText($html));

        return $text === '' ? null : Str::limit($text, 300);
    }

    /**
     * Qui a fait quelque chose : son nom, et d'où il vient s'il n'est pas d'ici (« Léo Labarbe (EDM) » vu du Support,
     * « Charles (Support) » vu du client) ; personne, le Support (un automatisme). `$short` : vu du client, une personne
     * du Support est « le Support » (l'objet d'un courriel).
     */
    private static function who(?Actor $actor, bool $short = false): string
    {
        if ($actor === null) {
            return __('Le Support');
        }

        $team = $actor->team();

        if ($short && $team !== null && Tma::isClient()) {
            return __('Le Support');
        }

        return $team !== null ? "{$actor->name} ({$team})" : (string) $actor->name;
    }

    /** Ce que dit un passage à une personne, selon que le ticket l'attend ou non. */
    private static function moveLine(Ticket $ticket, StateTransition $transition, bool $holds): string
    {
        $replace = ['who' => self::who($transition->actor)];

        return match (true) {
            $transition->move === 'request-answer' => $holds ? __(':who attend votre réponse.', $replace) : __(':who a demandé des précisions.', $replace),
            $transition->move === 'submit-for-validation' => $holds ? __(':who a résolu votre demande.', $replace) : __(':who a marqué la demande comme résolue.', $replace),
            $transition->move === 'transmit' => $holds ? __(':who vous a transmis une demande.', $replace) : __(':who a transmis la demande au Support.', $replace),
            $transition->move === 'answer' => __(':who a répondu.', $replace),
            $transition->move === 'reopen' => $holds ? __(':who vous renvoie la demande : le résultat ne convient pas encore.', $replace) : __(':who a renvoyé la demande.', $replace),
            $transition->move === 'pause' => __(':who a mis la demande en pause.', $replace),
            in_array($transition->move, ['resume', 'auto-resume'], true) => __('La demande reprend.'),
            $transition->move === 'validate' => __(':who a confirmé la solution : le ticket est clos.', $replace),
            $transition->move === 'force-close' => __(':who a forcé la clôture du ticket.', $replace),
            $transition->move === 'force-return' => __(':who a repris la main sans attendre la réponse.', $replace),
            $transition->move === 'take-over' => $holds ? __('Vous avez pris la main : c’est vous que le Support attend.') : __(':who a pris la main : le Support attend désormais sa réponse.', $replace),
            $transition->move === 'cancel' => __(':who a retiré la demande.', $replace),
            $transition->move === 'refuse' => __(':who a refusé la demande.', $replace),
            $transition->move === 'auto-close' => __('Restée sans réponse, la demande a été close automatiquement.'),
            in_array($transition->move, ['publish', 'auto-publish'], true) => __('Le Support a ouvert ce ticket pour vous.'),
            default => __(':who : « :move ».', [...$replace, 'move' => rescue(fn (): string => app(TicketWorkflow::class)->move($transition->move)->labelFor($ticket), $transition->move, false)]),
        };
    }

    /**
     * Qui peut prendre un ticket nouveau : les membres du Support, dans le maître (une application cliente ne traite
     * rien ; le maître prévient les siens).
     *
     * @return Collection<string, Authenticatable>
     */
    private static function mayTakeCharge(): Collection
    {
        return self::keyed(Tma::userModel()::query()->lazy()
            ->filter(fn (Authenticatable $user): bool => Addressables::isSupportMember($user))
            ->collect());
    }

    /**
     * @param  Collection<int, Authenticatable|null>  $users
     * @return Collection<string, Authenticatable>
     */
    private static function keyed(Collection $users): Collection
    {
        return $users->filter()->keyBy(fn (Authenticatable $user): string => (string) $user->getAuthIdentifier());
    }

    private static function localUser(?Actor $actor): ?Authenticatable
    {
        return $actor?->isLocal() ? $actor->user : null;
    }
}
