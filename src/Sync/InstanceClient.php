<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\Client\PendingRequest;

/** Le maître appelle une application cliente. */
final class InstanceClient
{
    public function __construct(private Instance $instance) {}

    private function http(): PendingRequest
    {
        if (blank($this->instance->token)) {
            throw new SyncException(__('filament-tma::sync.errors.no_token', ['name' => $this->instance->name]));
        }

        return Http::to($this->instance->token);
    }

    /** @return array<string, mixed> */
    public function ping(): array
    {
        return Http::call(__('filament-tma::sync.operations.test'), fn () => $this->http()->get($this->instance->apiUrl('ping')))->json();
    }

    /** @return array<int, array{id: string, name: string, email: string|null, service_desk: bool}> */
    public function users(): array
    {
        return Http::call(__('filament-tma::sync.operations.users', ['name' => $this->instance->name]), fn () => $this->http()
            ->get($this->instance->apiUrl('users')))
            ->json('data', []);
    }

    /**
     * Envoie l'état complet d'un ticket ; la réponse dit quelles entrées attendent encore leurs fichiers.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string> les ulid des entrées dont le client n'a pas les fichiers
     */
    public function pushTicket(array $snapshot): array
    {
        return Http::call(__('filament-tma::sync.operations.push_ticket', ['number' => $snapshot['ticket']['number'] ?? '?']), fn () => $this->http()
            ->post($this->instance->apiUrl('sync'), $snapshot))
            ->json('missing_media', []);
    }

    /** @param  array<string, mixed>  $project  `Payload::project()` */
    public function pushProject(array $project): void
    {
        Http::call(__('filament-tma::sync.operations.push_project', ['title' => $project['title'] ?? '?']), fn () => $this->http()
            ->post($this->instance->apiUrl('sync'), ['kind' => 'project', 'project' => $project]));
    }

    /**
     * Relie un agent : l'utilisateur de l'application qui a cet e-mail agira pour le Support, avec ce jeton.
     *
     * @return array{name: string}
     */
    public function linkAgent(string $email, string $masterUserId, string $name, string $token): array
    {
        return Http::call(__('filament-tma::sync.operations.link_agent', ['email' => $email]), fn () => $this->http()->post($this->instance->apiUrl('agents'), [
            'email' => $email,
            'name' => $name,
            'master_uid' => Tma::instanceUid(),
            'master_user_id' => $masterUserId,
            'token' => $token,
        ]))->json();
    }

    public function unlinkAgent(string $email): void
    {
        Http::call(__('filament-tma::sync.operations.unlink_agent', ['email' => $email]), fn () => $this->http()->post($this->instance->apiUrl('agents/unlink'), ['email' => $email]));
    }

    /** @param  array<int, array<string, mixed>>  $files */
    public function pushMedia(string $owner, string $ulid, array $files): void
    {
        Http::call(__('filament-tma::sync.operations.push_files'), fn () => $this->http()
            ->post($this->instance->apiUrl("media/{$owner}/{$ulid}"), ['files' => $files]));
    }
}
