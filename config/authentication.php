<?php

declare(strict_types=1);

return [
    'clerk' => [
        'authorized_parties' => \explode(',', \env('CLERK_AUTHORIZED_PARTIES', '')),

        'jwks_url' => \env('CLERK_JWKS_URL'),
    ],
];
