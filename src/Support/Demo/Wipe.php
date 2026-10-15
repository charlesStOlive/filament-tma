<?php

namespace CharlesStOlive\FilamentTma\Support\Demo;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Vide les données de travail du module dans cette application : tickets, tâches, projets, messages, notes, passages,
 * temps, leurs fichiers, et le journal qui les concerne. Restent les applications reliées, les membres du Support, les
 * forfaits et les utilisateurs. Sans événement : rien ne part vers l'autre application.
 */
final class Wipe
{
    /** @return array<string, int> ce qui a été retiré */
    public static function run(): array
    {
        $morphs = array_map(fn (string $class): string => (new $class)->getMorphClass(),
            [Ticket::class, ProjectTask::class, Entry::class, Project::class, TimeEntry::class]);
        $counts = [
            'tickets' => DB::table('tma_tickets')->count(),
            'tâches' => DB::table('tma_tasks')->count(),
            'projets' => DB::table('tma_projects')->count(),
            'messages et notes' => DB::table('tma_entries')->count(),
            'saisies de temps' => DB::table('tma_time_entries')->count(),
        ];

        // Les fichiers d'abord : la médiathèque les retire du disque.
        $media = config('media-library.media_model', Media::class);
        $media::query()->whereIn('model_type', $morphs)->each(fn (Media $file) => $file->delete());

        DB::transaction(function () use ($morphs): void {
            DB::table('tma_time_entries')->delete();
            DB::table('tma_allowance_alerts')->delete();
            DB::table('tma_allowance_statuses')->delete();
            DB::table('tma_state_transitions')->delete();
            DB::table('tma_entries')->whereNotNull('parent_id')->delete();
            DB::table('tma_entries')->delete();
            DB::table('tma_tickets')->delete();
            DB::table('tma_tasks')->delete();
            DB::table('tma_projects')->delete();
            DB::table('tma_audits')->whereIn('subject_type', $morphs)->delete();
        });

        return $counts;
    }
}
