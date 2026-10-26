<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Support\DigestSender;
use CharlesStOlive\FilamentTma\Support\StaleReminders;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Les notifications à la demande, pour le terminal, les IA et le planificateur :
 * - `--stale` : rappelle à qui a la main ce qui ne bouge plus depuis `notifications.stale.after_days` jours
 *   (planifié chaque matin, avant le récapitulatif) ;
 * - `--grouped` : envoie tout de suite les récapitulatifs de qui a des notifications en attente (le bouton
 *   « Envoyer maintenant » de la liste des tickets).
 *
 * Les deux ensemble : les rappels d'abord, pour qu'ils partent dans le même courriel.
 */
class NotifyCommand extends Command
{
    protected $signature = 'tma:notify
        {--stale : Rappeler ce qui ne bouge plus}
        {--grouped : Envoyer maintenant les notifications en attente}
        {--dry-run : Afficher sans rien écrire ni envoyer}';

    protected $description = 'Rappels de ce qui ne bouge plus, envoi des notifications groupées en attente';

    public function handle(): int
    {
        if (! $this->option('stale') && ! $this->option('grouped')) {
            $this->components->error(__('filament-tma::commands.notify.what'));

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[simulation] ' : '';

        if ($this->option('stale')) {
            if (! config('filament-tma.notifications.stale.enabled', true)) {
                $this->components->info(__('filament-tma::commands.notify.stale_disabled'));
            } else {
                $result = StaleReminders::run($dryRun, fn (Trackable $subject, int $days, int $sent) => $this->line("{$subject->number} — {$subject->title} : {$days} j, {$sent} personne(s)"));
                $this->components->info($prefix.__('filament-tma::commands.notify.stale_done', ['n' => $result['reminders'], 's' => $result['subjects']]));
            }
        }

        if ($this->option('grouped')) {
            $result = DigestSender::send(
                pendingOnly: true,
                dryRun: $dryRun,
                report: fn (Authenticatable $user, int $waiting, int $news) => $this->line(($user->email ?? $user->getAuthIdentifier())." : {$news} nouveauté(s)"),
            );
            $this->components->info($prefix.__('filament-tma::commands.notify.grouped_done', ['r' => $result['recipients'], 'n' => $result['notices']]));
        }

        return self::SUCCESS;
    }
}
