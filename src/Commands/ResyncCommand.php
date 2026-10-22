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
 *     php artisan tma:resync --project=01k…           # les tâches publiées d'un projet (son ulid ou son titre)
 *     php artisan tma:resync --ticket=DES-0042 --task=T-0007
 *     php artisan tma:resync --names                  # les noms seulement
 *     php artisan tma:resync --all --dry-run          # ce qui partirait, sans rien envoyer
 *
 * La file doit tourner (`queue:work`) : les envois passent par elle, avec leurs tentatives.
 */
class ResyncCommand extends Command
{
    protected $signature = 'tma:resync
        {--all : Tous les tickets et toutes les tâches publiées, pour toutes les applications}
        {--instance= : Une application cliente (uid ou nom) : tout ce qui va à elle}
        {--project=* : Un projet (ulid ou titre) : ses tâches publiées}
        {--ticket=* : Un ticket (numéro ou ulid)}
        {--task=* : Une tâche (numéro ou ulid)}
        {--names : Les noms seulement, sans rien renvoyer}
        {--dry-run : Dire ce qui partirait, sans rien changer}';

    protected $description = 'Maître : rafraîchit les noms et renvoie tickets, tâches et projets à leurs applications clientes';

    public function handle(): int
    {
        if (! Tma::isMaster()) {
            $this->components->error(__('Cette commande se lance dans le maître : c’est lui qui renvoie aux applications.'));

            return self::FAILURE;
        }

        $instance = null;

        if (filled($name = $this->option('instance'))) {
            $instance = Instance::query()->where('uid', $name)->orWhere('name', $name)->first();

            if ($instance === null) {
                $this->components->error(__('Application inconnue : :name', ['name' => $name]));

                return self::FAILURE;
            }
        }

        $subjects = $this->option('names') ? collect() : $this->subjects($instance);

        if ($subjects === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info(__(':n ticket(s) ou tâche(s) partiraient.', ['n' => $subjects->filter->isShared()->count()]));
            $subjects->each(fn ($subject) => $this->line('  '.$subject->number.' — '.$subject->title));

            return self::SUCCESS;
        }

        $names = Resync::refreshNames($instance);
        $this->components->info(__(':n nom(s) mis à jour.', ['n' => $names]));

        if (! $this->option('names')) {
            $this->components->info(__(':n envoi(s) demandé(s), par la file.', ['n' => Resync::push($subjects)]));
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
                $this->components->error(__('Projet inconnu : :ref', ['ref' => $ref]));

                return null;
            }

            $chosen = $chosen->concat(Resync::subjects(project: $project));
        }

        foreach (['ticket' => Ticket::class, 'task' => ProjectTask::class] as $option => $model) {
            foreach ((array) $this->option($option) as $ref) {
                $subject = $model::query()->where('number', $ref)->orWhere('ulid', strtolower($ref))->first();

                if ($subject === null) {
                    $this->components->error(__('Introuvable : :ref', ['ref' => $ref]));

                    return null;
                }

                $chosen->push($subject);
            }
        }

        if ($chosen->isNotEmpty()) {
            return $chosen;
        }

        if ($instance === null && ! $this->option('all')) {
            $this->components->error(__('Dites quoi renvoyer : --all, --instance, --project, --ticket, --task (ou --names).'));

            return null;
        }

        return Resync::subjects($instance);
    }
}
