<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Actor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Qui agit, et d'où : un utilisateur de l'interface ou d'une commande, l'API de l'autre application (avec l'acteur
 * qu'elle décrit), ou le planificateur (personne).
 */
final class Context
{
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly ?Actor $actor,
        public readonly Origin $origin,
    ) {}

    public static function ui(Authenticatable $user): self
    {
        return new self($user, Actor::forUser($user), Origin::Ui);
    }

    public static function command(Authenticatable $user): self
    {
        return new self($user, Actor::forUser($user), Origin::Command);
    }

    public static function scheduler(): self
    {
        return new self(null, null, Origin::Scheduler);
    }

    /** L'utilisateur connecté, depuis l'interface. */
    public static function current(): self
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return self::ui($user);
    }

    public function isSystem(): bool
    {
        return $this->origin === Origin::Scheduler;
    }
}
