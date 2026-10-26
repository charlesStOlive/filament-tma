<?php

return [
    'mine' => 'My notifications',
    'flush' => [
        'label' => 'Send now',
        'heading' => 'Send the pending notifications?',
        'nothing' => 'Nothing is waiting for a grouped send.',
        'description' => ':n notification(s) pending for :r person(s): each one receives their summary now, instead of waiting for the next one.',
        'submit' => 'Send',
        'done' => ':r summary(ies) sent',
        'done_body' => ':n notification(s).',
    ],
    'remind' => [
        'label' => 'Remind',
        'heading' => 'Remind: :who',
        'description' => 'A message marked “Reminder” goes into the history; whoever has control receives an e-mail right away, even if they did not choose the direct e-mail.',
        'message' => 'Your word (optional)',
        'sent' => 'Reminder sent',
        'default_message' => 'This ticket is waiting for your reply.',
    ],
    'inform' => [
        'label' => 'Inform by e-mail',
        'heading' => 'Inform the client by e-mail',
        'description' => 'Your message goes into the task’s history, and its main recipients at the client receive it by e-mail. Without this, a task only reaches them in the bell and the weekly digest.',
        'sent' => 'Message sent',
        'sent_body' => 'The client receives it by e-mail.',
    ],
];
