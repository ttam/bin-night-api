<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

/**
 * Every request is authenticated by a token, and the API has to know who that is in the database. That is looked up once,
 * when the user is provisioned, and the rest of the request is handed the result.
 */
final class UserLookupsTest extends ApiTestCase
{
    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function requests(): iterable
    {
        yield 'a list of houses' => ['GET', '/api/v1/houses', []];
        yield 'a list of bins' => ['GET', '/api/v1/bins', []];
        yield 'a list of notifications' => ['GET', '/api/v1/notifications', []];
        yield 'the plan' => ['GET', '/api/v1/me', []];
        yield 'the schedule' => ['GET', '/api/v1/schedule', []];
        yield 'creating a house' => ['POST', '/api/v1/houses', ['bin_night' => 'monday', 'name' => 'Twin Pines', 'timezone' => 'America/Los_Angeles']];
        yield 'creating a bin' => ['POST', '/api/v1/bins', ['bin_color' => '#000000', 'cycle' => 'weekly', 'house_id' => '{house}', 'lid_color' => '#ffffff', 'name' => 'Recycling', 'start_date' => '1985-10-21']];
        yield 'creating a notification' => ['POST', '/api/v1/notifications', ['house_id' => '{house}', 'lead_days' => 1, 'name' => 'Marty', 'send_time' => '18:30:00', 'target' => '+61400000099', 'type' => 'sms']];
        yield 'changing a bin' => ['PATCH', '/api/v1/bins/{bin}', ['name' => 'Renamed']];
        yield 'changing a house' => ['PATCH', '/api/v1/houses/{house}', ['name' => 'Renamed']];
        yield 'changing a notification' => ['PATCH', '/api/v1/notifications/{notification}', ['target' => '+61400000098']];
        yield 'deleting a bin' => ['DELETE', '/api/v1/bins/{bin}', []];
        yield 'adding a phone number' => ['POST', '/api/v1/phone-numbers', ['number' => '+61400000097']];
        yield 'checking a code' => ['POST', '/api/v1/phone-numbers/{phone}/verify', ['code' => '123456']];
    }

    /** @param array<string, mixed> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('requests')]
    public function testARequestLooksTheUserUpOnce(string $method, string $uri, array $body): void
    {
        $this->useLimits(houses: 3, binsPerHouse: 5, notificationsPerHouse: 5);
        $ids = [
            '{house}' => $house = $this->createHouse('Hill Valley', '1985-10-26 01:21:00'),
            '{bin}' => $this->createBin($house, 'Rubbish', '1985-10-26 01:21:00'),
            '{notification}' => $this->createNotification($house, '+61400000000', '1985-10-26 01:21:00'),
            '{phone}' => $this->createPhoneNumber('+61400000096', 'pending'),
        ];
        $uri = \strtr($uri, $ids);
        $body = \json_decode(\strtr((string) \json_encode($body), $ids), true, flags: JSON_THROW_ON_ERROR);

        $lookups = [];
        DB::listen(static function ($query) use (&$lookups): void {
            if (\preg_match('/\bfrom ["`]users["`]/i', $query->sql) === 1) {
                $lookups[] = $query->sql;
            }
        });

        $this->json($method, $uri, $body)->assertSuccessful();

        self::assertCount(1, $lookups, \implode("\n", $lookups));
    }

    public function testAFirstTimeUserIsLookedUpAndCreatedWithoutAnyMore(): void
    {
        \config(['authentication.clerk.development_user' => 'user_new']);
        $this->useLimits();

        $selects = [];
        DB::listen(static function ($query) use (&$selects): void {
            if (\preg_match('/\bfrom ["`]users["`]/i', $query->sql) === 1) {
                $selects[] = $query->sql;
            }
        });

        $this->postJson('/api/v1/houses', ['bin_night' => 'monday', 'name' => 'Hill Valley', 'timezone' => 'America/Los_Angeles'])->assertCreated();

        self::assertCount(1, $selects, \implode("\n", $selects));
    }
}
