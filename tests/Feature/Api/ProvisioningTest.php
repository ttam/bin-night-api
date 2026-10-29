<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\AuthenticatedUser;
use App\Auth\Authenticators\Interfaces\TokenAuthenticatorInterface;
use Illuminate\Support\Facades\DB;

final class ProvisioningTest extends ApiTestCase
{
    public function testAFirstTimeUserCanCreateAHouseStraightAway(): void
    {
        \config(['authentication.clerk.development_user' => 'user_new']);

        $this->postJson('/api/v1/houses', ['bin_night' => 'monday', 'name' => 'Hill Valley', 'timezone' => 'America/Los_Angeles'])
            ->assertCreated()
            ->assertJsonPath('data.active', true);

        $userId = DB::table('users')->where('external_id', 'user_new')->value('id');
        self::assertNotNull($userId);
        self::assertSame($userId, DB::table('houses')->value('user_id'));
    }

    public function testAFirstTimeUserGetsEmptyListsRatherThanAnError(): void
    {
        \config(['authentication.clerk.development_user' => 'user_new']);

        foreach (['houses', 'bins', 'notifications'] as $resource) {
            $this->getJson('/api/v1/' . $resource)->assertOk()->assertExactJson(['data' => []]);
        }

        self::assertSame(1, DB::table('users')->where('external_id', 'user_new')->count());
        self::assertSame('free', DB::table('users')->where('external_id', 'user_new')->value('plan'));
    }

    public function testAnExistingUserIsNotCreatedAgain(): void
    {
        $this->getJson('/api/v1/houses')->assertOk();
        $this->getJson('/api/v1/houses')->assertOk();

        self::assertSame(1, DB::table('users')->where('external_id', 'user_111')->count());
        self::assertSame($this->userId, DB::table('users')->where('external_id', 'user_111')->value('id'));
    }

    public function testARequestWithoutATokenDoesNotCreateAUser(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/houses')->assertUnauthorized();

        self::assertSame(0, DB::table('users')->where('external_id', '!=', 'user_111')->count());
    }

    public function testTheTokensNameAndEmailAreStoredAndKeptUpToDate(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $authenticator = $this->createStub(TokenAuthenticatorInterface::class);
        $authenticator->method('authenticate')->willReturn(
            new AuthenticatedUser(['sub' => 'user_new', 'name' => 'Marty', 'email' => 'marty@example.com']),
            new AuthenticatedUser(['sub' => 'user_new', 'name' => 'Marty McFly', 'email' => 'marty@example.com']),
            new AuthenticatedUser(['sub' => 'user_new']),
        );
        $this->app->instance(TokenAuthenticatorInterface::class, $authenticator);

        $this->withToken('token')->getJson('/api/v1/houses')->assertOk();

        self::assertSame('Marty', DB::table('users')->where('external_id', 'user_new')->value('name'));
        self::assertSame('marty@example.com', DB::table('users')->where('external_id', 'user_new')->value('email'));

        $this->withToken('token')->getJson('/api/v1/houses')->assertOk();

        self::assertSame('Marty McFly', DB::table('users')->where('external_id', 'user_new')->value('name'));

        $this->withToken('token')->getJson('/api/v1/houses')->assertOk();

        self::assertSame('Marty McFly', DB::table('users')->where('external_id', 'user_new')->value('name'));
        self::assertSame(1, DB::table('users')->where('external_id', 'user_new')->count());
    }
}
