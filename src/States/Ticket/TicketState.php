<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentStateFusionEnhanced\Concerns\StateFusionInfo;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentState;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentStateFusion;
use CharlesStOlive\FilamentTma\Enums\Side;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Où en est un ticket (`Ticket::$state`). Chaque état dit à qui est le tour (`inHandsOf()`, addendum §3.1) :
 *
 *     draft ── transmettre, publier ──> submitted (le contact au Support est choisi à la transmission)
 *     submitted, in_progress ── demander des précisions ──> awaiting_requester ── répondre ──> in_progress
 *     submitted, in_progress ── marquer comme résolu ──> pending_validation ── confirmer ──> closed, renvoyer ──> in_progress
 *     submitted, in_progress ── mettre en pause ──> paused ── reprendre ──> in_progress
 *     draft, submitted ── retirer, refuser ──> cancelled
 *     forçages : tout état ouvert après draft ──> closed ; awaiting_requester, pending_validation ──> in_progress
 *
 * Les passages eux-mêmes (« Demander des précisions », « Répondre »…) sont les mouvements de `Workflow\TicketWorkflow` :
 * plusieurs mouvements peuvent relier les mêmes états (« Répondre » et « Forcer le retour » mènent tous deux de
 * l'attente à « En cours »), ce que la configuration de spatie, une classe par couple d'états, ne sait pas dire.
 * Elle ne fait donc que borner les couples permis.
 */
abstract class TicketState extends State implements HasFilamentState, HasFilamentStateFusion
{
    use StateFusionInfo;

    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): string;

    public function getDescription(): ?string
    {
        return null;
    }

    /** La partie qui doit agir ; null pour un ticket terminé. Un brouillon est à son auteur (voir `Ticket::sideInCharge()`). */
    abstract public function inHandsOf(): ?Side;

    public function isFinal(): bool
    {
        return false;
    }

    /** @return array<int, string> Les états d'un ticket qui avance : ni en pause, ni clos, ni annulé. */
    public static function active(): array
    {
        return array_map(fn (string $state): string => $state::getMorphClass(),
            [Draft::class, Submitted::class, InProgress::class, AwaitingRequester::class, PendingValidation::class]);
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->registerState([Draft::class, Submitted::class, InProgress::class, AwaitingRequester::class, Paused::class, PendingValidation::class, Closed::class, Cancelled::class])
            // Transmettre, publier ; retirer sa demande, la refuser.
            ->allowTransition(Draft::class, Submitted::class)
            ->allowTransition([Draft::class, Submitted::class], Cancelled::class)
            ->allowTransition([AwaitingRequester::class, Paused::class, PendingValidation::class], InProgress::class)
            // Le Support répond dès qu'une demande arrive : plus de « prise en charge ».
            ->allowTransition([Submitted::class, InProgress::class], AwaitingRequester::class)
            ->allowTransition([Submitted::class, InProgress::class], Paused::class)
            ->allowTransition([Submitted::class, InProgress::class], PendingValidation::class)
            // Confirmer, la clôture automatique, et « Forcer la clôture » de tout état ouvert.
            ->allowTransition([Submitted::class, InProgress::class, AwaitingRequester::class, Paused::class, PendingValidation::class], Closed::class);
    }
}
