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

    /**
     * Une saisie de temps, sur le message (ou la note) qui dit ce qui a été fait : `$summary` l'écrit, ou `$entry`
     * (ulid) en désigne un qui existe déjà sur la fiche. Sans l'un ni l'autre, sur la fiche entière.
     *
     * @return array<string, mixed> la saisie de temps et le message sur lequel elle porte
     */
    public function logTime(string $ref, int $minutes, ?string $workedOn, ?string $summary, bool $billable, bool $internal, ?string $entry = null): array;

    /** @return array<string, mixed> */
    public function comment(string $ref, string $body, bool $note): array;
}
