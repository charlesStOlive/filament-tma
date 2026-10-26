<?php

return [
    'delete' => [
        'heading' => 'Supprimer la tâche :number ?',
        'submit' => 'Supprimer définitivement',
        'done' => 'Tâche :number supprimée',
        'bulk_heading' => 'Supprimer la tâche sélectionnée ?|Supprimer les :count tâches sélectionnées ?',
        'bulk_done' => ':count tâche supprimée|:count tâches supprimées',
        'messages' => ':count message|:count messages',
        'notes' => ':count note|:count notes',
        'time' => ':time de temps saisi',
        'nothing_one' => 'Elle n’a encore ni message, ni note, ni temps saisi.',
        'nothing_many' => 'Elles n’ont encore ni message, ni note, ni temps saisi.',
        'goes_with_one' => 'Partent avec elle : :what.',
        'goes_with_many' => 'Partent avec elles : :what.',
        'published_one' => 'Elle est publiée : elle disparaît aussi chez :client.',
        'published_many' => 'Une est publiée : elle disparaît aussi chez :client.|:count sont publiées : elles disparaissent aussi chez :client.',
        'warning' => 'Les tickets ouverts depuis une tâche restent, sans lien. La suppression ne se défait pas.',
        'and' => 'et',
    ],
    'errors' => [
        'recipient_required' => 'Choisissez au moins un destinataire principal chez le client, « Toute l’équipe projet » ou « Tout le monde ».',
        'due_before_start' => 'L’échéance ne peut pas précéder le début.',
    ],
    'fields' => [
        'new_project' => 'Nouveau projet',
        'starts_on_help' => 'Vide : le jour de la création.',
        'due_on_help' => 'Facultative : une date visée, pas une fin fixe.',
        'publish_at_help' => 'Vide : la tâche part chez le client dès l’enregistrement. Avec une date, elle reste en brouillon, invisible du client, et se publie à l’heure dite.',
        'project_estimate' => 'Fourchette du projet',
        'project_time' => 'Temps du projet',
        'schedule' => 'Calendrier',
        'completed_at' => 'Terminée le',
        'publish_at' => 'Publication prévue',
        'published_at' => 'Publiée le',
    ],
    'help' => [
        'dates' => 'Début et échéance',
        'publish' => 'Publier une tâche',
    ],
    'publication' => [
        'published' => 'Publiée chez :client',
        'still_draft' => 'Restée en brouillon : vous ne pouvez pas la publier.',
    ],
    'model' => [
        'title' => 'Tâche',
        'singular' => 'tâche projet',
        'plural' => 'tâches projet',
    ],
    'actions' => [
        'create_ticket' => 'Créer un ticket',
        'create_ticket_heading' => 'Créer un ticket chez le Support',
    ],
    'schedule' => [
        'publication' => 'Publication le :date à :time',
        'on_date' => 'le :date',
        'overdue' => 'En retard : échéance le :date',
        'due' => 'Échéance le :date',
    ],
];
