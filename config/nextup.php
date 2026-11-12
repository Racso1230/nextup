<?php

return [

    'alerts' => [
        // Where failed-job alert emails go. Empty disables the emails (failures are still logged).
        'mail_to' => env('ALERT_MAIL_TO'),

        // At most one email per job class in this many minutes.
        'throttle_minutes' => 15,
    ],

];
