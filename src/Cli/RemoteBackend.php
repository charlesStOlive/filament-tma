<?php

namespace CharlesStOlive\FilamentTma\Cli;

use CharlesStOlive\FilamentTma\Sync\Http;

/**
 * Les commandes lancées dans une application cliente agissent dans le maître (le temps ne se saisit que là), par
 * son API « agent », avec le jeton personnel d'un utilisateur du maître.
 */
final class RemoteBackend implements Backend
{
    public function __construct(private string $token) {}

    private function url(string $path): string
    {
        return rtrim((string) config('filament-tma.master.url'), '/').'/api/tma/v1/agent/'.ltrim($path, '/');
    }

    public function list(array $filters): array
    {
        return Http::call(__('Centre de service'), fn () => Http::to($this->token)->get($this->url('tickets'), array_filter($filters, fn ($v) => $v !== null && $v !== false)))->json('data', []);
    }

    public function show(string $ref): array
    {
        return Http::call(__('Centre de service'), fn () => Http::to($this->token)->get($this->url('tickets/'.rawurlencode($ref))))->json('data', []);
    }

    public function logTime(string $ref, int $minutes, ?string $workedOn, ?string $summary, bool $billable, bool $internal): array
    {
        return Http::call(__('Centre de service'), fn () => Http::to($this->token)->post($this->url('tickets/'.rawurlencode($ref).'/time'), [
            'minutes' => $minutes,
            'worked_on' => $workedOn,
            'summary' => $summary,
            'billable' => $billable,
            'internal' => $internal,
        ]))->json('data', []);
    }

    public function comment(string $ref, string $body, bool $note): array
    {
        return Http::call(__('Centre de service'), fn () => Http::to($this->token)->post($this->url('tickets/'.rawurlencode($ref).'/entries'), [
            'body' => $body,
            'note' => $note,
        ]))->json('data', []);
    }
}
