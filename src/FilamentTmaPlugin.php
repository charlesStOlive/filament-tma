<?php

namespace CharlesStOlive\FilamentTma;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Les écrans de filament-tma dans un panel :
 *
 *     ->plugins([FilamentTmaPlugin::make()])
 *
 * Le mode (maître ou client), l'identifiant de l'installation et le reste se règlent dans config/filament-tma.php.
 */
class FilamentTmaPlugin implements Plugin
{
    public const ID = 'filament-tma';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(self::ID);
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            TicketResource::class,
            TaskResource::class,
            InstanceResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
