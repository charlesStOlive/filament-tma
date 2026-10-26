<?php

namespace CharlesStOlive\FilamentTma\Jobs;

use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Sync\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Le maître envoie à une application cliente l'état de ses forfaits pour la période en cours (CDC §21.1 : le client
 * consulte la consommation de son forfait). Par la file, un envoi en attente par application.
 */
class PushAllowancesToInstance implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(public Instance $instance)
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
        return $this->instance->uid;
    }

    public function handle(): void
    {
        if (blank($this->instance->token)) {
            return;
        }

        $statuses = Allowance::query()->get()
            ->filter(fn (Allowance $allowance): bool => $allowance->isActiveOn(now()) && $allowance->covers($this->instance->uid))
            ->map(fn (Allowance $allowance): array => AllowanceMeter::status($allowance))
            ->values()->all();

        Http::call(__('filament-tma::sync.operations.push_allowances'), fn () => Http::to($this->instance->token)->post($this->instance->apiUrl('allowances'), ['data' => $statuses]));
    }

    /** Toutes les applications concernées par un forfait, ou par une saisie. */
    public static function dispatchFor(Allowance $allowance): void
    {
        Instance::query()->active()->whereIn('uid', $allowance->instanceUids())->each(function (Instance $instance): void {
            rescue(function () use ($instance): void {
                static::dispatch($instance);
            });
        });
    }
}
