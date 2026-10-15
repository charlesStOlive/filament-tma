<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Cli\LocalBackend;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateAgent;
use CharlesStOlive\FilamentTma\Sync\Payload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** L'API « agent » du maître : ce que font les commandes `tma:*`, au nom de l'utilisateur du jeton. */
class AgentController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->backend($request)->list([
            'type' => (string) $request->query('type', 'all'),
            'project' => $request->query('project'),
            'state' => $request->query('state'),
            'app' => $request->query('app'),
            'search' => $request->query('search'),
            'mine' => $request->boolean('mine'),
            'all' => $request->boolean('all'),
            'limit' => min(200, (int) $request->query('limit', 50)),
        ]));
    }

    public function show(Request $request, string $ref): JsonResponse
    {
        return $this->respond(fn () => $this->backend($request)->show($ref));
    }

    /**
     * Une saisie de temps, sur le message qui dit ce qui a été fait (`summary` l'écrit ; `internal` : une note), sur
     * un message ou une note qui existe déjà (`entry_ulid`), ou sur la fiche entière. La réponse donne aussi l'état à
     * recopier (`snapshot`), pour un agent qui saisit son temps depuis une application cliente.
     */
    public function time(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'worked_on' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:500000'],
            'entry_ulid' => ['nullable', 'string', 'max:26'],
            'billable' => ['boolean'],
            'internal' => ['boolean'],
        ]);

        return $this->respond(function () use ($request, $ref, $data): array {
            $backend = $this->backend($request);
            $result = $backend->logTime($ref, $data['minutes'], $data['worked_on'] ?? null, $data['summary'] ?? null, $data['billable'] ?? true, $data['internal'] ?? false, $data['entry_ulid'] ?? null);

            return [...$result, 'snapshot' => Payload::snapshot($backend->find($ref))];
        }, 201);
    }

    /**
     * Un message ou une note (sur le ticket entier). Avec `type` (comment, note), `parent_ulid` et `files` (base64), c'est
     * l'écriture d'un agent depuis une application cliente : la réponse donne alors aussi l'état du ticket à recopier.
     */
    public function entry(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500000'],
            'note' => ['boolean'],
            'type' => ['nullable', 'in:comment,note'],
            'parent_ulid' => ['nullable', 'string', 'size:26'],
            'files' => ['array'],
            'private' => ['boolean'],
        ]);

        if (! isset($data['type'])) {
            return $this->respond(fn () => $this->backend($request)->comment($ref, $data['body'], $data['note'] ?? false), 201);
        }

        return $this->respond(function () use ($request, $ref, $data): array {
            $result = $this->backend($request)->write($ref, $data['type'], $data['body'], $data['parent_ulid'] ?? null, $data['files'] ?? [], $data['private'] ?? false);

            return ['entry' => Payload::entry($result['entry']), 'snapshot' => Payload::snapshot($result['subject'])];
        }, 201);
    }

    /**
     * L'agent change son message ou sa note (`body`, avec `files` en base64, `remove_files` et `private`), archive une note
     * (`archived`) ou supprime la sienne (`delete`) ; la réponse donne l'état à recopier.
     */
    public function changeEntry(Request $request, string $ref, string $ulid): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:500000'],
            'files' => ['array'],
            'remove_files' => ['array'],
            'remove_files.*' => ['string'],
            'private' => ['boolean'],
            'archived' => ['boolean'],
            'delete' => ['boolean'],
        ]);

        return $this->respond(fn () => ['snapshot' => Payload::snapshot(
            $this->backend($request)->changeEntry($ref, $ulid, $data),
        )]);
    }

    /** Un passage (prendre en charge, demander une réponse…) au nom de l'agent ; la réponse donne l'état à recopier. */
    public function move(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'move' => ['required', 'string'],
            'data' => ['array'],
            'transition_ulid' => ['nullable', 'string', 'size:26'],
        ]);

        return $this->respond(fn () => ['snapshot' => Payload::snapshot(
            $this->backend($request)->move($ref, $data['move'], $data['data'] ?? [], $data['transition_ulid'] ?? null),
        )]);
    }

    private function backend(Request $request): LocalBackend
    {
        return new LocalBackend(
            $request->user(config('filament-tma.api.guard', 'api')),
            viaApi: true,
            scope: $request->attributes->get(AuthenticateAgent::SCOPE),
        );
    }

    private function respond(\Closure $call, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $call()], $status);
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }
    }
}
