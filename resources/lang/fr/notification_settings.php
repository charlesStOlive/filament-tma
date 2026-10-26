<?php

return [
    'mine' => 'Mes notifications',
    'flush' => [
        'label' => 'Envoyer maintenant',
        'heading' => 'Envoyer les notifications en attente ?',
        'nothing' => 'Rien n’attend un envoi groupé.',
        'description' => ':n notification(s) en attente pour :r personne(s) : chacune reçoit son récapitulatif maintenant, au lieu d’attendre le prochain.',
        'submit' => 'Envoyer',
        'done' => ':r récapitulatif(s) envoyé(s)',
        'done_body' => ':n notification(s).',
    ],
    'remind' => [
        'label' => 'Relancer',
        'heading' => 'Relancer : :who',
        'description' => 'Un message marqué « Relance » part dans l’historique ; qui a la main reçoit un courriel tout de suite, même s’il n’a pas choisi l’e-mail direct.',
        'message' => 'Votre mot (facultatif)',
        'sent' => 'Relance envoyée',
        'default_message' => 'Ce ticket attend votre retour.',
    ],
    'inform' => [
        'label' => 'Informer par courriel',
        'heading' => 'Informer le client par courriel',
        'description' => 'Votre message part dans l’historique de la tâche, et ses destinataires principaux chez le client le reçoivent par courriel. Sans cela, une tâche ne leur parvient que dans la cloche et le bilan de la semaine.',
        'sent' => 'Message envoyé',
        'sent_body' => 'Le client le reçoit par courriel.',
    ],
];
