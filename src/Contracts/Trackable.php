<?php

namespace CharlesStOlive\FilamentTma\Contracts;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Entry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\ModelStates\State;

/**
 * Ce qui a une chronologie, du temps passé et une copie chez l'autre application : un ticket, une tâche projet.
 * Les objets métier restent distincts (leurs états, leurs mouvements) ; ce contrat porte les composants communs
 * (CDC §5) : `Workflow\Timeline`, `Workflow\TimeLog`, la synchronisation, les fichiers.
 *
 * @property string $ulid
 * @property string $number
 * @property State $state
 */
interface Trackable
{
    /** `ticket` ou `task` : le nom de l'objet dans l'API. */
    public function kind(): string;

    /** L'application cliente concernée : la source d'un ticket, la destinataire d'une tâche. */
    public function clientInstanceUid(): string;

    public function entries(): MorphMany;

    public function transitions(): MorphMany;

    /** Les destinataires (`Models\Recipient`). */
    public function recipients(): MorphMany;

    public function timeEntries(): MorphMany;

    public function touchActivity(): void;

    /** On peut encore y écrire des messages (les notes, elles, restent toujours possibles). */
    public function acceptsMessages(): bool;

    /** @return Collection<int, Entry> les messages de premier niveau, avec leurs réponses et leurs notes */
    public function timelineEntries(): Collection;

    /** @return Collection<int, Entry> les notes qu'une personne peut lire, sur le ticket entier ou sur un message */
    public function noteEntries(?Authenticatable $user, bool $withArchived = false): Collection;

    /** @return array<int, Side> les parties que représente cette application */
    public function sidesHere(): array;

    /** @return array<int, Side> les parties que représente une application donnée */
    public function sidesOf(string $instanceUid, bool $isMaster): array;

    /** Cette application est l'intervenant. */
    public function isHandledHere(): bool;

    /** Cette application fait autorité (sinon, elle envoie ce qu'elle écrit à celle qui fait autorité). */
    public function authorityIsHere(): bool;

    /** La copie est partagée avec l'autre application (un ticket au Support, une tâche publiée). */
    public function isShared(): bool;
}
