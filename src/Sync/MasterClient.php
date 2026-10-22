<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;

/**
 * Une application cliente appelle le maître. Ces appels sont synchrones : s'ils échouent, l'opération est annulée
 * avec un message d'erreur (addendum §7).
 */
final class MasterClient
{
    private function http(): PendingRequest
    {
        $token = config('filament-tma.master.token');

        if (! Tma::serviceDeskAvailable() || blank($token)) {
            throw new SyncException(__('Le Support n’est pas configuré (TMA_MASTER_URL, TMA_MASTER_TOKEN).'));
        }

        return Http::to($token);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('filament-tma.master.url'), '/').'/api/tma/v1/'.ltrim($path, '/');
    }

    /** @return array<string, mixed> */
    public function ping(): array
    {
        return Http::call(__('Test de connexion au Support'), fn () => $this->http()->get($this->url('master/ping')))->json();
    }

    /**
     * Transmet un ticket au Support, avec ses entrées et leurs fichiers. Le maître le crée (ou
     * le retrouve : la transmission est idempotente) et renvoie son état.
     *
     * @return array<string, mixed> l'état du ticket chez le maître (`Payload::snapshot()`)
     */
    public function transmit(Ticket $ticket, StateTransition $transition, Context $context): array
    {
        $entries = $ticket->entries()->with(['author', 'parent', 'media'])->oldest('id')->get()
            ->map(fn (Entry $entry): array => Payload::entry($entry, withFiles: true))
            ->values()->all();

        return Http::call(__('Transmission au Support'), fn () => $this->http()->post($this->url('tickets'), [
            'ticket' => Payload::ticket($ticket),
            'entries' => $entries,
            'transition' => Payload::transition($transition),
            'files' => Payload::files($ticket),
            'recipients' => Recipients::payload($ticket),
        ]))->json();
    }

    /**
     * Les destinataires d'un ticket ou d'une tâche du Support, vus d'ici (`Workflow\Recipients::fromClient()`).
     *
     * @param  array<int, array<string, mixed>>  $recipients
     * @return array<string, mixed> l'état chez le maître
     */
    public function recipients(Trackable $subject, array $recipients, Context $context): array
    {
        return Http::call(__('Support'), fn () => $this->http()->post($this->url(($subject->kind() === 'task' ? 'tasks' : 'tickets')."/{$subject->ulid}/recipients"), [
            'recipients' => $recipients,
            'actor' => Payload::actor($context->actor),
        ]))->json();
    }

    /**
     * Envoie la demande modifiée par son auteur (titre, description, priorité, pièces jointes) : le maître la prend
     * tant qu'aucun message ne l'a suivie.
     *
     * @return array<string, mixed> l'état du ticket chez le maître
     */
    public function updateDemand(Ticket $ticket, Context $context): array
    {
        $files = Payload::files($ticket);

        return Http::call(__('Support'), fn () => $this->http()->patch($this->url("tickets/{$ticket->ulid}"), [
            'ticket' => Arr::only(Payload::ticket($ticket), ['title', 'description', 'priority']),
            'files' => $files,
            'file_uuids' => array_column($files, 'uuid'),
            'actor' => Payload::actor($context->actor),
        ]))->json();
    }

    /**
     * Fait un mouvement du demandeur sur un ticket du Support : c'est le maître qui l'exécute.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> l'état du ticket chez le maître
     */
    public function runMove(Ticket $ticket, string $move, array $data, Context $context, string $transitionUlid): array
    {
        return Http::call(__('Support'), fn () => $this->http()->post($this->url("tickets/{$ticket->ulid}/moves"), [
            'move' => $move,
            'data' => $data,
            'actor' => Payload::actor($context->actor),
            'transition_ulid' => $transitionUlid,
        ]))->json();
    }

    /**
     * Transmet au Support le jeton par lequel il appellera cette application ; il la rappelle aussitôt pour
     * vérifier la connexion dans l'autre sens.
     *
     * @return array{instance: string, callback: bool, callback_error: string|null}
     */
    public function register(string $token): array
    {
        return Http::call(__('Transmission du jeton au Support'), fn () => $this->http()->post($this->url('master/register'), [
            'token' => $token,
        ]))->json();
    }

    /**
     * Un membre du Support fait un passage dans le maître, en son nom (son jeton) ; la réponse est l'état du
     * ticket à recopier.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function agentMove(Agent $agent, Trackable $subject, string $move, array $data, ?string $transitionUlid = null): array
    {
        return Http::call(__('Support'), fn () => $this->agentHttp($agent)->post($this->url("agent/tickets/{$subject->ulid}/moves"), [
            'move' => $move,
            'data' => $data,
            'transition_ulid' => $transitionUlid,
        ]))->json('data.snapshot');
    }

    /**
     * Un agent écrit dans la chronologie, dans le maître, en son nom.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array{entry: array<string, mixed>, snapshot: array<string, mixed>}
     */
    public function agentEntry(Agent $agent, Trackable $subject, string $type, string $body, ?string $parentUlid, array $files = [], bool $private = false, bool $reminder = false): array
    {
        return Http::call(__('Support'), fn () => $this->agentHttp($agent)->post($this->url("agent/tickets/{$subject->ulid}/entries"), [
            'type' => $type,
            'body' => $body,
            'parent_ulid' => $parentUlid,
            'files' => $files,
            'private' => $private,
            'reminder' => $reminder,
        ]))->json('data');
    }

    /**
     * Un agent change, dans le maître et en son nom, son message ou sa note (`body`, `files`, `remove_files`,
     * `private`), archive une note (`archived`) ou supprime la sienne (`delete`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> l'état du ticket ou de la tâche à recopier
     */
    public function agentChangeEntry(Agent $agent, Trackable $subject, string $entryUlid, array $data): array
    {
        return Http::call(__('Support'), fn () => $this->agentHttp($agent)->patch($this->url("agent/tickets/{$subject->ulid}/entries/{$entryUlid}"), $data))
            ->json('data.snapshot');
    }

    /**
     * Un agent saisit du temps, dans le maître, en son nom : sur un nouveau message (`$message`, HTML), sur un
     * message ou une note de la fiche (`$entryUlid`), ou sur la fiche entière.
     *
     * @return array<string, mixed> l'état du ticket ou de la tâche à recopier (ses totaux de temps, le message)
     */
    public function agentTime(Agent $agent, Trackable $subject, int $minutes, ?string $workedOn, bool $billable, ?string $message = null, ?string $entryUlid = null): array
    {
        return Http::call(__('Support'), fn () => $this->agentHttp($agent)->post($this->url("agent/tickets/{$subject->ulid}/time"), array_filter([
            'minutes' => $minutes,
            'worked_on' => $workedOn,
            'billable' => $billable,
            'summary' => $message,
            'entry_ulid' => $entryUlid,
        ], fn (mixed $value): bool => $value !== null)))->json('data.snapshot');
    }

    /**
     * Les saisies de temps d'une fiche, lues dans le maître pour un membre du Support (le client n'en a que les totaux).
     *
     * @return array<int, array{ulid: string, actor: string|null, worked_on: string, minutes: int, billable: bool, invoiced: bool, entry_ulid: string|null}>
     */
    public function agentTimeEntries(Agent $agent, Trackable $subject): array
    {
        return (array) Http::call(__('Support'), fn () => $this->agentHttp($agent)->get($this->url("agent/tickets/{$subject->ulid}/time")))
            ->json('data.time_entries', []);
    }

    /**
     * Un membre du Support modifie (`minutes`, `worked_on`, `billable`, `entry_ulid`, `summary`) ou supprime (`delete`)
     * une saisie de temps, dans le maître, en son nom.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> l'état du ticket ou de la tâche à recopier
     */
    public function agentChangeTime(Agent $agent, Trackable $subject, string $timeUlid, array $data): array
    {
        return Http::call(__('Support'), fn () => $this->agentHttp($agent)->patch($this->url("agent/tickets/{$subject->ulid}/time/{$timeUlid}"), $data))
            ->json('data.snapshot');
    }

    private function agentHttp(Agent $agent): PendingRequest
    {
        if (blank(config('filament-tma.master.url'))) {
            throw new SyncException(__('Le Support n’est pas configuré (TMA_MASTER_URL).'));
        }

        return Http::to($agent->token);
    }

    /**
     * Envoie une entrée (message, réponse, note) avec ses fichiers ; renvoyée, elle est modifiée, archivée ou
     * supprimée là-bas.
     *
     * @return array<string, mixed> l'entrée telle que le maître l'a enregistrée (le numéro d'une note)
     */
    public function sendEntry(Entry $entry): array
    {
        return (array) Http::call(__('Envoi au Support'), fn () => $this->http()->post(
            $this->url(($entry->subject->kind() === 'task' ? 'tasks' : 'tickets')."/{$entry->subject->ulid}/entries"),
            Payload::entry($entry, withFiles: true),
        ))->json('entry');
    }
}
