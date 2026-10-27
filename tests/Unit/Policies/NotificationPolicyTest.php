<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\NotificationType;
use App\Models\House;
use App\Models\Notification;
use App\Models\User;
use App\Policies\AbstractPolicy;
use App\Policies\NotificationPolicy;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\TestCase;

final class NotificationPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();
    }

    public static function getUnknownTypes(): iterable
    {
        yield 'Unknown type' => ['carrier-pigeon'];

        yield 'Not a string' => [['sms']];

        yield 'Not provided' => [null];
    }

    public function testCreateDoesNotCountNotificationsInOtherHouses(): void
    {
        DB::table('notifications')->insert([
            ['id' => 'n1', 'house_id' => 'h2', 'type' => 'sms'],
            ['id' => 'n2', 'house_id' => 'h2', 'type' => 'sms'],
            ['id' => 'n3', 'house_id' => 'h1', 'type' => 'sms'],
        ]);

        $response = new NotificationPolicy($this->planRepository(notificationsPerHouse: 2))
            ->create($this->user(), $this->house(), 'sms');

        self::assertTrue($response->allowed());
    }

    public function testCreateDoesNotCountNotificationsOfATypeThePlanDoesNotAllow(): void
    {
        DB::table('notifications')->insert(['id' => 'n1', 'house_id' => 'h1', 'type' => 'carrier-pigeon']);

        $response = new NotificationPolicy($this->planRepository(notificationsPerHouse: 1))
            ->create($this->user(), $this->house(), 'sms');

        self::assertTrue($response->allowed());
    }

    public function testCreateFailsAtTheLimitOfOne(): void
    {
        DB::table('notifications')->insert(['id' => 'n1', 'house_id' => 'h1', 'type' => 'sms']);

        $response = new NotificationPolicy($this->planRepository(notificationsPerHouse: 1))
            ->create($this->user(), $this->house(), 'sms');

        self::assertFalse($response->allowed());
        self::assertSame('Your plan allows 1 notification per house.', $response->message());
    }

    public function testCreateFailsAtThePlanLimitForThatHouse(): void
    {
        DB::table('notifications')->insert([
            ['id' => 'n1', 'house_id' => 'h1', 'type' => 'sms'],
            ['id' => 'n2', 'house_id' => 'h1', 'type' => 'sms'],
            ['id' => 'n3', 'house_id' => 'h1', 'type' => 'sms'],
        ]);

        $response = new NotificationPolicy($this->planRepository(notificationsPerHouse: 3))
            ->create($this->user(), $this->house(), 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(AbstractPolicy::CODE, $response->code());
        self::assertSame('Your plan allows 3 notifications per house.', $response->message());
    }

    public function testCreateFailsWhenThePlanDoesNotAllowTheType(): void
    {
        $response = new NotificationPolicy($this->planRepository(types: []))
            ->create($this->user(), $this->house(), 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(AbstractPolicy::CODE, $response->code());
        self::assertSame('Your plan does not include sms notifications.', $response->message());
    }

    public function testCreateFailsWithOtherUsersHouse(): void
    {
        $house = new House()->setAttribute('id', 'h1')->setAttribute('user_id', '2');

        $response = new NotificationPolicy($this->planRepository())->create($this->user(), $house, 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testCreateFailsWithUnknownHouse(): void
    {
        $response = new NotificationPolicy($this->planRepository())->create($this->user(), null, 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    #[DataProvider('getUnknownTypes')]
    public function testCreatePassesWithAnUnknownTypeSoValidationCanReportIt(mixed $type): void
    {
        $response = new NotificationPolicy($this->planRepository())->create($this->user(), $this->house(), $type);

        self::assertTrue($response->allowed());
    }

    public function testCreatePassesWithAnUnlimitedPlan(): void
    {
        DB::table('notifications')->insert([
            ['id' => 'n1', 'house_id' => 'h1', 'type' => 'sms'],
            ['id' => 'n2', 'house_id' => 'h1', 'type' => 'sms'],
        ]);

        $response = new NotificationPolicy($this->planRepository(notificationsPerHouse: PlanLimits::UNLIMITED))
            ->create($this->user(), $this->house(), 'sms');

        self::assertTrue($response->allowed());
    }

    public function testCreatePassesWithUserOwnedHouse(): void
    {
        $response = new NotificationPolicy($this->planRepository())->create($this->user(), $this->house(), 'sms');

        self::assertTrue($response->allowed());
    }

    public function testUpdateFailsWhenChangingToATypeThePlanDoesNotAllow(): void
    {
        $response = new NotificationPolicy($this->planRepository(types: []))
            ->update($this->user(), $this->notification('carrier-pigeon'), 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(AbstractPolicy::CODE, $response->code());
        self::assertSame('Your plan does not include sms notifications.', $response->message());
    }

    public function testUpdateFailsWithOtherUsersNotification(): void
    {
        $response = new NotificationPolicy($this->planRepository())
            ->update($this->user(), $this->notification('sms', '2'), 'sms');

        self::assertFalse($response->allowed());
        self::assertSame(404, $response->status());
    }

    public function testUpdatePassesWhenChangingToATypeThePlanAllows(): void
    {
        $response = new NotificationPolicy($this->planRepository())
            ->update($this->user(), $this->notification('carrier-pigeon'), 'sms');

        self::assertTrue($response->allowed());
    }

    public function testUpdatePassesWhenTheTypeIsNotChanging(): void
    {
        $response = new NotificationPolicy($this->planRepository(types: []))
            ->update($this->user(), $this->notification('sms'), 'sms');

        self::assertTrue($response->allowed());
    }

    #[DataProvider('getUnknownTypes')]
    public function testUpdatePassesWithAnUnknownTypeSoValidationCanReportIt(mixed $type): void
    {
        $response = new NotificationPolicy($this->planRepository(types: []))
            ->update($this->user(), $this->notification('sms'), $type);

        self::assertTrue($response->allowed());
    }

    public function testUpdatePassesWithUserOwnedNotification(): void
    {
        $response = new NotificationPolicy($this->planRepository())->update($this->user(), $this->notification('sms'));

        self::assertTrue($response->allowed());
    }

    private function house(): House
    {
        return new House()->setAttribute('id', 'h1')->setAttribute('user_id', '1');
    }

    private function migrate(): void
    {
        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('type');
        });
    }

    private function notification(string $type, string $userId = '1'): Notification
    {
        return new Notification()->setAttribute('type', $type)->setAttribute('user_id', $userId);
    }

    /** @param list<\App\Enums\NotificationType>|null $types */
    private function planRepository(int $notificationsPerHouse = 2, ?array $types = null): PlanRepositoryInterface
    {
        $repository = $this->createStub(PlanRepositoryInterface::class);
        $repository->method('getLimits')->willReturn(
            new PlanLimits(1, 1, $notificationsPerHouse, $types ?? [NotificationType::SMS]),
        );

        return $repository;
    }

    private function user(): User
    {
        return new User()->setAttribute('id', '1');
    }
}
