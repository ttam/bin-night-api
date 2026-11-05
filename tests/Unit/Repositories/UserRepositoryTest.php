<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\Auth\Exceptions\AccountDeletedException;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Tests\Unit\TestCase;

final class UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    protected function tearDown(): void
    {
        User::flushEventListeners();
        User::unsetEventDispatcher();
    }

    public function testCreateUserFromAuthenticatedUserCreatesTheUserFromTheTokensClaims(): void
    {
        $user = new UserRepository()->createUserFromAuthenticatedUser($this->claims());

        self::assertSame('user_111', DB::table('users')->value('external_id'));
        self::assertSame('Marty McFly', DB::table('users')->value('name'));
        self::assertSame('marty@example.com', DB::table('users')->value('email'));
        self::assertSame(1, DB::table('users')->count());
        self::assertSame($user->getKey(), DB::table('users')->value('id'));
    }

    public function testCreateUserFromAuthenticatedUserCreatesTheUserWithoutProfileClaims(): void
    {
        new UserRepository()->createUserFromAuthenticatedUser(new AuthenticatedUser(['sub' => 'user_111']));

        self::assertSame('user_111', DB::table('users')->value('external_id'));
        self::assertNull(DB::table('users')->value('name'));
        self::assertNull(DB::table('users')->value('email'));
    }

    public function testCreateUserFromAuthenticatedUserFindsTheExistingUserWithoutCreatingAnother(): void
    {
        DB::table('users')->insert([
            ['id' => 'u1', 'external_id' => 'user_111', 'name' => 'Marty McFly', 'email' => 'marty@example.com'],
            ['id' => 'u2', 'external_id' => 'user_222', 'name' => 'Doc Brown', 'email' => 'doc@example.com'],
        ]);

        $user = new UserRepository()->createUserFromAuthenticatedUser($this->claims());

        self::assertSame('u1', $user->getKey());
        self::assertSame(2, DB::table('users')->count());
    }

    public function testCreateUserFromAuthenticatedUserFindsTheUserThatWasCreatedFirstByAnotherRequest(): void
    {
        User::setEventDispatcher(new Dispatcher());
        // Another request inserts the same user just before this one does.
        User::creating(static function (): void {
            DB::table('users')->insert(['id' => 'competitor', 'external_id' => 'user_111', 'name' => 'Marty']);
        });

        $user = new UserRepository()->createUserFromAuthenticatedUser($this->claims());

        self::assertSame('competitor', $user->getKey());
        self::assertSame(1, DB::table('users')->count());
        self::assertSame('Marty McFly', DB::table('users')->value('name'));
    }

    public function testCreateUserFromAuthenticatedUserKeepsWhatItHasWhenTheTokenSaysNothing(): void
    {
        DB::table('users')->insert(['id' => 'u1', 'external_id' => 'user_111', 'name' => 'Marty McFly', 'email' => 'marty@example.com']);

        new UserRepository()->createUserFromAuthenticatedUser(new AuthenticatedUser(['sub' => 'user_111', 'name' => '', 'email' => null]));

        self::assertSame('Marty McFly', DB::table('users')->value('name'));
        self::assertSame('marty@example.com', DB::table('users')->value('email'));
    }

    public function testCreateUserFromAuthenticatedUserOnlyWritesWhenSomethingChanged(): void
    {
        DB::table('users')->insert(['id' => 'u1', 'external_id' => 'user_111', 'name' => 'Marty McFly', 'email' => 'marty@example.com', 'updated_at' => '1985-10-26 01:21:00']);

        new UserRepository()->createUserFromAuthenticatedUser($this->claims());

        self::assertSame('1985-10-26 01:21:00', DB::table('users')->value('updated_at'));
    }

    public function testCreateUserFromAuthenticatedUserUpdatesAChangedNameAndEmail(): void
    {
        DB::table('users')->insert(['id' => 'u1', 'external_id' => 'user_111', 'name' => 'Marty', 'email' => 'old@example.com']);

        $user = new UserRepository()->createUserFromAuthenticatedUser($this->claims());

        self::assertSame('Marty McFly', $user->getAttribute('name'));
        self::assertSame('Marty McFly', DB::table('users')->value('name'));
        self::assertSame('marty@example.com', DB::table('users')->value('email'));
    }

    public function testCreateUserFromAuthenticatedUserRefusesADeletedAccountAndMakesNoSecondUser(): void
    {
        DB::table('users')->insert(['id' => '1', 'external_id' => 'user_111', 'deleted_at' => '1985-10-26 01:21:00', 'created_at' => '1985-10-25 01:21:00', 'updated_at' => '1985-10-25 01:21:00']);

        try {
            new UserRepository()->createUserFromAuthenticatedUser($this->claims());
            self::fail('Expected the deleted account to be refused.');
        } catch (AccountDeletedException) {
            self::assertSame(1, DB::table('users')->count());
            self::assertSame('1985-10-26 01:21:00', DB::table('users')->value('deleted_at'));
        }
    }

    public function testGetUserFromExternalId(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'external_id' => 'user_111'],
            ['id' => 2, 'external_id' => 'user_222'],
        ]);

        self::assertEquals('1', new UserRepository()->getUserFromExternalId('user_111')->getKey());
    }

    private function claims(): AuthenticatedUser
    {
        return new AuthenticatedUser(['sub' => 'user_111', 'name' => 'Marty McFly', 'email' => 'marty@example.com']);
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('external_id')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
