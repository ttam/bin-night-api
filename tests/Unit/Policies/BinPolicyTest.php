<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Bin;
use App\Models\House;
use App\Models\User;
use App\Policies\BinPolicy;
use PHPUnit\Framework\TestCase;

final class BinPolicyTest extends TestCase
{
    public function testCreateFailsWithOtherUsersHouse(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy()->create($user, $house);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testCreateFailsWithUnknownHouse(): void
    {
        $response = new BinPolicy()->create(new User()->setAttribute('id', '1'), null);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testCreatePassesWithUserOwnedHouse(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy()->create($user, $house)->allowed());
    }

    public function testUpdateFailsWithOtherBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '2');
        $user = new User()->setAttribute('id', '1');

        $response = new BinPolicy()->update($user, $bin);

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testUpdatePassesWithUserOwnedBin(): void
    {
        $bin = new Bin()->setAttribute('user_id', '1');
        $user = new User()->setAttribute('id', '1');

        self::assertTrue(new BinPolicy()->update($user, $bin)->allowed());
    }
}
