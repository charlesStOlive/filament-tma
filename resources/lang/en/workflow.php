<?php

return [
    'task' => [
        'reopen' => [
            'label' => 'Reopen',
            'description' => 'The task goes back to In progress, at the client too.',
            'reason' => 'Why reopen it? (optional)',
        ],
        'complete' => [
            'label' => 'Complete',
            'message' => 'The final word (optional)',
            'message_help' => 'What was delivered, what remains to be done: it opens the closing block of the history.',
        ],
        'publish' => [
            'description' => 'The task becomes visible in the client’s application.',
            'recipients' => 'Main recipients at the client',
            'recipients_help' => 'Notified of the publication, then of everything that happens to the task.',
        ],
        'cancel' => [
            'label' => 'Cancel',
        ],
    ],
    'reason' => 'Reason',
    'help' => [
        'reason' => 'When is a reason needed?',
    ],
    'errors' => [
        'ticket_move' => 'This step is not possible for this ticket.',
        'support_prepares' => 'Only the Support prepares a ticket for a client.',
        'cannot_create_ticket' => 'You cannot create a ticket.',
        'prepare_for_client' => 'At the Support, a ticket is prepared for a client.',
        'task_move' => 'This step is not possible for this task.',
        'tasks_at_support' => 'Project tasks are created at the Support.',
        'cannot_create_task' => 'You cannot create a task.',
        'cannot_delete_task' => 'You cannot delete task :number.',
        'task_billed' => 'Time on task :number is billed: it cannot be deleted.',
    ],
    'ticket' => [
        'force_return' => [
            'label' => 'Force the return',
            'description' => 'Out of turn: control goes back to the Support without waiting for the requester.',
            'help' => 'When to force the return?',
        ],
        'reopen' => [
            'label' => 'Back to the Support',
            'message' => 'What is wrong',
            'help' => 'Confirm or send back?',
        ],
        'take_over' => [
            'label' => 'Take over',
            'label_named' => 'Take over (instead of :name)',
            'description' => 'You become the requester: the Support will wait for you, for this answer and what follows.',
        ],
        'resume' => [
            'label' => 'Resume',
        ],
        'cancel' => [
            'label' => 'Withdraw the request',
        ],
        'answer' => [
            'message' => 'Answer',
            'help' => 'Why answer here?',
        ],
        'force_close' => [
            'label' => 'Force closing',
            'description' => 'Out of turn: the ticket is closed right away, whoever it is waiting for.',
            'help' => 'When to force closing?',
        ],
        'submit_for_validation' => [
            'label' => 'Mark as resolved',
            'help' => 'And then?',
        ],
        'refuse' => [
            'label' => 'Refuse',
        ],
        'auto_close' => [
            'label' => 'Closed automatically (no answer)',
            'message' => 'Closed automatically: no activity for :days days.',
        ],
        'validate' => [
            'label' => 'Confirm and close',
            'message' => 'A word for the Support (optional)',
        ],
        'pause' => [
            'label' => 'Pause',
            'help' => 'What happens when it resumes?',
            'help_title' => 'The pause and its resumption',
        ],
        'publish' => [
            'description' => 'The ticket becomes visible in the client’s application.',
        ],
        'request_answer' => [
            'label' => 'Ask for details',
            'message' => 'Question',
            'help' => 'What happens to the ticket?',
        ],
        'auto_resume' => [
            'label' => 'Resumed on the planned date',
        ],
        'transmit' => [
            'label' => 'Send to the Support',
            'description' => 'The Support takes over: it will have the final say on this ticket.',
            'contact_help' => 'The Support person who will follow your request.',
        ],
    ],
    'move_heading' => [
        'ticket' => 'Ticket :number: :state',
        'task' => 'Task :number: :state',
    ],
];
