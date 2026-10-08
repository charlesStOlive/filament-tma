<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Events\CleanupFinished;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Auditor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Le nettoyage (CDC §18). Supprime définitivement, après la période de rétention :
 *
 * - les tickets et tâches supprimés (avec leur chronologie, leur historique, leur temps et leurs fichiers) ;
 * - les entrées de chronologie supprimées (et leurs fichiers) ;
 * - les fichiers dont l'objet n'existe plus (orphelins) ;
 * - les fichiers envoyés par un formulaire mais jamais rattachés (`tma-uploads`, après quelques heures).
 *
 * Rien de ce qui touche une saisie de temps rattachée à une facture n'est supprimé. `--dry-run` compte sans
 * supprimer. Chaque passage est journalisé et audité.
 */
class CleanupCommand extends Command
{
    protected $signature = 'tma:cleanup
        {--dry-run : Compter sans rien supprimer}
        {--days= : Rétention en jours (par défaut cleanup.retention_days)}';

    protected $description = 'Supprime définitivement ce qui a été supprimé et les fichiers devenus inutiles';

    /** @var array<string, int> */
    private array $report = [];

    public function handle(): int
    {
        // La même instance peut servir deux fois dans un processus (Artisan::call) : on repart de zéro.
        $this->report = ['tickets' => 0, 'tasks' => 0, 'entries' => 0, 'orphan_files' => 0, 'pending_uploads' => 0, 'protected' => 0];
        $dry = (bool) $this->option('dry-run');
        $before = now()->subDays((int) ($this->option('days') ?? config('filament-tma.cleanup.retention_days', 90)));

        foreach ([Ticket::class => 'tickets', ProjectTask::class => 'tasks'] as $model => $key) {
            $model::onlyTrashed()->where('deleted_at', '<', $before)->each(function (Model $subject) use ($dry, $key): void {
                if ($subject->timeEntries()->withTrashed()->whereNotNull('invoice_id')->exists()) {
                    $this->report['protected']++;

                    return;
                }

                $this->report[$key]++;

                if (! $dry) {
                    $this->destroy($subject);
                }
            });
        }

        Entry::onlyTrashed()->where('deleted_at', '<', $before)->each(function (Entry $entry) use ($dry): void {
            $this->report['entries']++;

            if (! $dry) {
                $entry->forceDelete();
            }
        });

        Media::query()->whereIn('model_type', [(new Ticket)->getMorphClass(), (new ProjectTask)->getMorphClass(), (new Entry)->getMorphClass()])
            ->each(function (Media $media) use ($dry): void {
                $class = Model::getActualClassNameForMorph($media->model_type);

                if ($class::withTrashed()->whereKey($media->model_id)->exists()) {
                    return;
                }

                $this->report['orphan_files']++;

                if (! $dry) {
                    $media->delete();
                }
            });

        $this->cleanUploads($dry);

        $this->table([__('Catégorie'), __('Nombre')], collect($this->report)->map(fn (int $n, string $k): array => [$k, $n])->values()->all());
        $this->components->info($dry ? __('Simulation : rien n’a été supprimé.') : __('Nettoyage terminé.'));

        Log::info('filament-tma : nettoyage'.($dry ? ' (simulation)' : ''), $this->report);

        if (! $dry) {
            Auditor::record('cleanup.done', null, null, $this->report);
        }

        event(new CleanupFinished($this->report, $dry));

        return self::SUCCESS;
    }

    /** Un ticket ou une tâche, et tout ce qui en dépend. */
    private function destroy(Ticket|ProjectTask $subject): void
    {
        $subject->entries()->withTrashed()->get()->each->forceDelete();
        $subject->transitions()->delete();
        $subject->timeEntries()->withTrashed()->get()->each->forceDelete();
        $subject->forceDelete();
    }

    private function cleanUploads(bool $dry): void
    {
        $disk = Storage::disk(config('filament-tma.attachments.disk', 'local'));
        $limit = now()->subHours((int) config('filament-tma.cleanup.uploads_retention_hours', 24))->getTimestamp();

        foreach ($disk->files('tma-uploads') as $path) {
            if ($disk->lastModified($path) >= $limit) {
                continue;
            }

            $this->report['pending_uploads']++;

            if (! $dry) {
                $disk->delete($path);
            }
        }
    }
}
