<?php

return [
    'model' => [
        'singular' => 'log entry',
        'plural' => 'audit log',
    ],
    'columns' => [
        'action' => 'Action',
        'subject' => 'Subject',
        'causer' => 'By',
        'origin' => 'Origin',
        'change' => 'Change',
    ],
];
