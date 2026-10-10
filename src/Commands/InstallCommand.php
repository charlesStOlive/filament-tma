<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\EnvFile;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Configure l'installation dans `.env` : son mode, son identifiant (généré s'il n'y en a pas), son nom, son préfixe,
 * et, pour une application cliente, le nom court de son client et le Support.
 *
 *     php artisan tma:install --mode=master --name=Filbreeze --prefix=FIL
 *     php artisan tma:install --mode=client --name=Desapp --short-name=EDM --prefix=DES --uid=01K… --master-url=… --master-token=… --master-account=support@tma.local
 *
 * `--master-account` crée, dans l'application cliente, le compte par lequel le Support l'appellera, et lui transmet
 * son jeton (`POST master/register`) ; si la transmission échoue, il l'affiche.
 *
 * L'identifiant ne change plus une fois des tickets créés.
 */
class InstallCommand extends Command
{
    protected $signature = 'tma:install
        {--mode= : master (le Support) ou client}
        {--uid= : L’identifiant à prendre (sinon : celui déjà réglé, ou un nouveau)}
        {--name= : Le nom de l’application}
        {--short-name= : Client : le nom court du client (EDM donne « Équipe EDM », « Publier chez EDM »)}
        {--prefix= : Le début des numéros de tickets (DES donne DES-0042)}
        {--master-url= : Client : l’adresse du Support}
        {--master-token= : Client : le jeton émis par le Support}
        {--master-account= : Client : l’e-mail du compte qu’utilisera le Support (créé, avec son jeton)}
        {--show : Afficher les lignes de .env sans rien écrire}';

    protected $description = 'Configure le module TMA dans .env : mode, identifiant, Support';

    public function handle(): int
    {
        $mode = $this->option('mode') ?: config('filament-tma.mode', 'client');

        if (! in_array($mode, ['master', 'client'], true)) {
            $this->components->error(__('Le mode est master ou client.'));

            return self::FAILURE;
        }

        $current = config('filament-tma.instance.uid');
        $uid = strtoupper((string) ($this->option('uid') ?: $current ?: Str::ulid()));

        if (strlen($uid) !== 26) {
            $this->components->error(__('Un identifiant fait 26 caractères (un ULID).'));

            return self::FAILURE;
        }

        if (filled($current) && $current !== $uid && Ticket::withTrashed()->exists()) {
            $this->components->error(__('Des tickets existent déjà avec l’identifiant :uid : il ne change plus.', ['uid' => $current]));

            return self::FAILURE;
        }

        $values = array_filter([
            'TMA_MODE' => $mode,
            'TMA_INSTANCE_UID' => $uid,
            'TMA_INSTANCE_NAME' => $this->option('name'),
            'TMA_INSTANCE_SHORT_NAME' => $mode === 'client' ? $this->option('short-name') : null,
            'TMA_TICKET_PREFIX' => $this->option('prefix') ? strtoupper($this->option('prefix')) : null,
            'TMA_MASTER_URL' => $mode === 'client' ? $this->option('master-url') : null,
            'TMA_MASTER_TOKEN' => $mode === 'client' ? $this->option('master-token') : null,
            'TMA_MASTER_API_USER' => $mode === 'client' ? $this->option('master-account') : null,
        ], fn ($value): bool => filled($value));

        $this->write($values);
        config(['filament-tma.mode' => $mode, 'filament-tma.instance.uid' => $uid]);

        if ($mode === 'client' && filled($email = $this->option('master-account'))) {
            if ($this->option('show')) {
                $this->components->warn(__('--show : le compte du Support n’est pas créé.'));
            } else {
                $this->connectMaster($email, $values);
            }
        }

        $this->newLine();
        $this->components->bulletList([
            __('Identifiant de cette application : :uid', ['uid' => $uid]),
            $mode === 'master'
                ? __('Déclarez chaque application cliente dans Support › Applications clientes.')
                : __('Chez le Support, l’application se déclare avec cet identifiant.'),
            __('Pensez à `php artisan config:clear` si la configuration est en cache.'),
        ]);

        return self::SUCCESS;
    }

    /**
     * Crée le compte par lequel le Support appellera l'application, et lui transmet son jeton. Si la
     * transmission échoue, le jeton s'affiche : il se colle alors à la main dans la fiche de l'application.
     *
     * @param  array<string, string>  $values
     */
    private function connectMaster(string $email, array $values): void
    {
        $token = ServiceAccount::token(ServiceAccount::user($email, __('Support (TMA)')));

        config(array_filter([
            'filament-tma.api.master_user' => $email,
            'filament-tma.master.url' => $values['TMA_MASTER_URL'] ?? null,
            'filament-tma.master.token' => $values['TMA_MASTER_TOKEN'] ?? null,
        ]));

        if (blank(config('filament-tma.master.url')) || blank(config('filament-tma.master.token'))) {
            $this->showToken($email, $token, __('Le Support n’est pas encore configuré (--master-url, --master-token).'));

            return;
        }

        try {
            $answer = app(MasterClient::class)->register($token);
        } catch (SyncException $e) {
            $this->showToken($email, $token, $e->getMessage());

            return;
        }

        $this->components->info(__('Jeton transmis au Support (application « :name »).', ['name' => $answer['instance'] ?? '?']));

        if ($answer['callback'] ?? false) {
            $this->components->info(__('Le Support joint cette application : la liaison marche dans les deux sens.'));
        } else {
            $this->components->warn(__('Le Support ne joint pas encore cette application : :error', ['error' => $answer['callback_error'] ?? '?']));
        }
    }

    private function showToken(string $email, string $token, string $why): void
    {
        $this->components->warn($why);
        $this->components->info(__('Compte du Support : :email. Son jeton, à coller chez le Support (Applications clientes › Saisir le jeton à la main) :', ['email' => $email]));
        $this->line($token);
    }

    /** @param  array<string, string>  $values */
    private function write(array $values): void
    {
        if ($this->option('show') || ! EnvFile::writable()) {
            if (! $this->option('show')) {
                $this->components->warn(__('.env n’est pas modifiable : ajoutez ces lignes vous-même.'));
            }

            $this->line(EnvFile::lines($values));

            return;
        }

        EnvFile::set($values);
        $this->components->info(__('.env mis à jour : :keys', ['keys' => implode(', ', array_keys($values))]));
    }
}
