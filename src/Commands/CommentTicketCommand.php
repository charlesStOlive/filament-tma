<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Commands\Concerns\UsesTmaBackend;
use Illuminate\Console\Command;

/** `tma:comment DES-0042 --body="…"` : un commentaire (partagé) ou, avec `--note`, une note interne. */
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
            {--note : Une note interne plutôt qu’un commentaire}
            '.$this->backendOptions();

        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->option('body-file');
        $body = filled($file) ? (string) file_get_contents($file === '-' ? 'php://stdin' : $file) : (string) $this->option('body');

        if (trim($body) === '') {
            $this->components->error(__('Le texte est vide : --body ou --body-file.'));

            return self::FAILURE;
        }

        return $this->runWithBackend(
            fn ($backend) => $backend->comment((string) $this->argument('ref'), $body, (bool) $this->option('note')),
            fn (array $result) => $this->components->info(__(':ticket : :type ajouté(e).', ['ticket' => $result['ticket'], 'type' => $result['type'] === 'note' ? __('note') : __('commentaire')])),
        );
    }
}
