<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Bins;
use App\Http\Controllers\Api\Houses;
use App\Http\Controllers\Api\Notifications;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (Registrar $route): void {
    $route->group(['middleware' => 'jwt.auth'], static function (Registrar $route): void {
        $route->get('bins', Bins\ListController::class);

        $route->get('houses', Houses\ListController::class);
        $route->post('houses', Houses\CreateController::class)->middleware('can:create,house');

        $route->get('notifications', Notifications\ListController::class);
    });
});
