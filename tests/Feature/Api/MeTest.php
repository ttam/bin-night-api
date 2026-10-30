<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Plan\PlanLimits;
use Illuminate\Support\Facades\DB;

final class MeTest extends ApiTestCase
{
    public function testAFirstTimeUserCanAskStraightAway(): void
    {
        \config(['authentication.clerk.development_user' => 'user_new']);
        $this->useLimits();

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.plan', 'free');

        self::assertSame(1, DB::table('users')->where('external_id', 'user_new')->count());
    }

    public function testAnUnauthenticatedRequestIsRefused(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function testItShowsThePlanAndItsLimits(): void
    {
        $this->useLimits(houses: 1, binsPerHouse: 2, notificationsPerHouse: 3);

        $this->getJson('/api/v1/me')->assertOk()->assertExactJson(['data' => [
            'limits' => [
                'bins_per_house' => 2,
                'houses' => 1,
                'notification_types' => ['sms'],
                'notifications_per_house' => 3,
            ],
            'plan' => 'free',
        ]]);
    }

    public function testItShowsTheUsersOwnPlan(): void
    {
        $this->useLimits();
        DB::table('users')->where('id', $this->userId)->update(['plan' => 'plus']);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.plan', 'plus');
    }

    public function testNoLimitIsNull(): void
    {
        $this->useLimits(houses: PlanLimits::UNLIMITED, binsPerHouse: PlanLimits::UNLIMITED, notificationsPerHouse: PlanLimits::UNLIMITED);

        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.limits.bins_per_house', null)
            ->assertJsonPath('data.limits.houses', null)
            ->assertJsonPath('data.limits.notifications_per_house', null);
    }
}
