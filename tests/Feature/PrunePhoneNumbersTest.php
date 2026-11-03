<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Clock\KnownClock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Clock\ClockInterface;
use Tests\TestCase;

final class PrunePhoneNumbersTest extends TestCase
{
    use RefreshDatabase;

    public function testItDeletesNumbersLeftUnverifiedForLongerThanTheConfiguredHours(): void
    {
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable('1985-10-26 12:00:00')));
        \config(['phone.prune_after_hours' => 24]);
        $user = User::factory()->create();

        PhoneNumber::factory()->for($user)->create(['created_at' => '1985-10-25 11:00:00']);
        PhoneNumber::factory()->for($user)->create(['created_at' => '1985-10-25 13:00:00']);
        PhoneNumber::factory()->for($user)->verified()->create(['created_at' => '1985-10-01 13:00:00']);

        $this->artisan('bin-night:prune-phone-numbers')->expectsOutput('Deleted 1 unverified phone numbers.')->assertSuccessful();

        self::assertSame(2, PhoneNumber::query()->count());
    }
}
