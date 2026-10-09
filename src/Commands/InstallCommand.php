<?php

namespace CharlesStOlive\FilamentTma\Commands;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\EnvFile;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Configure l'installation dans `.env` : son mode, son identifiant (généré s'il n'y en a pas), son nom, son préfixe,
 * et, pour une application cliente, le centre de service.
 *
 *     php artisan tma:install --mode=master --name=Filbreeze --prefix=FIL
 *     php artisan tma:install --mode=client --name=Desapp --prefix=DES --uid=01K… --master-url=… --master-token=… --master-account=filbreeze@api.local
 *
 * `--master-account` crée, dans l'application cliente, le compte par lequel le centre de service l'appellera, et
 * affiche son jeton : il se colle dans « Applications clientes › Jeton émis par l'application ».
 *
 * L'identifiant ne change plus une fois des tickets créés.
 */
class InstallCommand extends Command
{
    protected $signature = 'tma:install
        {--mode= : master (centre de service) ou client}
        {--uid= : L’identifiant à prendre (sinon : celui déjà réglé, ou un nouveau)}
        {--name= : Le nom de l’application}
        {--prefix= : Le début des numéros de tickets (DES donne DES-0042)}
        {--master-url= : Client : l’adresse du centre de service}
        {--master-token= : Client : le jeton émis par le centre de service}
        {--master-account= : Client : l’e-mail du compte qu’utilisera le centre de service (créé, avec son jeton)}
        {--show : Afficher les lignes de .env sans rien écrire}';

    protected $description = 'Configure le module TMA dans .env : mode, identifiant, centre de service';

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
            'TMA_TICKET_PREFIX' => $this->option('prefix') ? strtoupper($this->option('prefix')) : null,
            'TMA_MASTER_URL' => $mode === 'client' ? $this->option('master-url') : null,
            'TMA_MASTER_TOKEN' => $mode === 'client' ? $this->option('master-token') : null,
            'TMA_MASTER_API_USER' => $mode === 'client' ? $this->option('master-account') : null,
        ], fn ($value): bool => filled($value));

        $this->write($values);
        config(['filament-tma.mode' => $mode, 'filament-tma.instance.uid' => $uid]);

        if ($mode === 'client' && filled($email = $this->option('master-account'))) {
            if ($this->option('show')) {
                $this->components->warn(__('--show : le compte du centre de service n’est pas créé.'));
            } else {
                $token = ServiceAccount::token(ServiceAccount::user($email, __('Centre de service (TMA)')));

                $this->newLine();
                $this->components->info(__('Compte du centre de service : :email. Son jeton, à coller dans le centre de service (Applications clientes › Jeton émis par l’application) :', ['email' => $email]));
                $this->line($token);
            }
        }

        $this->newLine();
        $this->components->bulletList([
            __('Identifiant de cette application : :uid', ['uid' => $uid]),
            $mode === 'master'
                ? __('Déclarez chaque application cliente dans TMA › Applications clientes.')
                : __('Dans le centre de service, l’application se déclare avec cet identifiant.'),
            __('Pensez à `php artisan config:clear` si la configuration est en cache.'),
        ]);

        return self::SUCCESS;
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
