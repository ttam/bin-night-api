<?php

declare(strict_types=1);

return [
    /*
     * The numbers we send to, as a pattern each. Adding a country is adding a pattern here. End each with `$/D`: without
     * the D, `$` also matches before a final newline, and "+61400000000\n" would be a number.
     */
    'mobile_patterns' => [
        'AU' => '/^\+614\d{8}$/D',
    ],

    /*
     * Codes we will ask the provider to send. The provider limits guesses itself; these stop us being used to text
     * people. A number that is already verified never counts, because nothing is sent.
     */
    'code_limits' => [
        'per_ip_per_hour' => 20,
        'per_number_per_hour' => 3,
        'per_user_per_hour' => 5,
    ],

    /*
     * How long a number may sit unverified before the scheduled prune deletes it.
     */
    'prune_after_hours' => 24,
];
