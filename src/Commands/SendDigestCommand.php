<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Support\DigestSender;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Le récapitulatif quotidien (CDC §12.1) : à chaque utilisateur, ce qui l'attend, et ce qui a été mis de côté pour
 * lui depuis le dernier envoi. Personne ne reçoit un courriel vide. Planifié à `notifications.digest.at`, les jours
 * `notifications.digest.days`.
 */
class SendDigestCommand extends Command
{
    protected $signature = 'tma:digest
        {--pending : Seulement qui a des notifications en attente}
        {--dry-run : Afficher sans envoyer}';

    protected $description = 'Envoie le récapitulatif quotidien de ce qui attend chacun';

    public function handle(): int
    {
        if (! config('filament-tma.notifications.digest.enabled', true)) {
            $this->components->info('Récapitulatif désactivé (filament-tma.notifications.digest.enabled).');

            return self::SUCCESS;
        }

        $result = DigestSender::send(
            pendingOnly: (bool) $this->option('pending'),
            dryRun: (bool) $this->option('dry-run'),
            report: fn (Authenticatable $user, int $waiting, int $news) => $this->line(($user->email ?? $user->getAuthIdentifier())." : {$waiting} en attente, {$news} nouveauté(s)"),
        );

        $this->components->info(($this->option('dry-run') ? '[simulation] ' : '')."{$result['recipients']} récapitulatif(s), {$result['notices']} nouveauté(s).");

        return self::SUCCESS;
    }
}
