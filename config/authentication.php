<?php

declare(strict_types=1);

return [
    'clerk' => [
        'authorized_parties' => \App\Support\CommaSeparated::list(\env('CLERK_AUTHORIZED_PARTIES')),

        'development_user' => \env('CLERK_USER_ID'),

        'jwks_url' => \env('CLERK_JWKS_URL'),
    ],
];
