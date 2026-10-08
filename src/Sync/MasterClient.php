<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Http\Client\PendingRequest;

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
            throw new SyncException(__('Le centre de service n’est pas configuré (TMA_MASTER_URL, TMA_MASTER_TOKEN).'));
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
        return Http::call(__('Test de connexion au centre de service'), fn () => $this->http()->get($this->url('master/ping')))->json();
    }

    /**
     * Transmet un ticket au centre de service, avec ses entrées partagées et leurs fichiers. Le maître le crée (ou
     * le retrouve : la transmission est idempotente) et renvoie son état.
     *
     * @return array<string, mixed> l'état du ticket chez le maître (`Payload::snapshot()`)
     */
    public function transmit(Ticket $ticket, StateTransition $transition, Context $context): array
    {
        $entries = $ticket->entries()->with(['author', 'parent', 'media'])->oldest('id')->get()
            ->filter(fn (Entry $entry): bool => $entry->isShared())
            ->map(fn (Entry $entry): array => Payload::entry($entry, withFiles: true))
            ->values()->all();

        return Http::call(__('Transmission au centre de service'), fn () => $this->http()->post($this->url('tickets'), [
            'ticket' => Payload::ticket($ticket),
            'entries' => $entries,
            'transition' => Payload::transition($transition),
            'files' => Payload::files($ticket),
        ]))->json();
    }

    /**
     * Fait un mouvement du demandeur sur un ticket du centre de service : c'est le maître qui l'exécute.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> l'état du ticket chez le maître
     */
    public function runMove(Ticket $ticket, string $move, array $data, Context $context, string $transitionUlid): array
    {
        return Http::call(__('Centre de service'), fn () => $this->http()->post($this->url("tickets/{$ticket->ulid}/moves"), [
            'move' => $move,
            'data' => $data,
            'actor' => Payload::actor($context->actor),
            'transition_ulid' => $transitionUlid,
        ]))->json();
    }

    /** Envoie une entrée (commentaire, note partagée, réponse) avec ses fichiers. */
    public function sendEntry(Entry $entry): void
    {
        Http::call(__('Envoi au centre de service'), fn () => $this->http()->post(
            $this->url("tickets/{$entry->subject->ulid}/entries"),
            Payload::entry($entry, withFiles: true),
        ));
    }
}
