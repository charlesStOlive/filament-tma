<?php

return [
    'allowance_period' => [
        'monthly' => 'Mensuel',
        'quarterly' => 'Trimestriel',
    ],
    'notice_event' => [
        'ticket_waiting' => 'Un ticket vous attend',
        'new_note' => 'Nouvelle note',
        'project_task' => 'Tâche projet',
        'recipient' => 'Vous êtes destinataire',
        'stale' => 'Rappel : rien ne bouge',
        'closing_warning' => 'Avertissement de clôture',
        'auto_close' => 'Clôture automatique',
    ],
    'origin' => [
        'interface' => 'Interface',
        'api' => 'API',
        'command' => 'Commande',
        'scheduler' => 'Planificateur',
    ],
    'priority' => [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'urgent' => 'Urgente',
    ],
    'sync_status' => [
        'local' => 'Non partagé',
        'pending' => 'En attente de synchronisation',
        'synced' => 'Synchronisé',
        'failed' => 'Synchronisation en erreur',
    ],
    'notice_mode' => [
        'immediate' => 'Tout de suite',
        'digest' => 'Dans le bilan',
        'none' => 'Non envoyée (sans bilan)',
    ],
    'side' => [
        'master' => 'Équipe support',
    ],
    'ticket_opening' => [
        'request' => [
            'label' => 'Demande',
            'description' => 'Un correspondant fait sa demande.',
        ],
        'on_behalf' => [
            'label' => 'Au nom d’un client',
            'description' => 'Sa demande, reçue autrement (un courriel…) : à vous d’y répondre.',
        ],
        'addressed' => [
            'label' => 'Je m’adresse au client',
            'description' => 'Une idée, une question, une proposition : vous attendez sa réponse.',
        ],
        'notice' => [
            'label' => 'J’informe le client',
            'description' => 'Pour information, sans réponse attendue : le ticket est clos dès sa publication.',
        ],
        'addressed_to' => 'Adressé à',
        'support_message' => 'Message du Support',
        'information' => 'Information',
    ],
    'entry_type' => [
        'note' => 'Note',
    ],
    'recipient_role' => [
        'main' => 'Principal',
        'secondary' => 'Secondaire',
    ],
];
