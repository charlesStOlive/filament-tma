<?php

namespace CharlesStOlive\FilamentTma\Jobs;

use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Sync\InstanceClient;
use CharlesStOlive\FilamentTma\Sync\Payload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Le maître envoie un projet à son application cliente dès qu'il est créé, modifié ou supprimé, qu'il ait ou non des
 * tâches publiées (`Project::pushToClient()`). Par la file, comme les tickets et les tâches (`PushToInstance`) : il part
 * avec l'état du moment.
 *
 * Un projet qui change d'application (possible tant qu'il n'a pas de tâche) est retiré de l'ancienne : `$from` est
 * alors son uid, et le projet y part supprimé.
 */
class PushProjectToInstance implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** Effacé pour de bon avant l'envoi : il n'y a plus rien à dire. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Project $project, public ?string $from = null)
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
        return 'project:'.$this->project->getKey().':'.($this->from ?? 'target');
    }

    public function handle(): void
    {
        $project = $this->project->refresh();
        $instance = Instance::query()->active()->where('uid', $this->from ?? $project->target_instance_uid)->first();

        if ($instance === null) {
            return;
        }

        $payload = Payload::project($project);

        if ($this->from !== null) {
            $payload = [...$payload, 'target_instance_uid' => $this->from, 'deleted_at' => now()->utc()->toIso8601String()];
        }

        (new InstanceClient($instance))->pushProject($payload);
        $instance->forceFill(['last_pushed_at' => now()])->saveQuietly();
    }
}
