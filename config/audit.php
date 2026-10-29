<?php

return [

    /*
    | Days to keep audit rows. Null keeps them forever.
    */
    'retention_days' => env('AUDIT_RETENTION_DAYS'),

    /*
    | Page edits by the same user within this many minutes collapse into one entry.
    */
    'page_update_window_minutes' => 10,

    'versions' => [
        // Minimum minutes between automatic snapshots of a page.
        'interval_minutes' => 5,

        // Versions kept per page; the oldest are pruned.
        'max_per_page' => 100,
    ],

];
