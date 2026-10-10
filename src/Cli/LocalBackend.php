<?php

namespace CharlesStOlive\FilamentTma\Cli;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Auditor;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
            Auditor::as($context, fn () => app(\CharlesStOlive\FilamentTma\Workflow\TaskWorkflow::class)->run($subject, $move, $data, $context));
        } elseif (! $subject->transitions()->where('ulid', $transitionUlid)->exists()) {
            Auditor::as($context, fn () => app(\CharlesStOlive\FilamentTma\Workflow\TicketWorkflow::class)->run($subject, $move, $data, $context, $transitionUlid));
        }

        return $subject->refresh();
    }

    /**
     * Un message (commentaire, note, réponse), avec ses fichiers reçus en base64.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    public function write(string $ref, string $type, string $body, ?string $parentUlid = null, array $files = []): array
    {
        $subject = $this->find($ref);
        $context = $this->context();
        $parent = filled($parentUlid) ? $subject->entries()->where('ulid', $parentUlid)->first() : null;

        $entry = Auditor::as($context, fn () => DB::transaction(function () use ($subject, $type, $body, $context, $parent, $files): \CharlesStOlive\FilamentTma\Models\Entry {
            $entry = app(Timeline::class)->add($subject, EntryType::from($type), $body, $context, $parent);
            \CharlesStOlive\FilamentTma\Sync\Mirror::files($entry, $files);

            return $entry;
        }));

        return ['subject' => $subject->refresh(), 'entry' => $entry];
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
            ->with(['assignee', 'targetInstance'])
            ->when($this->scope !== null, fn (Builder $q) => $q->where('target_instance_uid', $this->scope))
            ->when(! ($filters['all'] ?? false) && blank($filters['state'] ?? null), fn (Builder $q) => $q->open())
            ->when(filled($filters['state'] ?? null), fn (Builder $q) => $q->whereIn('state', explode(',', (string) $filters['state'])))
            ->when(filled($filters['app'] ?? null), fn (Builder $q) => $q->whereHas('targetInstance', fn (Builder $i) => $i->where('name', $filters['app'])->orWhere('uid', $filters['app'])))
            ->when(filled($filters['project'] ?? null), fn (Builder $q) => $q->where('project', $filters['project']))
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

        return TicketView::detail($ticket, app(Timeline::class)->canReadNotes($ticket, $this->context()));
    }

    public function logTime(string $ref, int $minutes, ?string $workedOn, ?string $summary, bool $billable, bool $internal): array
    {
        $ticket = $this->find($ref);
        $context = $this->context();

        // La durée et le résumé sont enregistrés séparément (CDC §16) ; ensemble, ou pas du tout.
        return Auditor::as($context, fn (): array => DB::transaction(function () use ($ticket, $minutes, $workedOn, $summary, $billable, $internal, $context): array {
            $time = app(TimeLog::class)->add($ticket, $minutes, $workedOn, self::firstLine($summary), $context, $billable);
            $entry = filled($summary)
                ? app(Timeline::class)->add($ticket, $internal ? EntryType::Note : EntryType::Summary, self::toHtml($summary), $context, meta: ['time_entry' => $time->ulid])
                : null;

            return [
                'ticket' => $ticket->number,
                'time_entry' => ['ulid' => $time->ulid, 'minutes' => $time->minutes, 'worked_on' => $time->worked_on->toDateString(), 'billable' => $time->billable],
                'summary_entry' => $entry?->ulid,
                'time_total_minutes' => (int) $ticket->timeEntries()->sum('minutes'),
            ];
        }));
    }

    /** Une saisie de temps seule, sans résumé dans la chronologie (la saisie d'un agent depuis une application cliente). */
    public function addTime(string $ref, int $minutes, ?string $workedOn, ?string $description, bool $billable): void
    {
        $subject = $this->find($ref);
        $context = $this->context();

        Auditor::as($context, fn () => app(TimeLog::class)->add($subject, $minutes, $workedOn, $description, $context, $billable));
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

    private static function firstLine(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return Str::limit(trim(strtok(strip_tags($text), "\n")), 250);
    }
}
