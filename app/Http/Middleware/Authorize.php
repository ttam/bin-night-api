<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Models;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Closure;
use Illuminate\Auth\Middleware\Authorize as Middleware;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Str;

final class Authorize extends Middleware
{
    /** @var array<string, class-string<Models\Model>> */
    private array $classMap = [
        'bin' => Models\Bin::class,
        'house' => Models\House::class,
        'notification' => Models\Notification::class,
        'phoneNumber' => Models\PhoneNumber::class,
    ];

    /** @var array<string, class-string<Models\Model>> */
    private array $inputMap = [
        'house_id' => Models\House::class,
    ];

    /**
     * Request input that is handed to the policy as it is, rather than looked up as a model.
     *
     * @var list<string>
     */
    private array $valueInputs = ['type'];

    public function __construct(Gate $gate, private readonly UserRepositoryInterface $userRepository)
    {
        parent::__construct($gate);
    }

    /**
     * @param \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response $next
     * @param mixed ...$models
     */
    public function handle($request, Closure $next, $ability, ...$models)
    {
        $user = $this->userRepository->getUserFromExternalId(AuthenticatedUser::fromRequest($request)->id);
        $this->gate = $this->gate->forUser($user);

        /** @var string $name */
        $name = $models[0];
        $models[0] = $this->classMap[$name];

        return parent::handle($request, $next, $ability, ...$models); // @phpstan-ignore argument.type
    }

    /** @return \Illuminate\Database\Eloquent\Model|string|null */
    protected function getModel($request, $model)
    {
        if (\in_array($model, $this->valueInputs, true)) {
            /** @var string|null */
            return $request->input($model);
        }

        if (isset($this->inputMap[$model])) {
            $id = $request->input($model);

            return \is_string($id) && Str::isUuid($id)
                ? $this->inputMap[$model]::query()->find($id)
                : null;
        }

        return parent::getModel($request, $model);
    }
}
