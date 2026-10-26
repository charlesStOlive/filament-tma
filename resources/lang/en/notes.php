<?php

return [
    'number_title' => 'Note No. :number',
    'heading' => [
        'message' => 'The message from :author and its notes',
        'subject' => 'The notes on :subject',
    ],
    'add' => [
        'label' => 'Add a note',
        'heading_message' => 'A note on the message from :author',
        'heading_subject' => 'A note on :subject',
        'description' => 'Everyone on the ticket will read it, unless you keep it to yourself; it does not change its state.',
        'submit' => 'Add the note',
        'done' => ':note added',
    ],
    'edit' => [
        'heading' => 'Edit note No. :number',
        'add_images' => 'Add images',
        'done' => ':note edited',
    ],
    'archive' => [
        'restore' => 'Bring back',
        'archive' => 'Archive',
        'archived' => ':note archived',
        'restored' => ':note brought back',
    ],
    'delete' => [
        'heading' => 'Delete note No. :number?',
        'description' => 'It disappears, on both sides. Its number is not reused.',
        'done' => ':note deleted',
    ],
    'message' => [
        'edit_heading' => 'Edit your message',
        'edit_description' => 'Possible as long as nobody else has written after it. Everyone will see that it was edited.',
        'edited' => 'Message edited',
    ],
    'fields' => [
        'placeholder' => 'Your note…',
        'private' => 'Visible to me only',
        'private_help' => 'Nobody else will see it. You can show it to everyone later.',
        'images' => 'Images',
    ],
    'help' => 'Notes',
    'card' => [
        'number' => 'No. :number',
        'private' => 'Visible to you only',
        'archived' => 'Archived',
        'edited' => 'edited',
    ],
    'list' => [
        'on_task' => 'Note on the task',
        'on_ticket' => 'Note on the ticket',
        'hide_archived' => 'Hide archived',
        'show_archived' => 'Show the archived one|Show the :count archived',
        'on_message' => 'On the message from :author on :date',
        'task' => 'On the task',
        'ticket' => 'On the ticket',
        'archived' => 'archived',
        'private' => 'visible to you only',
        'images' => ':count image|:count images',
        'empty' => 'No note.',
        'empty_yet' => 'No note yet.',
    ],
    'chips' => [
        'archived' => ':count archived note|:count archived notes',
    ],
    'board' => [
        'on_message' => 'Notes on this message',
        'on_task' => 'Notes on the task',
        'on_ticket' => 'Notes on the ticket',
        'empty' => 'No note yet.',
    ],
];
