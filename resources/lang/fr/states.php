<?php

return [
    'task' => [
        'cancelled' => [
            'label' => 'Annulée',
            'description' => 'Abandonnée, avec son historique.',
        ],
        'draft' => [
            'label' => 'Brouillon',
            'description' => 'Visible du Support seulement : pas encore publiée chez le client.',
        ],
        'done' => [
            'label' => 'Terminée',
            'description' => 'Achevée : elle peut être facturée entièrement.',
        ],
        'in_progress' => [
            'description' => 'Mise à disposition du client et synchronisée.',
        ],
    ],
    'ticket' => [
        'pending_validation' => [
            'label' => 'Résolu',
            'description' => 'Le travail est fait : le demandeur confirme, ou renvoie la demande.',
        ],
        'submitted' => [
            'label' => 'Nouveau',
            'description' => 'Transmis au Support : attend sa première réponse.',
        ],
        'paused' => [
            'label' => 'En pause',
            'description' => 'Suspendu jusqu’à une date prévue, puis repris.',
        ],
        'awaiting_requester' => [
            'label' => 'En attente du demandeur',
            'description' => 'Le demandeur doit apporter des précisions.',
        ],
        'cancelled' => [
            'label' => 'Annulé',
            'description' => 'Abandonné ou refusé, avec son motif.',
        ],
        'in_progress' => [
            'description' => 'La demande est traitée.',
        ],
        'draft' => [
            'description' => 'En préparation : visible de son auteur et de son application seulement.',
        ],
        'closed' => [
            'label' => 'Clos',
            'description' => 'Terminé. C’est l’état qui ouvre la facturation complète.',
        ],
    ],
];
