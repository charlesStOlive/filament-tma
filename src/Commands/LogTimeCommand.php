<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Console\Command;

/**
 * `tma:log DES-0042 --hours=2 [--entry=01k…]` : saisir du temps (dans le maître), sur un message ou une note qui
 * existe déjà (son ulid, voir `tma:show`), ou sur la fiche entière. Sur une tâche projet, `--summary` écrit aussi le
 * message qui dit ce qui a été fait (`--summary-file` : un fichier, `-` l'entrée standard) ; sur un ticket, le temps n'a
 * pas de texte (un message y va avec un passage).
 */
class LogTimeCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Saisit du temps sur un ticket ou une tâche, sur un message, une note ou la fiche entière';

    public function __construct()
    {
        $this->signature = 'tma:log
            {ref : Numéro (DES-0042) ou ulid du ticket}
            {--minutes= : Durée en minutes}
            {--hours= : Durée en heures (1.5)}
            {--days= : Durée en jours (journée de '.(int) (config('filament-tma.time.day_minutes', 420) / 60).' h)}
            {--date= : Jour de l’intervention (AAAA-MM-JJ, aujourd’hui par défaut)}
            {--summary= : Tâche projet : ce qui a été fait (écrit en message dans la chronologie)}
            {--summary-file= : Fichier du résumé (- pour l’entrée standard)}
            {--non-billable : Temps non facturable}
            {--internal : Tâche projet : le résumé devient une note plutôt qu’un message}
            {--entry= : Ulid d’un message ou d’une note de la fiche sur lequel porte le temps (au lieu d’un résumé)}
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
            fn ($backend) => $backend->logTime((string) $this->argument('ref'), $minutes, $this->option('date'), $summary, ! $this->option('non-billable'), (bool) $this->option('internal'), $this->option('entry')),
            function (array $result): void {
                $this->components->info(__(':ticket : :time enregistrées le :date:summary. Total du ticket : :total.', [
                    'ticket' => $result['ticket'],
                    'time' => Duration::format((int) $result['time_entry']['minutes']),
                    'date' => $result['time_entry']['worked_on'],
                    'summary' => match (true) {
                        filled($result['summary_entry'] ?? null) => __(', avec le résumé'),
                        filled($result['entry'] ?? null) => __(', sur le message :entry', ['entry' => $result['entry']]),
                        default => '',
                    },
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
