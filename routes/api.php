<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Bins;
use App\Http\Controllers\Api\Houses;
use App\Http\Controllers\Api\Me;
use App\Http\Controllers\Api\Notifications;
use App\Http\Controllers\Api\PhoneNumbers;
use App\Http\Controllers\Api\Schedule;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (Registrar $route): void {
    $route->group(['middleware' => ['jwt.auth', 'provision-user', 'active-entities']], static function (Registrar $route): void {
        $route->get('bins', Bins\ListController::class);
        $route->post('bins', Bins\CreateController::class)->middleware('can:create,bin,house_id');
        $route->patch('bins/{bin}', Bins\UpdateController::class)->middleware('can:update,bin,bin')->whereUuid('bin');
        $route->delete('bins/{bin}', Bins\DeleteController::class)->middleware('can:delete,bin,bin')->whereUuid('bin');

        $route->get('houses', Houses\ListController::class);
        $route->post('houses', Houses\CreateController::class)->middleware('can:create,house');
        $route->patch('houses/{house}', Houses\UpdateController::class)->middleware('can:update,house,house')->whereUuid('house');
        $route->delete('houses/{house}', Houses\DeleteController::class)->middleware('can:delete,house,house')->whereUuid('house');

        $route->get('me', Me\ShowController::class);

        $route->get('notifications', Notifications\ListController::class);
        $route->post('notifications', Notifications\CreateController::class)->middleware('can:create,notification,house_id,type');
        $route->patch('notifications/{notification}', Notifications\UpdateController::class)->middleware('can:update,notification,notification,type')->whereUuid('notification');
        $route->delete('notifications/{notification}', Notifications\DeleteController::class)->middleware('can:delete,notification,notification')->whereUuid('notification');

        $route->get('phone-numbers', PhoneNumbers\ListController::class);
        $route->post('phone-numbers', PhoneNumbers\CreateController::class);
        $route->post('phone-numbers/{phoneNumber}/verification', PhoneNumbers\ResendController::class)->middleware('can:update,phoneNumber,phoneNumber')->whereUuid('phoneNumber');
        $route->post('phone-numbers/{phoneNumber}/verify', PhoneNumbers\VerifyController::class)->middleware(['throttle:30,1', 'can:update,phoneNumber,phoneNumber'])->whereUuid('phoneNumber');

        $route->get('schedule', Schedule\ShowController::class);
    });
});
