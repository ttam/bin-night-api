<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\FactoryServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    DatabaseServiceProvider::class,
    FactoryServiceProvider::class,
    RepositoryServiceProvider::class,
];
