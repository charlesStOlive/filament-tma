<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Cli\LocalBackend;
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

    public function time(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'worked_on' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'billable' => ['boolean'],
            'internal' => ['boolean'],
        ]);

        return $this->respond(fn () => $this->backend($request)->logTime($ref, $data['minutes'], $data['worked_on'] ?? null, $data['summary'] ?? null, $data['billable'] ?? true, $data['internal'] ?? false), 201);
    }

    public function entry(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'note' => ['boolean']]);

        return $this->respond(fn () => $this->backend($request)->comment($ref, $data['body'], $data['note'] ?? false), 201);
    }

    private function backend(Request $request): LocalBackend
    {
        return new LocalBackend($request->user(config('filament-tma.api.guard', 'api')), viaApi: true);
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
