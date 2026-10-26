<?php

return [
    'task' => [
        'cancelled' => [
            'label' => 'Cancelled',
            'description' => 'Abandoned, with its history.',
        ],
        'draft' => [
            'label' => 'Draft',
            'description' => 'Visible to the Support only: not yet published to the client.',
        ],
        'done' => [
            'label' => 'Completed',
            'description' => 'Finished: it can be fully billed.',
        ],
        'in_progress' => [
            'description' => 'Made available to the client and synchronised.',
        ],
    ],
    'ticket' => [
        'pending_validation' => [
            'label' => 'Resolved',
            'description' => 'The work is done: the requester confirms, or sends the request back.',
        ],
        'submitted' => [
            'label' => 'New',
            'description' => 'Sent to the Support: waiting for its first answer.',
        ],
        'paused' => [
            'label' => 'Paused',
            'description' => 'Suspended until a planned date, then resumed.',
        ],
        'awaiting_requester' => [
            'label' => 'Waiting for the requester',
            'description' => 'The requester must provide details.',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'description' => 'Abandoned or refused, with its reason.',
        ],
        'in_progress' => [
            'description' => 'The request is being handled.',
        ],
        'draft' => [
            'description' => 'Being prepared: visible to its author and its application only.',
        ],
        'closed' => [
            'label' => 'Closed',
            'description' => 'Finished. This is the state that opens full billing.',
        ],
    ],
];
