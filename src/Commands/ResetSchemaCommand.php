<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remet le schéma du module à plat (addendum §3 bis), dans une installation qui a passé les anciennes migrations :
 * supprime les tables `tma_*` et leurs lignes dans `migrations`, passe les migrations du module (publiées dans
 * l'application), puis remet les liaisons entre applications — applications clientes, membres du Support reliés,
 * forfaits —, sauf avec `--all`. Tickets, tâches, messages, notes, temps et leurs fichiers sont perdus.
 *
 * Avant : retirer de l'application les anciennes migrations du module et publier les nouvelles
 * (`vendor:publish --tag=filament-tma-migrations`). La commande le vérifie avant de toucher à quoi que ce soit.
 */
class ResetSchemaCommand extends Command
{
    protected $signature = 'tma:reset-schema
        {--all : Ne rien garder, pas même les liaisons entre applications et les forfaits}
        {--force : Sans confirmation (et en production)}';

    protected $description = 'Supprime les tables du module et les recrée avec ses migrations (tickets, tâches, messages et temps sont perdus).';

    /** Les migrations du module, dans l'ordre (`FilamentTmaServiceProvider`). */
    public const MIGRATIONS = [
        'create_tma_actors_and_instances_tables',
        'create_tma_projects_and_tasks_tables',
        'create_tma_tickets_tables',
        'create_tma_time_and_allowances_tables',
        'create_tma_notices_and_audits_tables',
    ];

    /** Les migrations d'avant la remise à plat du 16/10/2026. */
    public const LEGACY_MIGRATIONS = [
        'create_filament_tma_tables',
        'create_filament_tma_instances_table',
        'create_filament_tma_time_entries_table',
        'create_filament_tma_tasks_table',
        'create_filament_tma_allowances_table',
        'add_publish_at_to_tma_tickets_table',
        'create_filament_tma_audits_table',
        'create_filament_tma_agents_tables',
        'add_short_name_to_tma_instances_table',
        'convert_tma_summary_entries_to_messages',
        'drop_due_on_from_tma_tickets_table',
        'rename_ends_on_to_due_on_in_tma_tasks_table',
        'rework_tma_notes',
        'add_private_to_tma_entries_table',
        'create_tma_projects_table',
        'drop_description_from_tma_time_entries_table',
        'create_tma_notices_table',
        'create_tma_recipients_table',
    ];

    /** Les liaisons entre applications, gardées par défaut (dans cet ordre, pour leurs clés étrangères). */
    private const KEPT = ['tma_instances', 'tma_agent_links', 'tma_agents', 'tma_allowances'];

    public function handle(): int
    {
        $files = collect(File::glob(database_path('migrations/*.php')))->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME));
        $named = fn (string $name): bool => $files->contains(fn (string $file): bool => Str::endsWith($file, '_'.$name));

        if (($missing = array_values(array_filter(self::MIGRATIONS, fn (string $name): bool => ! $named($name)))) !== []) {
            $this->components->error(__('Les migrations du module ne sont pas publiées (:names) : php artisan vendor:publish --tag=filament-tma-migrations', ['names' => implode(', ', $missing)]));

            return self::FAILURE;
        }

        if (($legacy = array_values(array_filter(self::LEGACY_MIGRATIONS, $named))) !== []) {
            $this->components->error(__('Retirez d’abord de database/migrations les anciennes migrations du module : :names', ['names' => implode(', ', $legacy)]));

            return self::FAILURE;
        }

        $keep = ! $this->option('all');
        $question = $keep
            ? __('Supprimer les tickets, tâches, messages, notes et temps du module et recréer ses tables (les applications reliées, les membres du Support et les forfaits restent) ?')
            : __('Supprimer TOUTES les données du module, liaisons entre applications comprises, et recréer ses tables ?');

        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error(__('En production, il faut --force.'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm($question)) {
            return self::FAILURE;
        }

        $kept = $keep ? $this->keptRows() : [];
        $this->forgetFiles();
        $this->dropTables();

        $names = [...self::MIGRATIONS, ...self::LEGACY_MIGRATIONS];
        $removed = DB::table(config('database.migrations.table', 'migrations'))->get(['id', 'migration'])
            ->filter(fn (object $row): bool => collect($names)->contains(fn (string $name): bool => Str::endsWith($row->migration, '_'.$name)))
            ->pluck('id');
        DB::table(config('database.migrations.table', 'migrations'))->whereIn('id', $removed)->delete();

        $this->call('migrate', ['--force' => true]);
        $this->restore($kept);

        $this->components->info(__('Schéma du module recréé.').($keep ? ' '.__('Gardés : :counts.', ['counts' => collect($kept)->map(fn (array $rows, string $table): string => $table.' '.count($rows))->implode(', ')]) : ''));

        return self::SUCCESS;
    }

    /** @return array<string, array<int, array<string, mixed>>> les lignes gardées, par table */
    private function keptRows(): array
    {
        return collect(self::KEPT)
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all()])
            ->all();
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $kept */
    private function restore(array $kept): void
    {
        foreach ($kept as $table => $rows) {
            $columns = Schema::getColumnListing($table);

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert(array_map(fn (array $row): array => array_intersect_key($row, array_flip($columns)), $chunk));
            }
        }
    }

    /** Les fichiers des tickets, tâches, messages et notes (la médiathèque les retire du disque) et les notifications. */
    private function forgetFiles(): void
    {
        $morphs = array_map(fn (string $class): string => (new $class)->getMorphClass(),
            [Ticket::class, ProjectTask::class, Entry::class, Project::class, TimeEntry::class]);
        $media = config('media-library.media_model', Media::class);

        if (Schema::hasTable((new $media)->getTable())) {
            $media::query()->whereIn('model_type', $morphs)->each(fn (Media $file) => $file->delete());
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->where('type', 'like', 'CharlesStOlive\\\\FilamentTma\\\\%')->delete();
        }
    }

    private function dropTables(): void
    {
        $tables = collect(Schema::getTables())->pluck('name')->filter(fn (string $name): bool => Str::startsWith($name, 'tma_'));

        Schema::withoutForeignKeyConstraints(function () use ($tables): void {
            $tables->each(fn (string $table) => Schema::dropIfExists($table));
        });
    }
}
