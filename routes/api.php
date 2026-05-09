<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (Registrar $route): void {
    $route->group(['middleware' => 'jwt.auth'], static function (Registrar $route): void {
        //
    });
});
