<?php

return [
    'allowance_period' => [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
    ],
    'notice_event' => [
        'ticket_waiting' => 'A ticket is waiting for you',
        'new_note' => 'New note',
        'project_task' => 'Project task',
        'recipient' => 'You are a recipient',
        'stale' => 'Reminder: nothing is moving',
        'closing_warning' => 'Closing warning',
        'auto_close' => 'Automatic closing',
    ],
    'origin' => [
        'interface' => 'Interface',
        'api' => 'API',
        'command' => 'Command',
        'scheduler' => 'Scheduler',
    ],
    'priority' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
    'sync_status' => [
        'local' => 'Not shared',
        'pending' => 'Waiting for synchronisation',
        'synced' => 'Synchronised',
        'failed' => 'Synchronisation failed',
    ],
    'notice_mode' => [
        'immediate' => 'Right away',
        'digest' => 'In the digest',
        'none' => 'Not sent (no digest)',
    ],
    'side' => [
        'master' => 'Support team',
    ],
    'ticket_opening' => [
        'request' => [
            'label' => 'Request',
            'description' => 'A contact makes their request.',
        ],
        'on_behalf' => [
            'label' => 'On behalf of a client',
            'description' => 'Their request, received another way (an e-mail…): it is up to you to answer it.',
        ],
        'addressed' => [
            'label' => 'I write to the client',
            'description' => 'An idea, a question, a proposal: you are waiting for their answer.',
        ],
        'notice' => [
            'label' => 'I inform the client',
            'description' => 'For information, no answer expected: the ticket is closed as soon as it is published.',
        ],
        'addressed_to' => 'Addressed to',
        'support_message' => 'Message from the Support',
        'information' => 'Information',
    ],
    'entry_type' => [
        'note' => 'Note',
    ],
    'recipient_role' => [
        'main' => 'Main',
        'secondary' => 'Secondary',
    ],
];
