<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Models\Bin;
use App\Models\House;
use App\Models\Notification;
use App\Repositories\ActiveEntityRepository;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\Unit\TestCase;

final class ActiveEntityRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public function testItemsCreatedAtTheSameTimeAreOrderedById(): void
    {
        $this->insertUser('u1');
        $this->insertHouse('h1', 'u1', '1885-09-02 00:00:00');
        $this->insertBin('b-c', 'u1', 'h1', '1985-10-26 00:00:00');
        $this->insertBin('b-a', 'u1', 'h1', '1985-10-26 00:00:00');
        $this->insertBin('b-b', 'u1', 'h1', '1985-10-26 00:00:00');

        $activeEntities = $this->repository()->getActiveEntitiesForUser($this->user('u1'));

        self::assertTrue($activeEntities->isBinActive($this->bin('b-a')));
        self::assertTrue($activeEntities->isBinActive($this->bin('b-b')));
        self::assertFalse($activeEntities->isBinActive($this->bin('b-c')));
    }

    public function testNotificationTypeNotInThePlanIsInactive(): void
    {
        $this->insertUser('u1');
        $this->insertHouse('h1', 'u1', '1885-09-02 00:00:00');
        $this->insertNotification('n-sms', 'u1', 'h1', 'sms', '1955-11-12 00:00:00');

        $activeEntities = $this->repository(new PlanLimits(1, 2, 1, []))->getActiveEntitiesForUser($this->user('u1'));

        self::assertFalse($activeEntities->isNotificationActive($this->notification('n-sms')));
    }

    public function testOldestItemsAreActiveUpToEachLimit(): void
    {
        $this->insertUser('u1');
        // Inserted newest first, so the order has to come from created_at rather than from the table.
        $this->insertHouse('h-new', 'u1', '2015-10-21 00:00:00');
        $this->insertHouse('h-old', 'u1', '1955-11-12 00:00:00');
        $this->insertBin('b-new', 'u1', 'h-old', '1985-10-26 00:00:00');
        $this->insertBin('b-old', 'u1', 'h-old', '1885-09-02 00:00:00');
        $this->insertBin('b-mid', 'u1', 'h-old', '1955-11-12 00:00:00');
        $this->insertBin('b-other-house', 'u1', 'h-new', '1885-09-02 00:00:00');
        $this->insertNotification('n-new', 'u1', 'h-old', 'sms', '1985-10-26 00:00:00');
        $this->insertNotification('n-old', 'u1', 'h-old', 'sms', '1955-11-12 00:00:00');
        $this->insertNotification('n-other-house', 'u1', 'h-new', 'sms', '1885-09-02 00:00:00');

        $activeEntities = $this->repository(new PlanLimits(1, 2, 1, [NotificationType::SMS]))
            ->getActiveEntitiesForUser($this->user('u1'));

        self::assertTrue($activeEntities->isHouseActive($this->house('h-old')));
        self::assertFalse($activeEntities->isHouseActive($this->house('h-new')));

        self::assertTrue($activeEntities->isBinActive($this->bin('b-old')));
        self::assertTrue($activeEntities->isBinActive($this->bin('b-mid')));
        self::assertFalse($activeEntities->isBinActive($this->bin('b-new')));
        self::assertFalse($activeEntities->isBinActive($this->bin('b-other-house')));

        self::assertTrue($activeEntities->isNotificationActive($this->notification('n-old')));
        self::assertFalse($activeEntities->isNotificationActive($this->notification('n-new')));
        self::assertFalse($activeEntities->isNotificationActive($this->notification('n-other-house')));
    }

    public function testOnlyTheAuthenticatedUsersItemsAreConsidered(): void
    {
        $this->insertUser('u1');
        $this->insertUser('u2');
        // u1's house is older, so it would take the only slot if it were counted for u2.
        $this->insertHouse('h1', 'u1', '1885-09-02 00:00:00');
        $this->insertHouse('h2', 'u2', '1985-10-26 00:00:00');

        $activeEntities = $this->repository()->getActiveEntitiesForUser($this->user('u2'));

        self::assertTrue($activeEntities->isHouseActive($this->house('h2')));
        self::assertFalse($activeEntities->isHouseActive($this->house('h1')));
    }

    public function testUnlimitedLimitsMakeEverythingActive(): void
    {
        $this->insertUser('u1');
        $this->insertHouse('h1', 'u1', '1885-09-02 00:00:00');
        $this->insertHouse('h2', 'u1', '1985-10-26 00:00:00');
        $this->insertBin('b1', 'u1', 'h1', '1885-09-02 00:00:00');
        $this->insertBin('b2', 'u1', 'h1', '1955-11-12 00:00:00');
        $this->insertBin('b3', 'u1', 'h1', '1985-10-26 00:00:00');
        $this->insertNotification('n1', 'u1', 'h1', 'sms', '1885-09-02 00:00:00');
        $this->insertNotification('n2', 'u1', 'h1', 'sms', '1985-10-26 00:00:00');

        $unlimited = new PlanLimits(
            houses: PlanLimits::UNLIMITED,
            binsPerHouse: PlanLimits::UNLIMITED,
            notificationsPerHouse: PlanLimits::UNLIMITED,
            notificationTypes: [NotificationType::SMS],
        );

        $activeEntities = $this->repository($unlimited)->getActiveEntitiesForUser($this->user('u1'));

        self::assertTrue($activeEntities->isHouseActive($this->house('h2')));
        self::assertTrue($activeEntities->isBinActive($this->bin('b3')));
        self::assertTrue($activeEntities->isNotificationActive($this->notification('n2')));
    }

    private function bin(string $id): Bin
    {
        return new Bin()->setAttribute('id', $id);
    }

    private function house(string $id): House
    {
        return new House()->setAttribute('id', $id);
    }

    private function insertBin(string $id, string $userId, string $houseId, string $createdAt): void
    {
        DB::table('bins')->insert(['id' => $id, 'user_id' => $userId, 'house_id' => $houseId, 'created_at' => $createdAt]);
    }

    private function insertHouse(string $id, string $userId, string $createdAt): void
    {
        DB::table('houses')->insert(['id' => $id, 'user_id' => $userId, 'created_at' => $createdAt]);
    }

    private function insertNotification(string $id, string $userId, string $houseId, string $type, string $createdAt): void
    {
        DB::table('notifications')->insert([
            'id' => $id,
            'user_id' => $userId,
            'house_id' => $houseId,
            'type' => $type,
            'created_at' => $createdAt,
        ]);
    }

    private function insertUser(string $id): void
    {
        DB::table('users')->insert(['id' => $id, 'plan' => 'free', 'external_id' => 'ext-' . $id]);
    }

    private function migrate(): void
    {
        DB::schema()->create('users', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('plan');
            $table->string('external_id')->nullable();
        });

        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('user_id');
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        DB::schema()->create('bins', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('user_id');
            $table->string('house_id');
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('user_id');
            $table->string('house_id');
            $table->string('type');
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });
    }

    private function notification(string $id): Notification
    {
        return new Notification()->setAttribute('id', $id);
    }

    /** Defaults to one house, two bins per house and one notification per house. */
    private function repository(?PlanLimits $limits = null): ActiveEntityRepository
    {
        $plans = $this->createStub(PlanRepositoryInterface::class);
        $plans->method('getLimits')->willReturn($limits ?? new PlanLimits(1, 2, 1, [NotificationType::SMS]));

        return new ActiveEntityRepository($plans);
    }

    private function user(string $id): AuthenticatedUser
    {
        return new AuthenticatedUser(['sub' => 'ext-' . $id]);
    }
}
