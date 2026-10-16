<?php

namespace CharlesStOlive\FilamentTma\Cli;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Auditor;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Les commandes, sur les tickets de cette application, au nom d'un utilisateur (reconnu par son jeton). Mêmes droits
 * que dans l'interface ; tout est enregistré avec l'origine « commande ». L'API « agent » du maître passe aussi par
 * ici (origine « API »).
 */
final class LocalBackend implements Backend
{
    /**
     * @param  string|null  $scope  l'application cliente à laquelle se limite un jeton d'agent (voir `AuthenticateAgent`)
     */
    public function __construct(private Authenticatable $user, private bool $viaApi = false, private ?string $scope = null) {}

    private function context(): Context
    {
        return $this->viaApi
            ? new Context($this->user, Actor::forUser($this->user), Origin::Api)
            : Context::command($this->user);
    }

    /**
     * Un passage, au nom de l'utilisateur, avec ses droits (un agent qui agit depuis une application cliente).
     *
     * @param  array<string, mixed>  $data
     */
    public function move(string $ref, string $move, array $data, ?string $transitionUlid = null): Ticket|ProjectTask
    {
        $subject = $this->find($ref);
        $context = $this->context();

        if ($subject instanceof ProjectTask) {
            Auditor::as($context, fn () => app(TaskWorkflow::class)->run($subject, $move, $data, $context));
        } elseif (! $subject->transitions()->where('ulid', $transitionUlid)->exists()) {
            Auditor::as($context, fn () => app(TicketWorkflow::class)->run($subject, $move, $data, $context, $transitionUlid));
        }

        return $subject->refresh();
    }

    /**
     * Un message (commentaire, note, réponse), avec ses fichiers reçus en base64.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<string, mixed>  $meta  ce que l'entrée porte en plus (`reminder` : une relance)
     */
    public function write(string $ref, string $type, string $body, ?string $parentUlid = null, array $files = [], bool $private = false, array $meta = []): array
    {
        $subject = $this->find($ref);
        $context = $this->context();
        $parent = filled($parentUlid) ? $subject->entries()->where('ulid', $parentUlid)->first() : null;

        $entry = Auditor::as($context, fn () => DB::transaction(function () use ($subject, $type, $body, $context, $parent, $files, $private, $meta): Entry {
            $entry = app(Timeline::class)->add($subject, EntryType::from($type), $body, $context, $parent, $meta, private: $private);
            Mirror::files($entry, $files);

            return $entry;
        }));

        return ['subject' => $subject->refresh(), 'entry' => $entry];
    }

    /**
     * Change une entrée : son texte et ses fichiers (`body`, `files` en base64, `remove_files`), l'archivage d'une
     * note (`archived`), ou sa suppression (`delete`). Les droits sont ceux de `Timeline`.
     *
     * @param  array<string, mixed>  $data
     */
    public function changeEntry(string $ref, string $ulid, array $data): Ticket|ProjectTask
    {
        $subject = $this->find($ref);
        $context = $this->context();
        $entry = $subject->entries()->where('ulid', $ulid)->firstOrFail();
        $timeline = app(Timeline::class);

        Auditor::as($context, fn () => DB::transaction(function () use ($timeline, $entry, $context, $data): void {
            match (true) {
                (bool) ($data['delete'] ?? false) => $timeline->delete($entry, $context),
                array_key_exists('archived', $data) => $timeline->archive($entry, (bool) $data['archived'], $context),
                default => $this->editEntry($entry, $data, $context),
            };
        }));

        return $subject->refresh();
    }

    /** @param  array<string, mixed>  $data */
    private function editEntry(Entry $entry, array $data, Context $context): void
    {
        $timeline = app(Timeline::class);

        if (! $timeline->canEdit($entry, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez plus modifier cette entrée.'));
        }

        // Les fichiers arrivent en base64 : rangés d'abord, ils comptent pour une note sans texte.
        Mirror::files($entry, (array) ($data['files'] ?? []));
        $timeline->edit($entry->refresh(), $data['body'] ?? null, $context, removeFiles: (array) ($data['remove_files'] ?? []),
            private: isset($data['private']) ? (bool) $data['private'] : null);
    }

    public function list(array $filters): array
    {
        $type = $filters['type'] ?? 'all';
        $tickets = in_array($type, ['all', 'tickets'], true) && $this->can('viewAny', Ticket::class) ? $this->tickets($filters) : collect();
        $tasks = in_array($type, ['all', 'tasks'], true) && $this->can('viewAny', ProjectTask::class) ? $this->tasks($filters) : collect();

        if (! $this->can('viewAny', Ticket::class) && ! $this->can('viewAny', ProjectTask::class)) {
            throw new AuthorizationException(__('Vous n’avez pas accès à ces tickets.'));
        }

        return $tickets->concat($tasks)
            ->sortByDesc('last_activity_at')
            ->take((int) ($filters['limit'] ?? 50))
            ->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function tasks(array $filters): Collection
    {
        $actor = Actor::forUser($this->user);

        return ProjectTask::query()
            ->with(['assignee', 'targetInstance', 'project'])
            ->when($this->scope !== null, fn (Builder $q) => $q->where('target_instance_uid', $this->scope))
            ->when(! ($filters['all'] ?? false) && blank($filters['state'] ?? null), fn (Builder $q) => $q->open())
            ->when(filled($filters['state'] ?? null), fn (Builder $q) => $q->whereIn('state', explode(',', (string) $filters['state'])))
            ->when(filled($filters['app'] ?? null), fn (Builder $q) => $q->whereHas('targetInstance', fn (Builder $i) => $i->where('name', $filters['app'])->orWhere('uid', $filters['app'])))
            ->when(filled($filters['project'] ?? null), fn (Builder $q) => $q->whereHas('project', fn (Builder $p) => $p->where('title', $filters['project'])->orWhere('ulid', strtolower((string) $filters['project']))))
            ->when(filled($filters['search'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', '%'.$filters['search'].'%')->orWhere('number', $filters['search'])))
            ->when($filters['mine'] ?? false, fn (Builder $q) => $q->where('assignee_actor_id', $actor->getKey()))
            ->orderByDesc('last_activity_at')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->filter(fn (ProjectTask $task): bool => $this->can('view', $task))
            ->map(fn (ProjectTask $task): array => TicketView::summary($task))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function tickets(array $filters): Collection
    {
        $actor = Actor::forUser($this->user);

        return Ticket::query()
            ->with(['requester', 'assignee', 'sourceInstance'])
            ->when($this->scope !== null, fn (Builder $q) => $q->where('source_instance_uid', $this->scope))
            ->when(! ($filters['all'] ?? false) && blank($filters['state'] ?? null), fn (Builder $q) => $q->open())
            ->when(filled($filters['state'] ?? null), fn (Builder $q) => $q->whereIn('state', explode(',', (string) $filters['state'])))
            ->when(filled($filters['app'] ?? null), fn (Builder $q) => $q->whereHas('sourceInstance', fn (Builder $i) => $i->where('name', $filters['app'])->orWhere('uid', $filters['app'])))
            ->when(filled($filters['search'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', '%'.$filters['search'].'%')->orWhere('number', $filters['search'])))
            ->when($filters['mine'] ?? false, fn (Builder $q) => $q->where('assignee_actor_id', $actor->getKey()))
            ->orderByDesc('last_activity_at')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->filter(fn (Ticket $ticket): bool => $this->can('view', $ticket))
            ->map(fn (Ticket $ticket): array => TicketView::summary($ticket))
            ->values();
    }

    public function show(string $ref): array
    {
        $ticket = $this->find($ref);

        return TicketView::detail($ticket, $this->user);
    }

    public function logTime(string $ref, int $minutes, ?string $workedOn, ?string $summary, bool $billable, bool $internal, ?string $entry = null): array
    {
        $ticket = $this->find($ref);
        $context = $this->context();

        if (filled($summary) && filled($entry)) {
            throw ValidationException::withMessages(['entry_ulid' => __('Un résumé écrit un nouveau message : ne désignez pas en plus un message existant.')]);
        }

        $on = filled($entry) ? $this->entryOf($ticket, (string) $entry) : null;

        // Le temps porte sur le message qui dit ce qui a été fait (CDC §16) : le résumé l'écrit dans l'historique (ou
        // une note sur le ticket, avec `$internal`), ensemble ou pas du tout ; ou c'est un message qui existe déjà.
        return Auditor::as($context, function () use ($ticket, $minutes, $workedOn, $summary, $billable, $internal, $context, $on): array {
            $time = filled($summary)
                ? app(TimeLog::class)->addWithMessage($ticket, $minutes, $workedOn, self::toHtml($summary), $context, $billable, $internal ? EntryType::Note : EntryType::Comment)
                : app(TimeLog::class)->add($ticket, $minutes, $workedOn, $context, $billable, $on);

            return [
                'ticket' => $ticket->number,
                'time_entry' => ['ulid' => $time->ulid, 'minutes' => $time->minutes, 'worked_on' => $time->worked_on->toDateString(), 'billable' => $time->billable],
                'summary_entry' => filled($summary) ? $time->entry?->ulid : null,
                'entry' => $time->entry?->ulid,
                'time_total_minutes' => (int) $ticket->timeEntries()->sum('minutes'),
            ];
        });
    }

    /** Un message ou une note de la fiche, par son ulid, que l'utilisateur peut lire. */
    private function entryOf(Ticket|ProjectTask $ticket, string $ulid): Entry
    {
        return $ticket->entries()->visibleTo($this->user)->where('ulid', strtolower($ulid))->first()
            ?? throw new InvalidArgumentException(__('Message ou note introuvable sur :ref : :entry', ['ref' => $ticket->number, 'entry' => $ulid]));
    }

    public function comment(string $ref, string $body, bool $note): array
    {
        $ticket = $this->find($ref);
        $context = $this->context();
        $entry = Auditor::as($context, fn () => app(Timeline::class)->add($ticket, $note ? EntryType::Note : EntryType::Comment, self::toHtml($body), $context));

        return ['ticket' => $ticket->number, 'entry' => $entry->ulid, 'type' => $entry->type->value];
    }

    /** Un ticket (DES-0042) ou une tâche projet (T-0007), par son numéro ou son ulid, que l'utilisateur peut voir. */
    public function find(string $ref): Ticket|ProjectTask
    {
        $matches = Ticket::query()->where('number', $ref)->orWhere('ulid', strtolower($ref))->get()
            ->concat(ProjectTask::query()->where('number', $ref)->orWhere('ulid', strtolower($ref))->get());

        if ($matches->count() > 1) {
            throw new InvalidArgumentException(__('Plusieurs tickets portent le numéro :ref : donnez son ulid.', ['ref' => $ref]));
        }

        $ticket = $matches->first() ?? throw new InvalidArgumentException(__('Ticket ou tâche introuvable : :ref', ['ref' => $ref]));

        if ($this->scope !== null && $ticket->clientInstanceUid() !== $this->scope) {
            throw new InvalidArgumentException(__('Ticket ou tâche introuvable : :ref', ['ref' => $ref]));
        }

        $this->authorize('view', $ticket);

        return $ticket;
    }

    private function can(string $ability, mixed $subject): bool
    {
        $class = is_object($subject) ? $subject::class : $subject;

        return Gate::getPolicyFor($class) === null || Gate::forUser($this->user)->allows($ability, $subject);
    }

    private function authorize(string $ability, mixed $subject): void
    {
        if (! $this->can($ability, $subject)) {
            throw new AuthorizationException(__('Vous n’avez pas accès à ces tickets.'));
        }
    }

    /** Un texte brut (d'une commande) en HTML simple : un paragraphe par bloc, les retours à la ligne gardés. */
    public static function toHtml(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        if ($text !== strip_tags($text)) {
            return $text; // déjà du HTML : il sera nettoyé à l'enregistrement
        }

        return collect(preg_split("/\R{2,}/", trim($text)))
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }
}
