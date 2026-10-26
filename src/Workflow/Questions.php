<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Events\EntryChanged;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Les questions du Support sur une tâche projet. Une tâche informe ; une question engage : le Support l'écrit dans la
 * tâche (un message marqué `meta.question`), à des personnes du client choisies parmi les destinataires de la tâche
 * (`meta.question.to` : leurs identifiants dans l'application cliente). Elles l'apprennent comme un ticket qui les
 * attend : la cloche, et l'e-mail direct ou le bilan du matin (« À faire »).
 *
 * Une question est ouverte jusqu'à ce que quelqu'un du client y réponde (une réponse au message), ou que le Support la
 * retire (`meta.question.withdrawn_at`). Elle n'a ni tour, ni relance, ni clôture automatique : la tâche ne change pas
 * d'état, et le temps passé reste au projet. Le message, ses réponses et le retrait voyagent avec la chronologie : la
 * synchronisation n'a rien de plus à faire.
 */
final class Questions
{
    public const OPEN = 'open';

    public const ANSWERED = 'answered';

    public const WITHDRAWN = 'withdrawn';

    /** Poser une question : le Support, dans le maître, sur une tâche publiée chez le client où il peut écrire. */
    public function canAsk(ProjectTask $task, Context $context): bool
    {
        return Tma::isMaster() && $task->isShared() && $task->published_at !== null
            && app(Timeline::class)->canAdd($task, EntryType::Comment, $context);
    }

    /**
     * Pose la question à des personnes du client, parmi les destinataires de la tâche (`addressables()`).
     *
     * @param  array<int, string>  $to  leurs identifiants dans l'application cliente
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    public function ask(ProjectTask $task, ?string $body, array $to, Context $context, array $uploads = []): Entry
    {
        if (! $this->canAsk($task, $context)) {
            throw new AuthorizationException(__('filament-tma::questions.errors.cannot_ask'));
        }

        // Des clés numériques : PHP en fait des entiers, on les garde en texte.
        $to = array_values(array_intersect(array_map('strval', array_keys(self::addressables($task))), array_map('strval', $to)));

        if ($to === []) {
            throw ValidationException::withMessages(['to' => __('filament-tma::questions.errors.choose_recipients')]);
        }

        return app(Timeline::class)->add($task, EntryType::Comment, $body, $context, meta: ['question' => ['to' => $to]], uploads: $uploads);
    }

    /** Répondre : à une question qui n'est pas retirée, qui peut écrire sur la tâche, des deux côtés. */
    public function canAnswer(Entry $question, Context $context): bool
    {
        return $question->isQuestion() && ! $question->trashed() && $question->subject instanceof ProjectTask
            && self::status($question) !== self::WITHDRAWN
            && app(Timeline::class)->canAdd($question->subject, EntryType::Comment, $context);
    }

    /**
     * Répond à la question (une réponse au message). Venue du client, la première la clôt.
     *
     * @param  array<int, array{path: string, name?: string}>  $uploads
     */
    public function answer(Entry $question, ?string $body, Context $context, array $uploads = []): Entry
    {
        if (! $this->canAnswer($question, $context)) {
            throw new AuthorizationException(__('filament-tma::questions.errors.cannot_answer'));
        }

        return app(Timeline::class)->add($question->subject, EntryType::Comment, $body, $context, $question, uploads: $uploads);
    }

    /** Retirer une question encore ouverte : le Support, dans le maître. */
    public function canWithdraw(Entry $question, Context $context): bool
    {
        return Tma::isMaster() && $question->isQuestion() && ! $question->trashed() && $question->subject instanceof ProjectTask
            && self::status($question) === self::OPEN
            && app(Timeline::class)->canAdd($question->subject, EntryType::Comment, $context);
    }

    /** Retire la question : plus personne ne l'attend ; le client l'apprend avec l'état recopié. */
    public function withdraw(Entry $question, Context $context): Entry
    {
        if (! $this->canWithdraw($question, $context)) {
            throw new AuthorizationException(__('filament-tma::questions.errors.cannot_withdraw'));
        }

        DB::transaction(function () use ($question): void {
            $meta = $question->meta;
            $meta['question']['withdrawn_at'] = now()->toIso8601String();
            $question->forceFill(['meta' => $meta])->save();
            $question->subject->touchActivity();
        });

        event(new EntryChanged($question, $context, EntryChanged::WITHDRAWN));

        return $question;
    }

    /** `OPEN`, `ANSWERED` ou `WITHDRAWN` ; null pour un message qui n'est pas une question. */
    public static function status(Entry $question): ?string
    {
        return match (true) {
            ! $question->isQuestion() => null,
            filled($question->meta['question']['withdrawn_at'] ?? null) => self::WITHDRAWN,
            self::answerOf($question) !== null => self::ANSWERED,
            default => self::OPEN,
        };
    }

    /** La première réponse venue du client : celle qui clôt la question. */
    public static function answerOf(Entry $question): ?Entry
    {
        $client = $question->subject?->clientInstanceUid();

        return $question->replies->first(fn (Entry $reply): bool => $reply->author?->instance_uid === $client);
    }

    /** @return array<int, string> les identifiants, dans l'application cliente, des personnes à qui elle est posée */
    public static function to(Entry $question): array
    {
        return array_map('strval', (array) ($question->meta['question']['to'] ?? []));
    }

    /** @return array<int, string> les noms des personnes à qui elle est posée */
    public static function names(Entry $question): array
    {
        $to = self::to($question);

        return Actor::query()
            ->where('instance_uid', $question->subject?->clientInstanceUid())
            ->whereIn('remote_id', $to)
            ->get()
            ->sortBy(fn (Actor $actor): int|false => array_search((string) $actor->remote_id, $to, true))
            ->pluck('name')
            ->values()
            ->all();
    }

    /** Elle est posée à cette personne d'ici (application cliente). */
    public static function isAddressedTo(Entry $question, ?Authenticatable $user): bool
    {
        return $user !== null && $question->isQuestion() && Tma::isHere($question->subject?->clientInstanceUid())
            && in_array((string) $user->getAuthIdentifier(), self::to($question), true);
    }

    /**
     * Les personnes d'ici à qui elle est posée (application cliente).
     *
     * @return Collection<int, Authenticatable>
     */
    public static function addressees(Entry $question): Collection
    {
        if (! Tma::isHere($question->subject?->clientInstanceUid())) {
            return collect();
        }

        return Tma::userModel()::query()->whereKey(self::to($question))->get();
    }

    /**
     * Les personnes du client à qui la poser : les destinataires de la tâche dans son application, les principaux
     * d'abord.
     *
     * @return array<string, string> identifiant dans l'application cliente => nom
     */
    public static function addressables(ProjectTask $task): array
    {
        return $task->recipients()->with('actor')->get()
            ->filter(fn (Recipient $r): bool => $r->actor !== null && $r->actor->instance_uid === $task->clientInstanceUid() && ! Addressables::isGateway($r->actor))
            ->sortBy(fn (Recipient $r): string => ($r->role === RecipientRole::Primary ? '0' : '1').$r->actor->name)
            ->mapWithKeys(fn (Recipient $r): array => [(string) $r->actor->remote_id => $r->role === RecipientRole::Secondary
                ? __('filament-tma::questions.in_copy', ['name' => $r->actor->name])
                : (string) $r->actor->name])
            ->all();
    }

    /** @return array<int, string> à qui la poser d'office : les destinataires principaux du client */
    public static function defaultAddressees(ProjectTask $task): array
    {
        return $task->recipients()->with('actor')->where('role', RecipientRole::Primary->value)->get()
            ->filter(fn (Recipient $r): bool => $r->actor !== null && $r->actor->instance_uid === $task->clientInstanceUid() && ! Addressables::isGateway($r->actor))
            ->map(fn (Recipient $r): string => (string) $r->actor->remote_id)
            ->values()
            ->all();
    }

    /**
     * Les questions ouvertes posées à cette personne (application cliente), les plus anciennes d'abord, sur des tâches
     * qui acceptent encore des messages.
     *
     * @return Collection<int, Entry>
     */
    public static function openFor(?Authenticatable $user): Collection
    {
        if ($user === null || ! Tma::isClient()) {
            return collect();
        }

        return Entry::query()
            ->where('subject_type', (new ProjectTask)->getMorphClass())
            ->messages()
            ->whereNull('parent_id')
            ->whereNotNull('meta->question')
            ->whereNull('meta->question->withdrawn_at')
            ->with(['subject.project', 'author', 'replies.author'])
            ->oldest('id')
            ->get()
            ->filter(fn (Entry $question): bool => $question->subject instanceof ProjectTask && $question->subject->acceptsMessages()
                && self::isAddressedTo($question, $user) && self::status($question) === self::OPEN)
            ->values();
    }
}
