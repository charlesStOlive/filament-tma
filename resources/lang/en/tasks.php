<?php

return [
    'delete' => [
        'heading' => 'Delete task :number?',
        'submit' => 'Delete permanently',
        'done' => 'Task :number deleted',
        'bulk_heading' => 'Delete the selected task?|Delete the :count selected tasks?',
        'bulk_done' => ':count task deleted|:count tasks deleted',
        'messages' => ':count message|:count messages',
        'notes' => ':count note|:count notes',
        'time' => ':time of logged time',
        'nothing_one' => 'It has no message, note or logged time yet.',
        'nothing_many' => 'They have no message, note or logged time yet.',
        'goes_with_one' => 'Deleted along with it: :what.',
        'goes_with_many' => 'Deleted along with them: :what.',
        'published_one' => 'It is published: it also disappears at :client.',
        'published_many' => 'One is published: it also disappears at :client.|:count are published: they also disappear at :client.',
        'warning' => 'Tickets opened from a task remain, without a link. Deletion cannot be undone.',
        'and' => 'and',
    ],
    'errors' => [
        'recipient_required' => 'Choose at least one main recipient at the client, “The whole project team” or “Everyone”.',
        'due_before_start' => 'The due date cannot be before the start.',
    ],
    'fields' => [
        'new_project' => 'New project',
        'starts_on_help' => 'Empty: the day of creation.',
        'due_on_help' => 'Optional: a target date, not a fixed end.',
        'publish_at_help' => 'Empty: the task goes to the client as soon as it is saved. With a date, it stays a draft, invisible to the client, and is published at the given time.',
        'project_estimate' => 'Project estimate',
        'project_time' => 'Project time',
        'schedule' => 'Schedule',
        'completed_at' => 'Completed on',
        'publish_at' => 'Planned publication',
        'published_at' => 'Published on',
    ],
    'help' => [
        'dates' => 'Start and due date',
        'publish' => 'Publishing a task',
    ],
    'publication' => [
        'published' => 'Published at :client',
        'still_draft' => 'Still a draft: you cannot publish it.',
    ],
    'model' => [
        'title' => 'Task',
        'singular' => 'project task',
        'plural' => 'project tasks',
    ],
    'actions' => [
        'create_ticket' => 'Create a ticket',
        'create_ticket_heading' => 'Create a ticket at the Support',
    ],
    'schedule' => [
        'publication' => 'Publication on :date at :time',
        'on_date' => 'on :date',
        'overdue' => 'Overdue: due on :date',
        'due' => 'Due on :date',
    ],
];
