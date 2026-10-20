<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Support\DigestSender;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Les bilans (CDC §12.1). Sans option, celui du matin : à chaque utilisateur, ce qui l'attend sur ses tickets, et ce
 * qui a été mis de côté pour lui depuis le dernier envoi ; planifié à `notifications.digest.at`, les jours
 * `notifications.digest.days`. `--weekly` : le bilan de la semaine des tâches projet, à qui a du nouveau ; planifié le
 * jour `notifications.weekly.day` à `notifications.weekly.at`. Personne ne reçoit un courriel vide.
 */
class SendDigestCommand extends Command
{
    protected $signature = 'tma:digest
        {--weekly : Le bilan de la semaine des tâches projet}
        {--pending : Seulement qui a des notifications en attente}
        {--dry-run : Afficher sans envoyer}';

    protected $description = 'Envoie le bilan du matin de ce qui attend chacun, ou celui de la semaine des tâches projet';

    public function handle(): int
    {
        $weekly = (bool) $this->option('weekly');

        if (! config('filament-tma.notifications.'.($weekly ? 'weekly' : 'digest').'.enabled', true)) {
            $this->components->info($weekly
                ? 'Bilan de la semaine désactivé (filament-tma.notifications.weekly.enabled).'
                : 'Bilan du matin désactivé (filament-tma.notifications.digest.enabled).');

            return self::SUCCESS;
        }

        $result = DigestSender::send(
            weekly: $weekly,
            pendingOnly: (bool) $this->option('pending'),
            dryRun: (bool) $this->option('dry-run'),
            report: fn (Authenticatable $user, int $waiting, int $news) => $this->line(($user->email ?? $user->getAuthIdentifier())." : {$waiting} en attente, {$news} nouveauté(s)"),
        );

        $this->components->info(($this->option('dry-run') ? '[simulation] ' : '')."{$result['recipients']} récapitulatif(s), {$result['notices']} nouveauté(s).");

        return self::SUCCESS;
    }
}
