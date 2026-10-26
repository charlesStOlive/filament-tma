<?php

return [
    'model' => [
        'singular' => 'allowance',
        'plural' => 'allowances',
    ],
    'fields' => [
        'instance_help' => 'With no application chosen, the allowance is shared between all the client’s applications.',
        'all_applications' => 'All the client’s applications',
        'contract' => 'Contract',
        'period' => 'Frequency',
        'volume' => 'Volume per period',
        'unit' => 'In',
    ],
    'help' => [
        'scope' => 'Client or application?',
        'period' => 'Monthly or quarterly?',
        'volume' => 'What is counted',
    ],
    'columns' => [
        'allocated' => 'Allocated',
        'consumed' => 'Consumed (current period)',
        'remaining' => 'Remaining',
        'period_summary' => 'Period :p: :c consumed out of :a.',
    ],
    'actions' => [
        'reset_thresholds' => 'Reset the thresholds',
        'reset_thresholds_description' => 'The thresholds of the current period can fire again. See the help page “The thresholds of an allowance”.',
        'thresholds_reset' => 'Thresholds reset',
    ],
];
