<?php

namespace CharlesStOlive\FilamentTma\Cli;

/**
 * Ce que les commandes `tma:*` savent faire, ici (`LocalBackend`) ou chez le maître (`RemoteBackend`). Les
 * réponses sont des tableaux simples, prêts pour `--json`.
 */
interface Backend
{
    /**
     * @param  array{type?: string, state?: string|null, app?: string|null, project?: string|null, search?: string|null, mine?: bool, all?: bool, limit?: int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters): array;

    /** @return array<string, mixed> */
    public function show(string $ref): array;

    /** @return array<string, mixed> la saisie de temps et le résumé enregistrés */
    public function logTime(string $ref, int $minutes, ?string $workedOn, ?string $summary, bool $billable, bool $internal): array;

    /** @return array<string, mixed> */
    public function comment(string $ref, string $body, bool $note): array;
}
