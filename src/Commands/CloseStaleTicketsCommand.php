<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\AutoCloseWarning;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clôture les tickets où la main est au demandeur (en attente de sa réponse, ou à valider) et où rien ne s'est passé
 * depuis `auto_close.after_days` jours (28 par défaut) ; avertit `warn_days_before` jours avant. Seule l'application
 * qui traite le ticket le fait (le maître pour le centre de service, l'application source pour le local).
 * Planifiée chaque jour par le plugin.
 */
class CloseStaleTicketsCommand extends Command
{
    protected $signature = 'tma:close-stale {--dry-run : Afficher sans rien faire}';

    protected $description = 'Clôture les tickets restés sans réponse du demandeur, après avertissement';

    public function handle(TicketWorkflow $workflow): int
    {
        if (! config('filament-tma.auto_close.enabled', true)) {
            $this->components->info('Clôture automatique désactivée (filament-tma.auto_close.enabled).');

            return self::SUCCESS;
        }

        $afterDays = (int) config('filament-tma.auto_close.after_days', 28);
        $warnBefore = (int) config('filament-tma.auto_close.warn_days_before');
        $warns = $warnBefore > 0 && $warnBefore < $afterDays;
        $dry = (bool) $this->option('dry-run');
        $context = Context::scheduler();
        $closed = $warned = 0;

        // Avec avertissement, on ne clôture qu'un ticket averti depuis tout le délai prévu, même si l'avertissement
        // est parti en retard (planificateur arrêté…) : le demandeur a toujours ce temps pour réagir.
        $this->waitingForRequester()
            ->where('last_activity_at', '<=', now()->subDays($afterDays))
            ->when($warns, fn (Builder $query) => $query->where('auto_close_warned_at', '<=', now()->subDays($warnBefore)))
            ->each(function (Ticket $ticket) use ($workflow, $context, $dry, &$closed): void {
                if (! $workflow->allows($ticket, 'auto-close', $context)) {
                    return;
                }

                $this->line("Clôture : {$ticket->number} — {$ticket->title}");
                $closed++;

                if (! $dry) {
                    $workflow->run($ticket, 'auto-close', [], $context);
                }
            });

        if ($warns) {
            $this->waitingForRequester()
                ->whereNull('auto_close_warned_at')
                ->where('last_activity_at', '<=', now()->subDays($afterDays - $warnBefore))
                ->each(function (Ticket $ticket) use ($workflow, $context, $dry, $afterDays, $warnBefore, &$warned): void {
                    if (! $workflow->allows($ticket, 'auto-close', $context)) {
                        return;
                    }

                    $closesOn = max($ticket->last_activity_at->copy()->addDays($afterDays), now()->addDays($warnBefore))->startOfDay();
                    $this->line("Avertissement : {$ticket->number} — clôture le {$closesOn->format('d/m/Y')}");
                    $warned++;

                    if ($dry) {
                        return;
                    }

                    $ticket->forceFill(['auto_close_warned_at' => now()])->saveQuietly();

                    if ($ticket->requester?->isLocal()) {
                        $ticket->requester->user?->notify(new AutoCloseWarning($ticket, $closesOn));
                    }

                    event(new AutoCloseWarned($ticket, $closesOn));
                });
        }

        $this->components->info(($dry ? '[simulation] ' : '')."{$closed} clôturé(s), {$warned} averti(s).");

        return self::SUCCESS;
    }

    private function waitingForRequester(): Builder
    {
        return Ticket::query()->whereIn('state', ['awaiting_requester', 'pending_validation'])->with('requester');
    }
}
