<?php

declare(strict_types=1);

use App\Support\CommaSeparated;

return [
    /*
     * The API is only for the app, so only the app's own origins may call it from a browser: those in CORS_ALLOWED_ORIGINS,
     * or when that is not set, the same ones Clerk's tokens are accepted for (CLERK_AUTHORIZED_PARTIES). Nothing set means
     * no origin at all is allowed, never every origin. In production that is https://binnight.app.
     */
    'allowed_origins' => CommaSeparated::list(\env('CORS_ALLOWED_ORIGINS', \env('CLERK_AUTHORIZED_PARTIES'))),

    'allowed_origins_patterns' => [],

    // What the app sends: a bearer token, and JSON.
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],

    'allowed_methods' => ['DELETE', 'GET', 'PATCH', 'POST'],

    // A browser hides every response header but a few unless it is listed here, and the app reads how long to wait from this.
    'exposed_headers' => ['Retry-After'],

    // How long a browser may remember a preflight answer, in seconds.
    'max_age' => 3600,

    'paths' => ['api/*'],

    // Requests are authenticated by a bearer token, not by cookies.
    'supports_credentials' => false,
];
