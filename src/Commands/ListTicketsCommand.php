<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** `tma:list` : les tickets et tâches projet ouverts (ou filtrés), du plus récemment actif au plus ancien. */
class ListTicketsCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Liste les tickets de TMA et les tâches projet (ouverts par défaut)';

    public function __construct()
    {
        $this->signature = 'tma:list
            {--type=all : tickets, tasks ou all}
            {--state= : États séparés par des virgules (in_progress,awaiting_requester…)}
            {--project= : Projet (tâches)}
            {--app= : Application source (nom ou identifiant), dans le maître}
            {--search= : Texte du titre, ou numéro}
            {--mine : Seulement ceux dont je suis le contact au Support}
            {--all : Y compris les tickets terminés}
            {--limit=50 : Nombre maximal}
            '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithBackend(
            fn ($backend) => $backend->list([
                'type' => (string) $this->option('type'),
                'state' => $this->option('state'),
                'project' => $this->option('project'),
                'app' => $this->option('app'),
                'search' => $this->option('search'),
                'mine' => (bool) $this->option('mine'),
                'all' => (bool) $this->option('all'),
                'limit' => (int) $this->option('limit'),
            ]),
            function (array $tickets): void {
                if ($tickets === []) {
                    $this->components->info(__('filament-tma::commands.list.empty'));

                    return;
                }

                $this->table(
                    [__('filament-tma::common.number'), __('filament-tma::common.title'), __('filament-tma::common.state'), __('filament-tma::common.waiting_for'), __('filament-tma::common.application'), __('filament-tma::common.support_contact'), __('filament-tma::common.time')],
                    collect($tickets)->map(fn (array $t): array => [
                        $t['ref'], Str::limit(($t['kind'] === 'task' && filled($t['project'] ?? null) ? "[{$t['project']}] " : '').$t['title'], 50), $t['state_label'], $t['waiting_on'] ?? $t['in_hands_of'] ?? '—',
                        $t['application'] ?? '—', $t['assignee'] ?? '—', Duration::format((int) $t['time_minutes']),
                    ])->all(),
                );
            },
        );
    }
}
