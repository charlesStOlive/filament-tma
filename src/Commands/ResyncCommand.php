<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Resync;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Maître : remet d'accord le maître et ses applications clientes (`Support\Resync`). D'abord les noms (ceux des
 * utilisateurs d'ici, ceux des correspondants des applications), puis le renvoi, par la file, de ce qu'on désigne :
 *
 *     php artisan tma:resync --all                    # tout, pour toutes les applications
 *     php artisan tma:resync --instance=01K…          # tout ce qui va à une application (son uid ou son nom)
 *     php artisan tma:resync --project=01k…           # un projet et ses tâches publiées (son ulid ou son titre)
 *     php artisan tma:resync --ticket=DES-0042 --task=T-0007
 *     php artisan tma:resync --names                  # les noms seulement
 *     php artisan tma:resync --all --dry-run          # ce qui partirait, sans rien envoyer
 *
 * La file doit tourner (`queue:work`) : les envois passent par elle, avec leurs tentatives.
 */
class ResyncCommand extends Command
{
    protected $signature = 'tma:resync
        {--all : Tous les projets, tickets et tâches publiées, pour toutes les applications}
        {--instance= : Une application cliente (uid ou nom) : tout ce qui va à elle}
        {--project=* : Un projet (ulid ou titre), et ses tâches publiées}
        {--ticket=* : Un ticket (numéro ou ulid)}
        {--task=* : Une tâche (numéro ou ulid)}
        {--names : Les noms seulement, sans rien renvoyer}
        {--dry-run : Dire ce qui partirait, sans rien changer}';

    protected $description = 'Maître : rafraîchit les noms et renvoie tickets, tâches et projets à leurs applications clientes';

    /** @var Collection<int, Project> les projets à renvoyer (`subjects()` les choisit avec le reste) */
    private Collection $projects;

    public function handle(): int
    {
        if (! Tma::isMaster()) {
            $this->components->error(__('filament-tma::commands.resync.master_only'));

            return self::FAILURE;
        }

        $instance = null;

        if (filled($name = $this->option('instance'))) {
            $instance = Instance::query()->where('uid', $name)->orWhere('name', $name)->first();

            if ($instance === null) {
                $this->components->error(__('filament-tma::commands.resync.unknown_instance', ['name' => $name]));

                return self::FAILURE;
            }
        }

        $this->projects = collect();
        $subjects = $this->option('names') ? collect() : $this->subjects($instance);

        if ($subjects === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info(__('filament-tma::commands.resync.dry_run', ['projects' => $this->projects->count(), 'n' => $subjects->filter->isShared()->count()]));
            $this->projects->each(fn (Project $project) => $this->line('  '.__('filament-tma::common.project').' — '.$project->title));
            $subjects->each(fn ($subject) => $this->line('  '.$subject->number.' — '.$subject->title));

            return self::SUCCESS;
        }

        $names = Resync::refreshNames($instance);
        $this->components->info(__('filament-tma::commands.resync.names', ['n' => $names]));

        if (! $this->option('names')) {
            $this->components->info(__('filament-tma::commands.resync.queued', ['n' => Resync::pushProjects($this->projects) + Resync::push($subjects)]));
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Trackable>|null */
    private function subjects(?Instance $instance): ?Collection
    {
        $chosen = collect();

        foreach ((array) $this->option('project') as $ref) {
            $project = Project::query()->where('ulid', strtolower($ref))->orWhere('title', $ref)->first();

            if ($project === null) {
                $this->components->error(__('filament-tma::commands.resync.unknown_project', ['ref' => $ref]));

                return null;
            }

            $this->projects->push($project);
            $chosen = $chosen->concat(Resync::subjects(project: $project));
        }

        foreach (['ticket' => Ticket::class, 'task' => ProjectTask::class] as $option => $model) {
            foreach ((array) $this->option($option) as $ref) {
                $subject = $model::query()->where('number', $ref)->orWhere('ulid', strtolower($ref))->first();

                if ($subject === null) {
                    $this->components->error(__('filament-tma::commands.resync.not_found', ['ref' => $ref]));

                    return null;
                }

                $chosen->push($subject);
            }
        }

        if ($chosen->isNotEmpty() || $this->projects->isNotEmpty()) {
            return $chosen;
        }

        if ($instance === null && ! $this->option('all')) {
            $this->components->error(__('filament-tma::commands.resync.what'));

            return null;
        }

        $this->projects = Resync::projects($instance);

        return Resync::subjects($instance);
    }
}
