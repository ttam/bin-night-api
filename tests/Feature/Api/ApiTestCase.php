<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationType;
use App\Models\User;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests go through the whole HTTP stack, signed in as `user_111` through the development user bypass. Plan
 * limits are replaced, so these tests do not depend on the real numbers.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        \config(['authentication.clerk.development_user' => 'user_111']);

        $this->userId = $this->createUser('user_111');
    }

    protected function createBin(string $houseId, string $name, string $createdAt, ?string $userId = null): string
    {
        $id = (string) Str::uuid();

        DB::table('bins')->insert([
            'id' => $id,
            'user_id' => $userId ?? $this->userId,
            'house_id' => $houseId,
            'name' => $name,
            'cycle' => 'weekly',
            'start_date' => '1985-10-26',
            'lid_color' => 'ffffff',
            'bin_color' => '000000',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    protected function createHouse(string $name, string $createdAt, ?string $userId = null): string
    {
        $id = (string) Str::uuid();

        DB::table('houses')->insert([
            'id' => $id,
            'user_id' => $userId ?? $this->userId,
            'name' => $name,
            'bin_night' => 'monday',
            'timezone' => 'America/Los_Angeles',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    /** The user's number, made if they do not have it yet. */
    protected function createPhoneNumber(string $number, string $status = 'verified', ?string $userId = null): string
    {
        $userId ??= $this->userId;
        $existing = DB::table('phone_numbers')->where('user_id', $userId)->where('number', $number)->value('id');

        if (\is_string($existing)) {
            return $existing;
        }

        $id = (string) Str::uuid();

        DB::table('phone_numbers')->insert([
            'id' => $id,
            'user_id' => $userId,
            'number' => $number,
            'status' => $status,
            'verified_at' => $status === 'verified' ? '1985-10-26 01:21:00' : null,
            'created_at' => '1985-10-26 01:21:00',
            'updated_at' => '1985-10-26 01:21:00',
        ]);

        return $id;
    }

    /** `$target` is the number the notification sends to; the user gets a verified phone number for it. */
    protected function createNotification(string $houseId, string $target, string $createdAt, ?string $userId = null, ?string $name = null): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'user_id' => $userId ?? $this->userId,
            'house_id' => $houseId,
            'name' => $name ?? $target,
            'type' => 'sms',
            'phone_number_id' => $this->createPhoneNumber($target, userId: $userId),
            'lead_days' => 1,
            'send_time' => '18:30:00',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    protected function createUser(string $externalId): string
    {
        return User::factory()->create(['external_id' => $externalId])->getKey();
    }

    protected function useLimits(int $houses = 1, int $binsPerHouse = 2, int $notificationsPerHouse = 2): void
    {
        $plans = $this->createStub(PlanRepositoryInterface::class);
        $plans->method('getLimits')->willReturn(
            new PlanLimits($houses, $binsPerHouse, $notificationsPerHouse, [NotificationType::SMS]),
        );

        $this->app->instance(PlanRepositoryInterface::class, $plans);
    }
}
