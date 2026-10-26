<?php

return [
    'task' => [
        'reopen' => [
            'label' => 'Rouvrir',
            'description' => 'La tâche repart En cours, chez le client aussi.',
            'reason' => 'Pourquoi la rouvrir ? (facultatif)',
        ],
        'complete' => [
            'label' => 'Terminer',
            'message' => 'Le mot de la fin (facultatif)',
            'message_help' => 'Ce qui a été livré, ce qui reste à faire : il ouvre le bloc de clôture de l’historique.',
        ],
        'publish' => [
            'description' => 'La tâche devient visible dans l’application du client.',
            'recipients' => 'Destinataires principaux chez le client',
            'recipients_help' => 'Prévenus de la publication, puis de tout ce qui arrive à la tâche.',
        ],
        'cancel' => [
            'label' => 'Annuler',
        ],
    ],
    'reason' => 'Motif',
    'help' => [
        'reason' => 'Quand faut-il un motif ?',
    ],
    'errors' => [
        'ticket_move' => 'Ce passage n’est pas possible pour ce ticket.',
        'support_prepares' => 'Seul le Support prépare un ticket pour un client.',
        'cannot_create_ticket' => 'Vous ne pouvez pas créer de ticket.',
        'prepare_for_client' => 'Dans le Support, un ticket se prépare pour un client.',
        'task_move' => 'Ce passage n’est pas possible pour cette tâche.',
        'tasks_at_support' => 'Les tâches projet se créent chez le Support.',
        'cannot_create_task' => 'Vous ne pouvez pas créer de tâche.',
        'cannot_delete_task' => 'Vous ne pouvez pas supprimer la tâche :number.',
        'task_billed' => 'Du temps de la tâche :number est facturé : elle ne se supprime pas.',
    ],
    'ticket' => [
        'force_return' => [
            'label' => 'Forcer le retour',
            'description' => 'Hors du tour : la main revient au Support sans attendre le demandeur.',
            'help' => 'Quand forcer le retour ?',
        ],
        'reopen' => [
            'label' => 'Retour au Support',
            'message' => 'Ce qui ne va pas',
            'help' => 'Confirmer ou renvoyer ?',
        ],
        'take_over' => [
            'label' => 'Prendre la main',
            'label_named' => 'Prendre la main (à la place de :name)',
            'description' => 'Vous devenez le demandeur : c’est vous que le Support attendra, pour cette réponse et la suite.',
        ],
        'resume' => [
            'label' => 'Reprendre',
        ],
        'cancel' => [
            'label' => 'Retirer la demande',
        ],
        'answer' => [
            'message' => 'Réponse',
            'help' => 'Pourquoi répondre ici ?',
        ],
        'force_close' => [
            'label' => 'Forcer la clôture',
            'description' => 'Hors du tour : le ticket est clos tout de suite, quel que soit celui qu’on attend.',
            'help' => 'Quand forcer la clôture ?',
        ],
        'submit_for_validation' => [
            'label' => 'Marquer comme résolu',
            'help' => 'Et ensuite ?',
        ],
        'refuse' => [
            'label' => 'Refuser',
        ],
        'auto_close' => [
            'label' => 'Clos automatiquement (sans réponse)',
            'message' => 'Clos automatiquement : aucune activité depuis :days jours.',
        ],
        'validate' => [
            'label' => 'Confirmer et clore',
            'message' => 'Un mot pour le Support (facultatif)',
        ],
        'pause' => [
            'label' => 'Mettre en pause',
            'help' => 'Que se passe-t-il à la reprise ?',
            'help_title' => 'La pause et sa reprise',
        ],
        'publish' => [
            'description' => 'Le ticket devient visible dans l’application du client.',
        ],
        'request_answer' => [
            'label' => 'Demander des précisions',
            'message' => 'Question',
            'help' => 'Que devient le ticket ?',
        ],
        'auto_resume' => [
            'label' => 'Reprise à la date prévue',
        ],
        'transmit' => [
            'label' => 'Transmettre au Support',
            'description' => 'Le Support prend la suite : il fera autorité sur ce ticket.',
            'contact_help' => 'La personne du Support qui suivra votre demande.',
        ],
    ],
    'move_heading' => [
        'ticket' => 'Ticket :number : :state',
        'task' => 'Tâche :number : :state',
    ],
];
