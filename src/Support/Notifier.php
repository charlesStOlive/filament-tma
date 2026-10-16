<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Qui prévenir, ici, de ce qui arrive à un ticket ou à une tâche : ses destinataires (`Workflow\Recipients`). Un
 * principal l'apprend tout de suite, par courriel ou dans la cloche de l'application selon son choix
 * (`NoticeChannels`) ; un secondaire, dans son prochain bilan. Les autres, rien.
 *
 * Chaque application prévient ses propres utilisateurs (addendum §4) : ce qui est fait ici passe par les événements du
 * workflow (`Listeners\NotifyFollowers`), ce qui arrive de l'autre application par l'état recopié
 * (`Events\SnapshotMirrored`). Une même chose n'est dite qu'une fois à une personne (`Notice::$key`), et jamais à son
 * auteur. Trois exceptions à la règle des destinataires :
 *
 * - une relance (un message marqué `meta.reminder`) va à qui a la main, toujours tout de suite ;
 * - le rappel de ce qui ne bouge plus va à qui a la main, dans son bilan ;
 * - la clôture automatique va au demandeur, toujours tout de suite.
 *
 * Un interne n'est jamais prévenu de ce qui touche le Support (`Addressables::keptOut()`) : sur sa demande transmise,
 * le côté demandeur, ce sont les correspondants principaux, qui répondent pour lui (`requesterSide()`).
 */
final class Notifier
{
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

        $key = "transition:{$transition->ulid}";

        if ($transition->move === 'auto-close') {
            foreach (self::requesterSide($subject) as $requester) {
                self::send($requester, NoticeEvent::AutoClosed, $subject, $key, __('Restée sans réponse, la demande a été close.'),
                    mode: NoticeMode::Immediate, notification: fn (Notice $notice): Notification => new TicketAutoClosed($subject));
            }

            return;
        }

        $excerpt = self::transitionExcerpt($subject, $transition);
        $side = rescue(fn (): ?Side => TicketState::make($transition->to_state, $subject)->inHandsOf(), null, false);
        $requesterSide = self::requesterSide($subject);

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'role' => $role]) {
            if ($transition->actor?->isUser($user)) {
                continue;
            }

            $holds = match ($side) {
                Side::Requester => $requesterSide->has((string) $user->getAuthIdentifier()),
                Side::Handler => $subject->assignee?->isUser($user) ?? false,
                default => false,
            };

            self::send($user, $holds ? NoticeEvent::Turn : NoticeEvent::Progress, $subject, $key, self::moveLine($subject, $transition, $holds), $excerpt, $role);
        }
    }

    /** Un message ou une note, écrit ici ou reçu. Le message d'un passage est dit avec le passage. */
    public static function entry(Entry $entry): void
    {
        if (self::$muted || $entry->private || $entry->trashed() || filled($entry->meta['transition'] ?? null)) {
            return;
        }

        $subject = $entry->subject;

        if (! $subject instanceof Trackable) {
            return;
        }

        $author = $entry->author;
        $who = $author?->name ?? __('Quelqu’un');
        $key = "entry:{$entry->ulid}";
        $excerpt = self::excerpt($entry->body);

        if (! empty($entry->meta['reminder'])) {
            foreach (self::holders($subject) as $user) {
                if (! $author?->isUser($user)) {
                    self::send($user, NoticeEvent::Reminder, $subject, $key, __(':who vous relance : ce ticket attend votre retour.', ['who' => $who]), $excerpt, mode: NoticeMode::Immediate);
                }
            }

            return;
        }

        $event = $entry->isNote() ? NoticeEvent::Note : NoticeEvent::Message;
        $line = $entry->isNote()
            ? __(':who a écrit une note.', ['who' => $who])
            : __(':who a écrit un message.', ['who' => $who]);

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'role' => $role]) {
            if (! $author?->isUser($user)) {
                self::send($user, $event, $subject, $key, $line, $excerpt, $role);
            }
        }
    }

    /**
     * Des personnes viennent d'être nommées destinataires (pas par un automatisme) : elles l'apprennent, selon leur
     * rôle, sauf l'auteur du changement.
     *
     * @param  array<int, int>  $actorIds
     */
    public static function recipientsAdded(Trackable $subject, array $actorIds, ?Actor $by = null): void
    {
        if (self::$muted || $actorIds === []) {
            return;
        }

        foreach (app(Recipients::class)->local($subject) as ['user' => $user, 'actor' => $actor, 'role' => $role]) {
            if (! in_array((int) $actor->getKey(), $actorIds, true) || $by?->isUser($user)) {
                continue;
            }

            $line = $role === RecipientRole::Primary
                ? ($by !== null ? __(':who vous a ajouté(e) aux destinataires principaux.', ['who' => $by->name]) : __('Vous êtes désormais destinataire principal.'))
                : ($by !== null ? __(':who vous a ajouté(e) aux destinataires secondaires.', ['who' => $by->name]) : __('Vous êtes désormais destinataire secondaire.'));

            self::send($user, NoticeEvent::Recipient, $subject, 'recipient:'.$subject->getMorphClass().':'.$subject->getKey().':'.$actor->getKey().':'.now()->getTimestamp(), $line, role: $role);
        }
    }

    /**
     * Rappelle à qui a la main un ticket ou une tâche qui ne bouge plus depuis `$days` jours, dans son bilan, une fois
     * par période de `notifications.stale.after_days` jours.
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
            $subject instanceof Ticket && $subject->state instanceof Submitted && $subject->assignee_actor_id === null => __('Personne ne l’a pris en charge depuis :days jours.', ['days' => $days]),
            default => __('Ce ticket attend votre retour depuis :days jours.', ['days' => $days]),
        };
        $sent = 0;

        foreach (self::holders($subject) as $user) {
            $recent = Notice::query()->for($user)
                ->where('event', NoticeEvent::Stale->value)
                ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('created_at', '>', now()->subDays($period))
                ->exists();

            if ($recent) {
                continue;
            }

            $sent++;

            if (! $dryRun) {
                self::send($user, NoticeEvent::Stale, $subject, 'stale:'.$subject->getMorphClass().':'.$subject->getKey().':'.now()->toDateString(), $line, mode: NoticeMode::Grouped);
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
                $subject->state instanceof Submitted && $subject->isHandledHere() => self::mayTakeCharge($subject),
                default => collect(),
            },
            default => collect(),
        };
    }

    /**
     * Le côté demandeur d'un ticket, ici : le demandeur ; sur la demande d'un interne transmise au Support, les
     * correspondants principaux, qui répondent pour lui.
     *
     * @return Collection<string, Authenticatable>
     */
    public static function requesterSide(Ticket $ticket): Collection
    {
        $requester = self::localUser($ticket->requester);

        if ($requester === null || ! Addressables::keptOut($ticket, $requester)) {
            return self::keyed(collect([$requester]));
        }

        return self::keyed(app(Recipients::class)->local($ticket)
            ->filter(fn (array $recipient): bool => $recipient['role'] === RecipientRole::Primary && Addressables::isCorrespondent($recipient['user']))
            ->map(fn (array $recipient): Authenticatable => $recipient['user']));
    }

    private static function taskTransition(ProjectTask $task, StateTransition $transition): void
    {
        $who = $transition->actor?->name ?? Terms::support();
        $line = match ($transition->move) {
            'publish', 'auto-publish' => Tma::isClient()
                ? __(':support a publié une tâche pour vous.', ['support' => Terms::support()])
                : __('La tâche est publiée chez :client.', ['client' => Terms::clientOrInstance($task->target_instance_uid)]),
            'complete' => __(':who a terminé la tâche.', ['who' => $who]),
            'cancel' => __(':who a annulé la tâche.', ['who' => $who]),
            'reopen' => __(':who a rouvert la tâche.', ['who' => $who]),
            default => __(':who : « :move ».', ['who' => $who, 'move' => rescue(fn (): string => app(TaskWorkflow::class)->move($transition->move)->labelFor($task), $transition->move, false)]),
        };

        foreach (app(Recipients::class)->local($task) as ['user' => $user, 'role' => $role]) {
            if (! $transition->actor?->isUser($user)) {
                self::send($user, NoticeEvent::Task, $task, "transition:{$transition->ulid}", $line, self::transitionExcerpt($task, $transition), $role);
            }
        }
    }

    /**
     * Écrit la notification d'une personne, puis l'envoie tout de suite (principal : par ses canaux) ou la garde pour
     * son bilan (secondaire, ou principal qui n'a choisi aucun canal). Ce qui part toujours tout de suite (`$mode`
     * imposé : relance, clôture automatique) passe par courriel à qui n'a choisi aucun canal.
     *
     * @param  (\Closure(Notice): Notification)|null  $notification  le courriel à envoyer tout de suite, s'il n'est pas le courriel commun
     */
    private static function send(Authenticatable $user, NoticeEvent $event, Trackable $subject, string $key, string $line, ?string $excerpt = null, ?RecipientRole $role = null, ?NoticeMode $mode = null, ?\Closure $notification = null): void
    {
        if (! method_exists($user, 'notify')) {
            return;
        }

        $imposed = $mode !== null;
        $mode ??= $role === RecipientRole::Primary ? NoticeMode::Immediate : NoticeMode::Grouped;
        $channels = $notification !== null ? [NoticeChannels::MAIL] : NoticeChannels::for($user);

        if ($channels === []) {
            [$mode, $channels] = $imposed ? [$mode, [NoticeChannels::MAIL]] : [NoticeMode::Grouped, []];
        }

        $notice = Notice::query()->createOrFirst(
            ['user_id' => (string) $user->getAuthIdentifier(), 'key' => $key],
            [
                'event' => $event,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'mode' => $mode,
                'data' => [
                    'kind' => $subject->kind(),
                    'ref' => $subject->number,
                    'title' => $subject->title,
                    'state' => $subject->state->getLabel(),
                    'line' => $line,
                    'excerpt' => $excerpt,
                ],
                'sent_at' => $mode === NoticeMode::Immediate ? now() : null,
                'created_at' => now(),
            ],
        );

        if ($notice->wasRecentlyCreated && $mode === NoticeMode::Immediate) {
            $user->notify($notification !== null ? $notification($notice) : new NoticeNotification($notice, $channels));
        }
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

    /** Ce que dit un passage à une personne, selon que la main lui revient ou non. */
    private static function moveLine(Ticket $ticket, StateTransition $transition, bool $holds): string
    {
        $replace = ['who' => $transition->actor?->name ?? Terms::support()];

        return match (true) {
            $transition->move === 'request-answer' => $holds ? __(':who attend votre réponse.', $replace) : __(':who a demandé des précisions.', $replace),
            $transition->move === 'submit-for-validation' => $holds ? __(':who a résolu votre demande : confirmez pour la clore, ou renvoyez-la.', $replace) : __(':who a marqué la demande comme résolue.', $replace),
            in_array($transition->move, ['submit', 'transmit'], true) => $holds ? __(':who vous confie cette demande.', $replace) : __(':who a envoyé la demande à :team.', [...$replace, 'team' => $ticket->handledBy() ?? Terms::support()]),
            $transition->move === 'take-charge' => __(':who a pris la demande en charge.', $replace),
            $transition->move === 'answer' => __(':who a répondu.', $replace),
            $transition->move === 'reopen' => $holds ? __(':who vous renvoie la demande : le résultat ne convient pas encore.', $replace) : __(':who a renvoyé la demande.', $replace),
            $transition->move === 'pause' => __(':who a mis la demande en pause.', $replace),
            in_array($transition->move, ['resume', 'auto-resume'], true) => __('La demande reprend.'),
            $transition->move === 'validate' => __(':who a confirmé la solution : le ticket est clos.', $replace),
            $transition->move === 'close' => __(':who a clos le ticket.', $replace),
            $transition->move === 'cancel' => __(':who a annulé la demande.', $replace),
            $transition->move === 'refuse' => __(':who a refusé la demande.', $replace),
            in_array($transition->move, ['publish', 'auto-publish'], true) => __(':support a ouvert ce ticket pour vous.', ['support' => Terms::support()]),
            default => __(':who : « :move ».', [...$replace, 'move' => rescue(fn (): string => app(TicketWorkflow::class)->move($transition->move)->labelFor($ticket), $transition->move, false)]),
        };
    }

    /**
     * Qui peut prendre un ticket soumis à cette application. Dans une application cliente, les membres du Support
     * n'en sont pas : le maître les prévient.
     *
     * @return Collection<string, Authenticatable>
     */
    private static function mayTakeCharge(Ticket $ticket): Collection
    {
        return self::keyed(Tma::userModel()::query()->lazy()
            ->filter(fn (Authenticatable $user): bool => (Tma::isMaster() || Agent::for($user) === null)
                && TmaGate::allows($user, TmaGate::workflowAction('take-charge'), $ticket))
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
