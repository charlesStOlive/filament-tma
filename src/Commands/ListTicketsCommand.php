<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Console\Command;

/** `tma:list` : les tickets ouverts (ou filtrés), du plus récemment actif au plus ancien. */
class ListTicketsCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Liste les tickets de TMA (ouverts par défaut)';

    public function __construct()
    {
        $this->signature = 'tma:list
            {--state= : États séparés par des virgules (in_progress,awaiting_requester…)}
            {--app= : Application source (nom ou identifiant), dans le maître}
            {--search= : Texte du titre, ou numéro}
            {--mine : Seulement ceux dont je suis l’intervenant}
            {--all : Y compris les tickets terminés}
            {--limit=50 : Nombre maximal}
            '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithBackend(
            fn ($backend) => $backend->list([
                'state' => $this->option('state'),
                'app' => $this->option('app'),
                'search' => $this->option('search'),
                'mine' => (bool) $this->option('mine'),
                'all' => (bool) $this->option('all'),
                'limit' => (int) $this->option('limit'),
            ]),
            function (array $tickets): void {
                if ($tickets === []) {
                    $this->components->info(__('Aucun ticket.'));

                    return;
                }

                $this->table(
                    [__('N°'), __('Titre'), __('État'), __('Main'), __('Application'), __('Intervenant'), __('Temps')],
                    collect($tickets)->map(fn (array $t): array => [
                        $t['ref'], \Illuminate\Support\Str::limit($t['title'], 50), $t['state_label'], $t['in_hands_of'] ?? '—',
                        $t['application'] ?? '—', $t['assignee'] ?? '—', Duration::format((int) $t['time_minutes']),
                    ])->all(),
                );
            },
        );
    }
}
