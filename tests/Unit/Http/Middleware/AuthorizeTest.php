<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Http\Middleware\Authorize;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\TestCase;

final class AuthorizeTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent();

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
            $table->softDeletes();
        });
    }

    public static function getBadHouseInputs(): iterable
    {
        yield 'Unknown house' => [['house_id' => '11111111-1111-1111-1111-111111111111']];

        yield 'Not a string' => [['house_id' => [1]]];

        yield 'Not a uuid' => [['house_id' => 'not-a-uuid']];

        yield 'Not provided' => [[]];
    }

    public static function getModelValues(): iterable
    {
        yield 'Bin' => ['bin', Bin::class];
        yield 'House' => ['house', House::class];
        yield 'Notification' => ['notification', Notification::class];
    }

    #[DataProvider('getModelValues')]
    public function testHandleMapsModels(string $modelName, string $className): void
    {
        $user = new User()->setAttribute('id', '1');

        $gate = $this->createMock(Gate::class);
        $gate->expects($this->once())->method('forUser')->willReturn($gate);
        $gate->expects($this->once())->method('authorize')->with('create', [$className]);

        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('getUserFromExternalId')->willReturn($user);

        $request = Request::create('/')->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));

        $models = [$modelName];
        new Authorize($gate, $repository)->handle(
            $request,
            static fn (): Response => new Response('ok', 200),
            'create',
            ...$models,
        );
    }

    public function testHandleMatchesHouseId(): void
    {
        $houseId = '00000000-0000-0000-0000-000000000002';
        DB::table('houses')->insert([['id' => '00000000-0000-0000-0000-000000000000'], ['id' => $houseId]]);

        $arguments = $this->authorizeWithInput(['house_id' => $houseId]);

        self::assertSame(Bin::class, $arguments[0]);
        self::assertInstanceOf(House::class, $arguments[1]);
        self::assertSame($houseId, $arguments[1]->getKey());
    }

    public function testHandlePassesTheTypeThroughAsItIs(): void
    {
        DB::table('houses')->insert(['id' => '00000000-0000-0000-0000-000000000000']);

        $arguments = $this->authorizeWithInput(
            ['house_id' => '00000000-0000-0000-0000-000000000000', 'type' => NotificationType::SMS],
            ['notification', 'house_id', 'type'],
        );

        self::assertSame(Notification::class, $arguments[0]);
        self::assertInstanceOf(House::class, $arguments[1]);
        self::assertSame(NotificationType::SMS, $arguments[2]);
    }

    #[DataProvider('getBadHouseInputs')]
    public function testHandleSendsNullWithBadHouseInput(array $input): void
    {
        DB::table('houses')->insert(['id' => '00000000-0000-0000-0000-000000000000']);

        self::assertSame([Bin::class, null], $this->authorizeWithInput($input));
    }

    /** @param list<string> $models */
    private function authorizeWithInput(array $input, array $models = ['bin', 'house_id']): array
    {
        $user = new User()->setAttribute('id', '1');
        $captured = [];

        $gate = $this->createMock(Gate::class);
        $gate->method('forUser')->willReturn($gate);
        $gate->expects($this->once())->method('authorize')->willReturnCallback(
            static function (string $ability, array $arguments) use (&$captured): AccessResponse {
                $captured = $arguments;

                return AccessResponse::allow();
            },
        );

        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('getUserFromExternalId')->willReturn($user);

        $request = Request::create('/', 'POST', $input)->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));

        new Authorize($gate, $repository)->handle(
            $request,
            static fn (): Response => new Response('ok', 200),
            'create',
            ...$models,
        );

        return $captured;
    }
}
