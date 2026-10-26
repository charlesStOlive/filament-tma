<?php

return [
    'tasks' => [
        'heading' => 'Tasks',
        'create' => 'New task',
    ],
    'model' => [
        'singular' => 'project',
        'plural' => 'projects',
    ],
    'fields' => [
        'instance' => 'Target application',
        'instance_help' => 'All the tasks of the project go to this application.',
        'estimate_min' => 'Estimate: at least',
        'estimate_help' => 'In days (one day: :day); 0.5 for half a day.',
        'estimate_max' => 'at most',
        'participants' => 'Participants',
        'participants_help' => 'In addition to the recipients of its tasks, who take part automatically.',
        'other_participants' => 'Other participants',
    ],
    'help' => [
        'estimate' => 'The estimate',
        'time' => 'The time of a project',
    ],
    'errors' => [
        'estimate_order' => 'The top of the estimate cannot be below the bottom.',
    ],
    'notifications' => [
        'saved' => 'Project saved',
        'resent' => 'The project is sent again, with :n published task.|The project is sent again, with its :n published tasks.',
        'resent_empty' => 'The project is sent again (it has no published task).',
    ],
    'columns' => [
        'progress' => ':done / :total completed',
        'no_estimate' => 'No estimate',
        'time_help' => 'The sum of the time of its tasks.',
        'no_task_in_progress' => 'No task in progress',
        'tasks_in_progress' => ':n task in progress|:n tasks in progress',
    ],
];
