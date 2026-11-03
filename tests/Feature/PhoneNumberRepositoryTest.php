<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\AuthenticatedUser;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Repositories\PhoneNumberRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PhoneNumberRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function testASecondRequestThatLosesTheRaceGetsTheRowThatWon(): void
    {
        $user = User::factory()->create(['external_id' => 'user_111']);
        $winner = (string) Str::uuid();

        // Between looking for the number and saving it, another request saves it.
        PhoneNumber::creating(static function () use ($user, $winner): void {
            PhoneNumber::unsetEventDispatcher();
            DB::table('phone_numbers')->insert([
                'id' => $winner,
                'user_id' => $user->getKey(),
                'number' => '+61400000000',
                'status' => 'pending',
                'created_at' => '1985-10-26 01:21:00',
                'updated_at' => '1985-10-26 01:21:00',
            ]);
        });

        $found = new PhoneNumberRepository()->findOrCreate(new AuthenticatedUser(['sub' => 'user_111']), '+61400000000');

        self::assertSame($winner, $found->getKey());
        self::assertSame(1, DB::table('phone_numbers')->count());
    }

    public function testPruneDeletesOnlyOldUnverifiedNumbers(): void
    {
        $user = User::factory()->create();
        $row = static fn (string $number, string $status, string $createdAt): array => [
            'id' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'number' => $number,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        DB::table('phone_numbers')->insert([
            $row('+61400000001', 'pending', '1985-10-25 01:00:00'),
            $row('+61400000002', 'pending', '1985-10-26 01:00:00'),
            $row('+61400000003', 'verified', '1985-10-20 01:00:00'),
        ]);

        $deleted = new PhoneNumberRepository()->pruneUnverified(new DateTimeImmutable('1985-10-25 12:00:00'));

        self::assertSame(1, $deleted);
        self::assertEqualsCanonicalizing(['+61400000002', '+61400000003'], DB::table('phone_numbers')->pluck('number')->all());
    }
}
