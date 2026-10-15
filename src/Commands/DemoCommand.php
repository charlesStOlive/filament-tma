<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Support\Demo\ClientDemo;
use CharlesStOlive\FilamentTma\Support\Demo\MasterDemo;
use CharlesStOlive\FilamentTma\Support\Demo\Wipe;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Console\Command;
use Throwable;

/**
 * Le jeu d'exemples du module, joué par le vrai workflow (les deux applications se synchronisent comme en vrai).
 * Dans l'ordre, pour repartir de zéro :
 *
 *     client : php artisan tma:demo --wipe --force     (vide l'application cliente)
 *     maître : php artisan tma:demo --fresh --force    (vide le maître ; projets, tâches, tickets préparés)
 *     client : php artisan tma:demo --force            (tickets de l'équipe, demandes au Support, échanges sur les tâches)
 *
 * Jamais en production sans `--force`.
 */
class DemoCommand extends Command
{
    protected $signature = 'tma:demo
        {--wipe : Vider seulement les données du module ici}
        {--fresh : Vider, puis créer les exemples}
        {--force : Sans confirmation (et en production)}';

    protected $description = 'Jeu d’exemples du module : vide les tickets, tâches et projets de cette application et crée des cas dans tous les états';

    public function handle(): int
    {
        $wipe = $this->option('wipe') || $this->option('fresh');

        if ($wipe && ! $this->option('force') && ! $this->confirm(__('Vider tous les tickets, tâches, projets, messages, notes et temps de cette application ?'))) {
            return self::FAILURE;
        }

        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error(__('En production, il faut --force.'));

            return self::FAILURE;
        }

        if ($wipe) {
            $removed = Wipe::run();
            $this->components->info(__('Vidé : :what.', ['what' => collect($removed)->map(fn (int $n, string $what): string => "{$n} {$what}")->implode(', ')]));
        }

        if ($this->option('wipe')) {
            return self::SUCCESS;
        }

        $this->components->info(Tma::isMaster() ? __('Exemples du Support :') : __('Exemples de l’application cliente :'));

        try {
            (Tma::isMaster() ? new MasterDemo($this) : new ClientDemo($this))->run();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
