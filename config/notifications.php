<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Catch-up window
    |--------------------------------------------------------------------------
    |
    | How many hours back each scheduled run looks for notifications that were missed, for example because the scheduler
    | was down or the server restarting at the time. Anything due in that time that has not been sent is sent late rather
    | than never; the delivery record stops it being sent twice. 0 turns catch-up off. A run given an explicit time only
    | ever handles the notifications due at that moment.
    |
    */
    'catch_up_hours' => \max(0, \intval(\env('NOTIFICATIONS_CATCH_UP_HOURS', 2))),
];
