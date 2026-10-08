<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Support\Digest;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Console\Command;

/**
 * Le récapitulatif quotidien (CDC §12.1) : à chaque utilisateur, ce qui l'attend. Personne ne reçoit un courriel
 * vide. Planifié à `notifications.digest.at`, les jours `notifications.digest.days`.
 */
class SendDigestCommand extends Command
{
    protected $signature = 'tma:digest {--dry-run : Afficher sans envoyer}';

    protected $description = 'Envoie le récapitulatif quotidien de ce qui attend chacun';

    public function handle(): int
    {
        if (! config('filament-tma.notifications.digest.enabled', true)) {
            $this->components->info('Récapitulatif désactivé (filament-tma.notifications.digest.enabled).');

            return self::SUCCESS;
        }

        $sent = 0;
        $model = Tma::userModel();

        $model::query()->each(function ($user) use (&$sent): void {
            $sections = Digest::for($user);

            if ($sections === []) {
                return;
            }

            $count = collect($sections)->sum(fn ($items) => $items->count());
            $this->line(($user->email ?? $user->getAuthIdentifier()).' : '.$count);
            $sent++;

            if (! $this->option('dry-run')) {
                $user->notify(new DailyDigest($sections));
            }
        });

        $this->components->info(($this->option('dry-run') ? '[simulation] ' : '')."{$sent} récapitulatif(s).");

        return self::SUCCESS;
    }
}
