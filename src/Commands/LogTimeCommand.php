<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Console\Command;

/**
 * `tma:log DES-0042 --hours=2 --summary="…"` : saisir du temps (dans le maître) et, à part, un résumé
 * d'intervention dans la chronologie (CDC §16). `--summary-file` lit un résumé long dans un fichier (`-` : l'entrée
 * standard).
 */
class LogTimeCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Saisit du temps sur un ticket, avec un résumé d’intervention';

    public function __construct()
    {
        $this->signature = 'tma:log
            {ref : Numéro (DES-0042) ou ulid du ticket}
            {--minutes= : Durée en minutes}
            {--hours= : Durée en heures (1.5)}
            {--days= : Durée en jours (journée de '.(int) (config('filament-tma.time.day_minutes', 420) / 60).' h)}
            {--date= : Jour de l’intervention (AAAA-MM-JJ, aujourd’hui par défaut)}
            {--summary= : Résumé d’intervention}
            {--summary-file= : Fichier du résumé (- pour l’entrée standard)}
            {--non-billable : Temps non facturable}
            {--internal : Le résumé reste une note interne (non partagée avec le client)}
            '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        $minutes = $this->minutes();

        if ($minutes === null) {
            $this->components->error(__('Indiquez une durée : --minutes, --hours ou --days.'));

            return self::FAILURE;
        }

        $summary = $this->summary();

        return $this->runWithBackend(
            fn ($backend) => $backend->logTime((string) $this->argument('ref'), $minutes, $this->option('date'), $summary, ! $this->option('non-billable'), (bool) $this->option('internal')),
            function (array $result): void {
                $this->components->info(__(':ticket : :time enregistrées le :date:summary. Total du ticket : :total.', [
                    'ticket' => $result['ticket'],
                    'time' => Duration::format((int) $result['time_entry']['minutes']),
                    'date' => $result['time_entry']['worked_on'],
                    'summary' => $result['summary_entry'] ? __(', avec le résumé') : '',
                    'total' => Duration::format((int) $result['time_total_minutes']),
                ]));
            },
        );
    }

    private function minutes(): ?int
    {
        foreach ([Duration::MINUTES, Duration::HOURS, Duration::DAYS] as $unit) {
            if (filled($this->option($unit))) {
                return Duration::toMinutes($this->option($unit), $unit);
            }
        }

        return null;
    }

    private function summary(): ?string
    {
        $file = $this->option('summary-file');

        if (filled($file)) {
            return (string) file_get_contents($file === '-' ? 'php://stdin' : $file);
        }

        return $this->option('summary');
    }
}
