<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use CharlesStOlive\FilamentTma\Support\Duration;
use Illuminate\Console\Command;

/** `tma:show DES-0042` : un ticket, sa description et sa chronologie (en texte). */
class ShowTicketCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Affiche un ticket et sa chronologie';

    public function __construct()
    {
        $this->signature = 'tma:show {ref : Numéro (DES-0042) ou ulid du ticket} '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithBackend(
            fn ($backend) => $backend->show((string) $this->argument('ref')),
            function (array $t): void {
                $this->newLine();
                $this->line("<options=bold>{$t['ref']} — {$t['title']}</>");
                $this->line(__('État : :state (en attente de : :side) · priorité : :priority · temps : :time', [
                    'state' => $t['state_label'], 'side' => $t['waiting_on'] ?? $t['in_hands_of'] ?? '—', 'priority' => $t['priority'] ?? '—',
                    'time' => Duration::format((int) $t['time_minutes']),
                ]));
                $this->line($t['kind'] === 'task'
                    ? __('Projet : :p · suivie par : :a', ['p' => $t['project'] ?? '—', 'a' => $t['assignee'] ?? '—'])
                    : __('Demandeur : :r · contact au Support : :a', ['r' => $t['requester'] ?? '—', 'a' => $t['assignee'] ?? '—']));

                if (filled($t['url'] ?? null)) {
                    $this->line($t['url']);
                }

                if (filled($t['description'])) {
                    $this->newLine();
                    $this->line($t['description']);
                }

                $this->newLine();
                foreach ($t['timeline'] as $item) {
                    $this->renderItem($item);
                }
            },
        );
    }

    /** @param  array<string, mixed>  $item */
    private function renderItem(array $item, string $indent = ''): void
    {
        $at = substr((string) $item['at'], 0, 16);

        if ($item['kind'] === 'transition') {
            $this->line("{$indent}<fg=gray>{$at} {$item['author']} : {$item['move']} ({$item['from']} → {$item['to']})".($item['reason'] ? " « {$item['reason']} »" : '').'</>');

            return;
        }

        $this->line("{$indent}<fg=cyan>{$at} [{$item['kind']}] {$item['author']}</>");
        foreach (explode("\n", (string) $item['text']) as $line) {
            $this->line("{$indent}  {$line}");
        }

        foreach ($item['replies'] ?? [] as $reply) {
            $this->renderItem($reply, $indent.'    ');
        }
    }
}
