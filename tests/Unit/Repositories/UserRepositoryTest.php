<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\UserRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testGetUserFromExternalId(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        self::assertEquals('1', new UserRepository()->getUserFromExternalId('user_111')->getKey());
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id');
        });
    }
}
