<?php

return [
    'number_title' => 'Note n° :number',
    'heading' => [
        'message' => 'Le message de :author et ses notes',
        'subject' => 'Les notes sur :subject',
    ],
    'add' => [
        'label' => 'Ajouter une note',
        'heading_message' => 'Une note sur le message de :author',
        'heading_subject' => 'Une note sur :subject',
        'description' => 'Tout le monde sur le ticket la lira, sauf si vous la gardez pour vous ; elle ne change pas son état.',
        'submit' => 'Ajouter la note',
        'done' => ':note ajoutée',
    ],
    'edit' => [
        'heading' => 'Modifier la note n° :number',
        'add_images' => 'Ajouter des images',
        'done' => ':note modifiée',
    ],
    'archive' => [
        'restore' => 'Ressortir',
        'archive' => 'Archiver',
        'archived' => ':note archivée',
        'restored' => ':note ressortie',
    ],
    'delete' => [
        'heading' => 'Supprimer la note n° :number ?',
        'description' => 'Elle disparaît, des deux côtés. Son numéro n’est pas redonné.',
        'done' => ':note supprimée',
    ],
    'message' => [
        'edit_heading' => 'Modifier votre message',
        'edit_description' => 'Possible tant que personne d’autre n’a écrit après lui. Tout le monde verra qu’il a été modifié.',
        'edited' => 'Message modifié',
    ],
    'fields' => [
        'placeholder' => 'Votre note…',
        'private' => 'Visible de moi seul',
        'private_help' => 'Personne d’autre ne la verra. Vous pourrez la montrer à tous plus tard.',
        'images' => 'Images',
    ],
    'help' => 'Les notes',
    'card' => [
        'number' => 'n° :number',
        'private' => 'Visible de vous seul',
        'archived' => 'Archivée',
        'edited' => 'modifiée',
    ],
    'list' => [
        'on_task' => 'Note sur la tâche',
        'on_ticket' => 'Note sur le ticket',
        'hide_archived' => 'Masquer les archivées',
        'show_archived' => 'Voir l’archivée|Voir les :count archivées',
        'on_message' => 'Sur le message de :author du :date',
        'task' => 'Sur la tâche',
        'ticket' => 'Sur le ticket',
        'archived' => 'archivée',
        'private' => 'visible de vous seul',
        'images' => ':count image|:count images',
        'empty' => 'Aucune note.',
        'empty_yet' => 'Aucune note pour l’instant.',
    ],
    'chips' => [
        'archived' => ':count note archivée|:count notes archivées',
    ],
    'board' => [
        'on_message' => 'Notes sur ce message',
        'on_task' => 'Notes sur la tâche',
        'on_ticket' => 'Notes sur le ticket',
        'empty' => 'Pas encore de note.',
    ],
];
