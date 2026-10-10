<?php

namespace CharlesStOlive\FilamentTma\Jobs;

use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Events\TicketSyncFailed;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Sync\InstanceClient;
use CharlesStOlive\FilamentTma\Sync\Payload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Le maître envoie l'état d'un ticket du Support, ou d'une tâche publiée, à son application cliente, puis
 * les fichiers qui lui manquent. Par la file, avec des tentatives espacées (`api.sync`) : une application indisponible ne bloque pas le
 * travail du Support. Un seul envoi en attente par objet : il part avec l'état du moment.
 */
class PushToInstance implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(public Ticket|ProjectTask $ticket)
    {
        $this->tries = (int) config('filament-tma.api.sync.tries', 6);
        $this->onQueue(config('filament-tma.api.sync.queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return config('filament-tma.api.sync.backoff', [30, 120, 600]);
    }

    public function uniqueId(): string
    {
        return $this->ticket->kind().':'.$this->ticket->getKey();
    }

    public function handle(): void
    {
        $instance = Instance::query()->active()->where('uid', $this->ticket->clientInstanceUid())->firstOrFail();
        $client = new InstanceClient($instance);
        $ticket = $this->ticket->refresh();

        $missing = $client->pushTicket(Payload::snapshot($ticket));

        foreach ($missing as $ulid) {
            $entry = $ticket->entries()->where('ulid', $ulid)->first();

            if ($entry instanceof Entry && $entry->isShared()) {
                $client->pushMedia('entry', $ulid, Payload::files($entry));
            }
        }

        $ticket->forceFill(['sync_status' => SyncStatus::Synced, 'synced_at' => now(), 'last_sync_error' => null])->saveQuietly();
        $instance->forceFill(['last_pushed_at' => now()])->saveQuietly();
    }

    public function failed(?\Throwable $e): void
    {
        $message = $e?->getMessage() ?? __('Erreur inconnue');

        $this->ticket->forceFill(['sync_status' => SyncStatus::Error, 'last_sync_error' => $message])->saveQuietly();

        event(new TicketSyncFailed($this->ticket, $message));
    }
}
