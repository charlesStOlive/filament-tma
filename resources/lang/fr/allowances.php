<?php

return [
    'model' => [
        'singular' => 'forfait',
        'plural' => 'forfaits',
    ],
    'fields' => [
        'instance_help' => 'Sans application choisie, le forfait est partagé entre toutes celles du client.',
        'all_applications' => 'Toutes les applications du client',
        'contract' => 'Contrat',
        'period' => 'Rythme',
        'volume' => 'Volume par période',
        'unit' => 'En',
    ],
    'help' => [
        'scope' => 'Client ou application ?',
        'period' => 'Mensuel ou trimestriel ?',
        'volume' => 'Ce qui est compté',
    ],
    'columns' => [
        'allocated' => 'Alloué',
        'consumed' => 'Consommé (période en cours)',
        'remaining' => 'Restant',
        'period_summary' => 'Période :p : :c consommées sur :a.',
    ],
    'actions' => [
        'reset_thresholds' => 'Réinitialiser les seuils',
        'reset_thresholds_description' => 'Les seuils de la période en cours pourront se déclencher de nouveau. Voir l’aide « Les seuils d’un forfait ».',
        'thresholds_reset' => 'Seuils réinitialisés',
    ],
];
