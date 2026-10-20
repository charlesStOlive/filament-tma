<?php

namespace CharlesStOlive\FilamentTma\Enums;

/**
 * Ce qu'une notification attend de qui la reçoit (`Support\Notifier`), qui décide par où elle part :
 *
 * - `Action` : on attend quelque chose de lui (le ticket lui revient, une relance, un avertissement de clôture, un
 *   client qui commente une tâche, pour le Support). Un courriel s'il a choisi l'e-mail direct, sinon le bilan du
 *   matin ; la cloche dans tous les cas.
 * - `Outcome` : la fin d'un ticket (clos, refusé, retiré). La cloche, et le bilan du matin.
 * - `Info` : pour information. La cloche ; pour une tâche, aussi le bilan de la semaine.
 */
enum NoticeTone: string
{
    case Action = 'action';
    case Outcome = 'outcome';
    case Info = 'info';
}
