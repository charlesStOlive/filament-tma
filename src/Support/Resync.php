<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Jobs\PushToInstance;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use Illuminate\Support\Collection;

/**
 * Remettre d'accord le maître et ses applications clientes, à la demande d'un admin du Support (`tma:resync`, « Tout
 * renvoyer » d'une application, « Renvoyer à l'application » d'un projet, d'un ticket, d'une tâche).
 *
 * - Les noms : une personne est une copie (`Actor`), rafraîchie quand elle agit. `refreshNames()` reprend tout de
 *   suite les noms et e-mails des utilisateurs d'ici, et, dans le maître, ceux des correspondants de chaque
 *   application (`GET users`, sans le cache).
 * - Les tickets et les tâches publiées : `push()` les renvoie (`PushToInstance`, par la file), avec leurs projets,
 *   leurs entrées, leurs fichiers et les noms à jour.
 * - Les projets, même sans tâche publiée : `pushProjects()` (`PushProjectToInstance`, par la file).
 */
final class Resync
{
    /**
     * Les noms et e-mails à jour : ceux des utilisateurs d'ici, et, dans le maître, ceux des correspondants des
     * applications clientes (de `$instance` seule si on la donne).
     *
     * @return int le nombre de personnes dont la copie a changé
     */
    public static function refreshNames(?Instance $instance = null): int
    {
        $changed = 0;

        Actor::query()->where('instance_uid', Tma::instanceUid())->whereNotNull('user_id')->with('user')->get()
            ->each(function (Actor $actor) use (&$changed): void {
                if ($actor->user !== null && Actor::forUser($actor->user)->wasChanged()) {
                    $changed++;
                }
            });

        if (! Tma::isMaster()) {
            return $changed;
        }

        foreach (self::instances($instance) as $client) {
            RemoteUsers::forget($client);

            foreach (rescue(fn (): array => RemoteUsers::for($client), [], false) as $user) {
                $actor = Actor::query()->where('instance_uid', $client->uid)->where('remote_id', (string) $user['id'])->first();

                if ($actor !== null && $actor->fill(['name' => (string) $user['name'], 'email' => $user['email'] ?? $actor->email])->isDirty()) {
                    $actor->save();
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * Ce que le maître envoie à une application (ou à toutes) : ses tickets du Support et ses tâches publiées ; ceux
     * d'un projet seulement avec `$project`.
     *
     * @return Collection<int, Trackable>
     */
    public static function subjects(?Instance $instance = null, ?Project $project = null): Collection
    {
        $tasks = ProjectTask::query()
            ->where('state', '!=', 'draft')
            ->when($instance, fn ($query) => $query->where('target_instance_uid', $instance->uid))
            ->when($project, fn ($query) => $query->where('project_id', $project->getKey()))
            ->get();

        if ($project !== null) {
            return $tasks;
        }

        $tickets = Ticket::query()
            ->where('state', '!=', 'draft')
            ->when($instance, fn ($query) => $query->where('source_instance_uid', $instance->uid))
            ->get();

        return $tickets->toBase()->concat($tasks);
    }

    /**
     * Renvoie des tickets et des tâches à leur application (dans le maître seulement, ce qui est publié).
     *
     * @param  iterable<Trackable>  $subjects
     * @return int le nombre d'envois demandés
     */
    public static function push(iterable $subjects): int
    {
        if (! Tma::isMaster()) {
            return 0;
        }

        $count = 0;

        foreach ($subjects as $subject) {
            if (! $subject->isShared()) {
                continue;
            }

            $subject->forceFill(['sync_status' => SyncStatus::Pending])->saveQuietly();
            rescue(fn () => PushToInstance::dispatch($subject));
            $count++;
        }

        return $count;
    }

    /**
     * Les projets que le maître envoie à une application (ou à toutes).
     *
     * @return Collection<int, Project>
     */
    public static function projects(?Instance $instance = null): Collection
    {
        return Project::query()
            ->when($instance, fn ($query) => $query->where('target_instance_uid', $instance->uid))
            ->get();
    }

    /**
     * Renvoie des projets à leur application (dans le maître seulement).
     *
     * @param  iterable<Project>  $projects
     * @return int le nombre d'envois demandés
     */
    public static function pushProjects(iterable $projects): int
    {
        if (! Tma::isMaster()) {
            return 0;
        }

        $count = 0;

        foreach ($projects as $project) {
            $project->pushToClient();
            $count++;
        }

        return $count;
    }

    /** @return Collection<int, Instance> */
    private static function instances(?Instance $instance): Collection
    {
        return $instance !== null ? collect([$instance]) : Instance::query()->active()->get();
    }
}
