<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use Illuminate\Console\Command;

/**
 * `tma:comment DES-0042 --body="…"` : un message ou, avec `--note`, une note sur le ticket entier. Sur un ticket, le
 * message est un « En cours » du Support (le ticket passe ou reste En cours, la main ne change pas), que `--commit`
 * peut rattacher à son commit.
 */
class CommentTicketCommand extends Command
{
    use UsesTmaBackend;

    protected $description = 'Ajoute un commentaire ou une note à un ticket';

    public function __construct()
    {
        $this->signature = 'tma:comment
            {ref : Numéro (DES-0042) ou ulid du ticket}
            {--body= : Le texte}
            {--body-file= : Fichier du texte (- pour l’entrée standard)}
            {--note : Une note sur le ticket plutôt qu’un message}
            {--commit= : Le commit qui porte le message (empreinte ou lien), sur un ticket}
            '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->option('body-file');
        $body = filled($file) ? (string) file_get_contents($file === '-' ? 'php://stdin' : $file) : (string) $this->option('body');

        if (trim($body) === '') {
            $this->components->error(__('filament-tma::commands.comment.empty'));

            return self::FAILURE;
        }

        return $this->runWithBackend(
            fn ($backend) => $backend->comment((string) $this->argument('ref'), $body, (bool) $this->option('note'), filled($this->option('commit')) ? (string) $this->option('commit') : null),
            fn (array $result) => $this->components->info(__('filament-tma::commands.comment.done', ['ticket' => $result['ticket'], 'type' => match (true) {
                $result['type'] === 'note' => __('filament-tma::commands.comment.note'),
                isset($result['state']) => __('filament-tma::commands.comment.progress'),
                default => __('filament-tma::commands.comment.comment'),
            }])),
        );
    }
}
