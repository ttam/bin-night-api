<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Clock\KnownClock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use Tests\Feature\Api\ApiTestCase;
use Tests\Unit\Services\Webhooks\SvixVerifierTest;

final class ClerkWebhookTest extends ApiTestCase
{
    private const string KEY = 'a-very-secret-signing-key-12345';

    /** 1985-10-26 00:00:00 UTC, which is the evening of the 25th in Los Angeles. */
    private const int NOW = 499_132_800;

    private const string DELETED_AT = '1985-10-26 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        \config(['authentication.clerk.webhook_secret' => 'whsec_' . \base64_encode(self::KEY)]);
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable('@' . self::NOW, new DateTimeZone('UTC'))));
    }

    public function testADeletedUsersAccountIsDeletedAndTheirDataKept(): void
    {
        $this->useLimits(houses: 3);
        $this->giveSomeoneData($this->userId);
        $earlier = $this->createHouse('Twin Pines', '1955-11-12 06:00:00');
        DB::table('houses')->where('id', $earlier)->update(['deleted_at' => '1985-10-20 09:00:00']);
        $otherId = $this->createUser('user_222');
        $this->giveSomeoneData($otherId, 'Lyon Estates');

        $this->signedEvent(['data' => ['deleted' => true, 'id' => 'user_111', 'object' => 'user'], 'type' => 'user.deleted'])->assertNoContent();

        // Everything is still there, marked as deleted together.
        self::assertSame(2, DB::table('users')->count());
        self::assertSame(self::DELETED_AT, DB::table('users')->where('external_id', 'user_111')->value('deleted_at'));
        self::assertNull(DB::table('users')->where('external_id', 'user_222')->value('deleted_at'));
        foreach (['houses' => 3, 'bins' => 2, 'notifications' => 2, 'phone_numbers' => 2] as $table => $rows) {
            self::assertSame($rows, DB::table($table)->count(), $table);
            self::assertSame([null], DB::table($table)->where('user_id', $otherId)->pluck('deleted_at')->unique()->values()->all(), $table);
        }
        foreach (['bins', 'notifications', 'phone_numbers'] as $table) {
            self::assertSame([self::DELETED_AT], DB::table($table)->where('user_id', $this->userId)->pluck('deleted_at')->all(), $table);
        }
        // What was deleted earlier keeps its own time, so it is not mistaken for part of the account.
        self::assertSame(['1985-10-20 09:00:00', self::DELETED_AT], DB::table('houses')->where('user_id', $this->userId)->orderBy('deleted_at')->pluck('deleted_at')->all());
        // Nothing is renamed or changed, and the delivery logs are as they were.
        self::assertSame(['Hill Valley', 'Twin Pines'], DB::table('houses')->where('user_id', $this->userId)->orderBy('name')->pluck('name')->all());
        self::assertSame(2, DB::table('notification_logs')->count());
    }

    public function testNothingIsSentToADeletedUser(): void
    {
        $this->giveSomeoneData($this->userId);
        $repository = $this->app->make(\App\Repositories\Interfaces\NotificationRepositoryInterface::class);
        $due = static fn (): int => $repository->getDueNotifications(new DateTimeImmutable('1985-10-27 01:15', new DateTimeZone('UTC')), new DateTimeImmutable('1985-10-27 01:30', new DateTimeZone('UTC')))->count();

        self::assertSame(1, $due());

        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'])->assertNoContent();

        self::assertSame(0, $due());
    }

    public function testTheDeletedAccountCannotSignBackInOrMakeASecondUser(): void
    {
        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'])->assertNoContent();

        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/houses')->assertUnauthorized();

        self::assertSame(1, DB::table('users')->count());
        self::assertSame(self::DELETED_AT, DB::table('users')->value('deleted_at'));
    }

    public function testTheSameEventTwiceIsStillASuccess(): void
    {
        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'])->assertNoContent();
        $this->app->instance(ClockInterface::class, new KnownClock(new DateTimeImmutable('@' . (self::NOW + 60), new DateTimeZone('UTC'))));
        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'], self::NOW + 60)->assertNoContent();

        // The second changes nothing: the account was deleted when it was first deleted.
        self::assertSame(1, DB::table('users')->count());
        self::assertSame(self::DELETED_AT, DB::table('users')->value('deleted_at'));
    }

    public function testAUserWeNeverHadIsASuccessToo(): void
    {
        $this->signedEvent(['data' => ['id' => 'user_999'], 'type' => 'user.deleted'])->assertNoContent();

        $this->assertNobodyWasDeleted();
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function eventsThatChangeNothing(): iterable
    {
        yield 'another kind of event' => [['data' => ['id' => 'user_111'], 'type' => 'user.updated']];
        yield 'a session event' => [['data' => ['id' => 'sess_1', 'user_id' => 'user_111'], 'type' => 'session.created']];
        yield 'a deletion with no user' => [['data' => [], 'type' => 'user.deleted']];
        yield 'a deletion with no data' => [['type' => 'user.deleted']];
        yield 'a deletion with an empty id' => [['data' => ['id' => ''], 'type' => 'user.deleted']];
        yield 'a deletion with an id that is not text' => [['data' => ['id' => ['user_111']], 'type' => 'user.deleted']];
        yield 'a deletion with data that is not an object' => [['data' => 'user_111', 'type' => 'user.deleted']];
    }

    /** @param array<string, mixed> $event */
    #[\PHPUnit\Framework\Attributes\DataProvider('eventsThatChangeNothing')]
    public function testAnEventWeHaveNoUseForChangesNothing(array $event): void
    {
        $this->signedEvent($event)->assertNoContent();

        $this->assertNobodyWasDeleted();
    }

    public function testAMessageThatIsNotSignedIsRefusedAndDeletesNothing(): void
    {
        $this->call('POST', '/api/webhooks/clerk', server: ['CONTENT_TYPE' => 'application/json'], content: '{"data":{"id":"user_111"},"type":"user.deleted"}')
            ->assertUnauthorized();

        $this->assertNobodyWasDeleted();
    }

    public function testAMessageSignedWithTheWrongSecretIsRefusedAndDeletesNothing(): void
    {
        $body = '{"data":{"id":"user_111"},"type":"user.deleted"}';

        $this->send($body, 'msg_1', self::NOW, SvixVerifierTest::sign($body, 'msg_1', self::NOW, 'someone-elses-key'))->assertUnauthorized();

        $this->assertNobodyWasDeleted();
    }

    public function testAnOldSignedMessageCannotBeReplayed(): void
    {
        $body = '{"data":{"id":"user_111"},"type":"user.deleted"}';
        $then = self::NOW - 3600;

        $this->send($body, 'msg_1', $then, SvixVerifierTest::sign($body, 'msg_1', $then, self::KEY))->assertUnauthorized();

        $this->assertNobodyWasDeleted();
    }

    public function testNothingIsAcceptedWhenNoSecretIsSetUp(): void
    {
        \config(['authentication.clerk.webhook_secret' => null]);

        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'])->assertUnauthorized();

        $this->assertNobodyWasDeleted();
    }

    /** @return iterable<string, array{string}> */
    public static function bodiesThatAreNotEvents(): iterable
    {
        yield 'not JSON' => ['not json'];
        yield 'a list' => ['["user.deleted"]'];
        yield 'a number' => ['42'];
        yield 'no type' => ['{"data":{"id":"user_111"}}'];
        yield 'a type that is not text' => ['{"type":7,"data":{"id":"user_111"}}'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bodiesThatAreNotEvents')]
    public function testASignedBodyThatIsNotAnEventIsABadRequest(string $body): void
    {
        $this->send($body, 'msg_1', self::NOW, SvixVerifierTest::sign($body, 'msg_1', self::NOW, self::KEY))->assertStatus(400);

        $this->assertNobodyWasDeleted();
    }

    public function testItNeedsNoSignedInUser(): void
    {
        \config(['authentication.clerk.development_user' => null]);

        $this->signedEvent(['data' => ['id' => 'user_111'], 'type' => 'user.deleted'])->assertNoContent();

        self::assertSame(self::DELETED_AT, DB::table('users')->value('deleted_at'));
    }

    public function testTheDeletionIsLoggedWithoutAnythingPersonal(): void
    {
        $logged = [];
        \Illuminate\Support\Facades\Log::listen(static function ($event) use (&$logged): void {
            $logged[] = $event->message . \json_encode($event->context);
        });

        $this->signedEvent(['data' => ['email_addresses' => [['email_address' => 'marty@example.com']], 'id' => 'user_111'], 'type' => 'user.deleted'])->assertNoContent();

        self::assertCount(1, $logged);
        self::assertStringContainsString('Clerk user deleted.', $logged[0]);
        self::assertStringContainsString('user_111', $logged[0]);
        self::assertStringNotContainsString('marty@example.com', $logged[0]);
    }

    private function assertNobodyWasDeleted(): void
    {
        self::assertSame(1, DB::table('users')->count());
        self::assertNull(DB::table('users')->value('deleted_at'));
    }

    /** @param array<string, mixed> $event */
    private function signedEvent(array $event, int $at = self::NOW): \Illuminate\Testing\TestResponse
    {
        $body = \json_encode($event, JSON_THROW_ON_ERROR);

        return $this->send($body, 'msg_1', $at, SvixVerifierTest::sign($body, 'msg_1', $at, self::KEY));
    }

    private function send(string $body, string $id, int $timestamp, string $signature): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/api/webhooks/clerk', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_SIGNATURE' => $signature,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        ], content: $body);
    }

    private function giveSomeoneData(string $userId, string $houseName = 'Hill Valley'): void
    {
        $houseId = $this->createHouse($houseName, '1985-10-26 01:21:00', $userId);
        $this->createBin($houseId, 'Rubbish', '1985-10-26 01:21:00', $userId);
        $notificationId = $this->createNotification($houseId, '+61400000000', '1985-10-26 01:21:00', $userId);

        DB::table('notification_logs')->insert([
            'id' => (string) Str::uuid(),
            'notification_id' => $notificationId,
            'scheduled_for' => '1985-10-26 18:30:00',
            'status' => 'sent',
            'created_at' => '1985-10-26 18:30:00',
            'updated_at' => '1985-10-26 18:30:00',
        ]);
    }
}
