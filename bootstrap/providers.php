<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\FactoryServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    FactoryServiceProvider::class,
    RepositoryServiceProvider::class,
];
