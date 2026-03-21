<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Model;
use App\Models\User;
use Tests\Unit\TestCase;
use TypeError;

final class ModelTest extends TestCase
{
    public function testGetUserReturnsLoadedRelation(): void
    {
        $user = new User()->setAttribute('email', 'email@example.com');
        $model = new class() extends Model {}->setRelation('user', $user);

        $result = $model->user;

        self::assertSame($user, $result);
        self::assertSame('email@example.com', $result->getAttribute('email'));
    }

    public function testGetUserPrefersRelationOverRawAttribute(): void
    {
        $user = new User()->setAttribute('id', 1);
        $user2 = new User()->setAttribute('id', 2);
        $model = new class() extends Model {}->setAttribute('user', $user2)->setRelation('user', $user);

        self::assertSame($user->getKey(), $model->user->getKey());
    }

    public function testGetUserThrowsWhenMissing(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('App\Models\Model::$user::get(): Return value must be of type App\Models\User, null returned');

        new class() extends Model {}->setRelation('user', null)->user;
    }

    public function testGetUserThrowsWhenWrongType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('App\Models\Model::$user::get(): Return value must be of type App\Models\User, stdClass returned');

        new class() extends Model {}->setRelation('user', new \stdClass())->user;
    }

    public function testUserRelationship(): void
    {
        $this->bootEloquent();
        $relation = new class() extends Model {}->user();

        self::assertInstanceOf(User::class, $relation->getRelated());
        self::assertSame('user_id', $relation->getForeignKeyName());
        self::assertSame('id', $relation->getOwnerKeyName());
    }
}
