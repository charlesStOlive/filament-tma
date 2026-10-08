<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Qui agit, et d'où : un utilisateur de l'interface ou d'une commande, l'API de l'autre application (avec l'acteur
 * qu'elle décrit), ou le planificateur (personne).
 *
 * `instanceUid` et `fromMaster` disent au nom de quelle application on agit : c'est elle qui décide de la partie
 * (demandeur, intervenant). Ici, sauf pour un appel de l'API : une application cliente n'agit sur le maître qu'en
 * demandeur de ses propres tickets, le maître sur un client qu'en intervenant du centre de service.
 */
final class Context
{
    public readonly string $instanceUid;

    public readonly bool $fromMaster;

    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly ?Actor $actor,
        public readonly Origin $origin,
        ?string $instanceUid = null,
        ?bool $fromMaster = null,
    ) {
        $this->instanceUid = $instanceUid ?? Tma::instanceUid();
        $this->fromMaster = $fromMaster ?? Tma::isMaster();
    }

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

    /** Un appel de l'autre application, au nom de l'un de ses utilisateurs (`actor`). */
    public static function api(Actor $actor, string $instanceUid, bool $fromMaster): self
    {
        return new self(null, $actor, Origin::Api, $instanceUid, $fromMaster);
    }

    public function isApi(): bool
    {
        return $this->origin === Origin::Api;
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
