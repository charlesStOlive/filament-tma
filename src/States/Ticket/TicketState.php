<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentStateFusionEnhanced\Concerns\StateFusionInfo;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentState;
use CharlesStOlive\FilamentStateFusionEnhanced\Contracts\HasFilamentStateFusion;
use CharlesStOlive\FilamentTma\Enums\Side;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Où en est un ticket (`Ticket::$state`). Chaque état dit à qui est la main (`inHandsOf()`) :
 *
 *     draft ──────────────> submitted ──> in_progress ──> pending_validation ──> closed
 *                               │            │  ▲  │              │
 *                               │            │  │  └─> paused ────┘ (reprise)
 *                               │            ▼  │
 *                               │      awaiting_requester ─────────────────────> closed
 *                               └──────────────────────────────────────────────> cancelled
 *
 * Les passages eux-mêmes (« Prendre en charge », « Répondre »…) sont les mouvements de `Workflow\TicketWorkflow` :
 * plusieurs mouvements peuvent relier les mêmes états (« Répondre » et « Reprendre » mènent tous deux de
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

    public static function config(): StateConfig
    {
        $open = [Draft::class, Submitted::class, InProgress::class, AwaitingRequester::class, Paused::class, PendingValidation::class];

        return parent::config()
            ->default(Draft::class)
            ->registerState([...$open, Closed::class, Cancelled::class])
            ->allowTransition(Draft::class, Submitted::class)
            // Un ticket local transmis au centre de service repart en « Soumis ».
            ->allowTransition([InProgress::class, AwaitingRequester::class], Submitted::class)
            ->allowTransition([Submitted::class, AwaitingRequester::class, Paused::class, PendingValidation::class], InProgress::class)
            ->allowTransition(InProgress::class, AwaitingRequester::class)
            ->allowTransition(InProgress::class, Paused::class)
            ->allowTransition(InProgress::class, PendingValidation::class)
            ->allowTransition([AwaitingRequester::class, PendingValidation::class], Closed::class)
            ->allowTransition($open, Cancelled::class);
    }
}
