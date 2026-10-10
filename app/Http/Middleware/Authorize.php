<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Closure;
use Illuminate\Auth\Middleware\Authorize as Middleware;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Str;

final class Authorize extends Middleware
{
    private array $classMap = [
        'bin' => Models\Bin::class,
        'house' => Models\House::class,
    ];

    private array $inputMap = [
        'house_id' => Models\House::class,
    ];

    public function __construct(Gate $gate, private readonly UserRepositoryInterface $userRepository)
    {
        parent::__construct($gate);
    }

    public function handle($request, Closure $next, $ability, ...$models)
    {
        $user = $this->userRepository->getUserFromExternalId($request->user()->id);
        $this->gate = $this->gate->forUser($user);

        $models[0] = $this->classMap[$models[0]];

        return parent::handle($request, $next, $ability, ...$models);
    }

    protected function getModel($request, $model)
    {
        if (isset($this->inputMap[$model])) {
            $id = $request->input($model);

            return \is_string($id) && Str::isUuid($id)
                ? $this->inputMap[$model]::query()->find($id)
                : null;
        }

        return parent::getModel($request, $model);
    }
}
