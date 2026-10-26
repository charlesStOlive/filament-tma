<?php

return [
    'tasks' => [
        'heading' => 'Tâches',
        'create' => 'Nouvelle tâche',
    ],
    'model' => [
        'singular' => 'projet',
        'plural' => 'projets',
    ],
    'fields' => [
        'instance' => 'Application destinataire',
        'instance_help' => 'Toutes les tâches du projet vont à cette application.',
        'estimate_min' => 'Fourchette : au moins',
        'estimate_help' => 'En jours (une journée : :day) ; 0,5 pour une demi-journée.',
        'estimate_max' => 'au plus',
        'participants' => 'Participants',
        'participants_help' => 'En plus des destinataires de ses tâches, qui y participent d’office.',
        'other_participants' => 'Autres participants',
    ],
    'help' => [
        'estimate' => 'La fourchette',
        'time' => 'Le temps d’un projet',
    ],
    'errors' => [
        'estimate_order' => 'Le haut de la fourchette ne peut pas être sous le bas.',
    ],
    'notifications' => [
        'saved' => 'Projet enregistré',
        'resent' => 'Le projet repart, avec :n tâche publiée.|Le projet repart, avec ses :n tâches publiées.',
        'resent_empty' => 'Le projet repart (il n’a pas de tâche publiée).',
    ],
    'columns' => [
        'progress' => ':done / :total terminées',
        'no_estimate' => 'Pas de fourchette',
        'time_help' => 'La somme du temps de ses tâches.',
        'no_task_in_progress' => 'Aucune tâche en cours',
        'tasks_in_progress' => ':n tâche en cours|:n tâches en cours',
    ],
];
