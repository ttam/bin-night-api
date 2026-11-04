<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Notification\AbstractNotificationRequest;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Models\House;
use App\Models\Notification;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\TestCase;

final class NotificationRequestTest extends TestCase
{
    private const string HOUSE_ID = '00000000-0000-0000-0000-000000000000';

    protected function setUp(): void
    {
        $this->bootEloquent()->migrate();

        // The mobile number rule reads its patterns from config.
        $container = new Container();
        $container->instance('config', new Repository(['phone' => ['mobile_patterns' => ['AU' => '/^\+614\d{8}$/']]]));
        Container::setInstance($container);

        DB::table('houses')->insert(['id' => self::HOUSE_ID]);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public static function getBadLeadDays(): iterable
    {
        yield 'Negative' => [-1];

        yield 'More than a week' => [8];

        yield 'Not a number' => ['tomorrow'];

        yield 'Not a whole number' => [1.5];
    }

    public static function getBadSendTimes(): iterable
    {
        yield 'Not on a quarter hour' => ['11:21:00'];

        yield 'Not a time' => ['noon'];

        yield 'Not a real time' => ['25:00:00'];

        yield 'Missing the leading zero' => ['9:00:00'];

        yield 'Without the seconds' => ['18:30'];

        yield 'Seconds that are not zero' => ['18:30:30'];

        yield 'Between quarter hours' => ['18:20:00'];

        yield 'Five past' => ['18:05:00'];

        yield 'Seconds without the minutes' => ['18::00'];
    }

    public function testAuthorize(): void
    {
        $request = new class() extends AbstractNotificationRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    public function testCreateRequestAllowsANameUsedInAnotherHouse(): void
    {
        DB::table('houses')->insert(['id' => '11111111-1111-1111-1111-111111111111']);
        DB::table('notifications')->insert(['id' => 'n1', 'house_id' => '11111111-1111-1111-1111-111111111111', 'name' => 'Marty\'s Phone']);

        self::assertTrue($this->validate([])->passes());
    }

    public function testCreateRequestFailsWithANameAlreadyUsedInTheHouse(): void
    {
        DB::table('notifications')->insert(['id' => 'n1', 'house_id' => self::HOUSE_ID, 'name' => 'Marty\'s Phone']);

        $validator = $this->validate([]);

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithAnInvalidName(): void
    {
        foreach ([\str_repeat('a', 256), ['array'], ''] as $name) {
            $validator = $this->validate(['name' => $name]);

            self::assertFalse($validator->passes());
            self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
        }
    }

    #[DataProvider('getBadLeadDays')]
    public function testCreateRequestFailsWithInvalidLeadDays(mixed $leadDays): void
    {
        $validator = $this->validate(['lead_days' => $leadDays]);

        self::assertFalse($validator->passes());
        self::assertSame(['lead_days'], \array_keys($validator->errors()->toArray()));
    }

    #[DataProvider('getBadSendTimes')]
    public function testCreateRequestFailsWithInvalidSendTime(string $sendTime): void
    {
        $validator = $this->validate(['send_time' => $sendTime]);

        self::assertFalse($validator->passes());
        self::assertSame(['send_time'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithInvalidType(): void
    {
        $validator = $this->validate(['type' => 'carrier-pigeon']);

        self::assertFalse($validator->passes());
        self::assertSame(['type'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithMalformedHouseId(): void
    {
        $validator = $this->validate(['house_id' => 'not-a-uuid']);

        self::assertFalse($validator->passes());
        self::assertSame(['house_id'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestFailsWithUnknownHouse(): void
    {
        $validator = $this->validate(['house_id' => '11111111-1111-1111-1111-111111111111']);

        self::assertFalse($validator->passes());
        self::assertSame(['house_id'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestPassesWithValidPayload(): void
    {
        self::assertTrue($this->validate([])->passes());
    }

    public function testCreateRequestRequiresAllFields(): void
    {
        $validator = $this->getValidator([], $this->createRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(
            ['lead_days', 'name', 'send_time', 'target', 'type', 'house_id'],
            \array_keys($validator->errors()->toArray()),
        );
    }

    public function testMessagesOnlyExplainTheSendTime(): void
    {
        self::assertSame(
            [
                'send_time.date_format' => 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.',
                'send_time.regex' => 'The send time must be on a quarter hour, like 18:00:00 or 18:45:00.',
            ],
            $this->createRequest()->messages(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function getBadTargets(): iterable
    {
        yield 'Local format' => ['0400000000'];

        yield 'Not a number' => ['example'];

        yield 'A landline' => ['+61290000000'];

        yield 'Another country' => ['+64211234567'];

        yield 'Too short' => ['+6140'];

        yield 'With spaces' => ['+61 400 000 000'];
    }

    public function testCreateRequestAcceptsAnAustralianMobile(): void
    {
        self::assertTrue($this->validate(['target' => '+61400000000', 'type' => 'sms'])->passes());
    }

    #[DataProvider('getBadTargets')]
    public function testCreateRequestFailsWithAnInvalidTarget(string $target): void
    {
        $validator = $this->validate(['target' => $target]);

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
    }

    public function testCreateRequestExplainsAnInvalidTarget(): void
    {
        $validator = $this->validate(['target' => '0400000000']);

        self::assertFalse($validator->passes());
        self::assertSame(['target' => ['The target must be an Australian mobile, like +61400000000.']], $validator->errors()->toArray());
    }

    public function testCreateRequestChecksNoTargetFormatWithoutAKnownType(): void
    {
        $validator = $this->validate(['target' => 'whatever', 'type' => 'carrier-pigeon']);

        self::assertFalse($validator->passes());
        self::assertSame(['type'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestChecksTheTargetAgainstTheNotificationsOwnType(): void
    {
        $data = ['target' => '0400000000'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data, 'sms')->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestChecksTheTargetAgainstTheNewTypeWhenItIsSent(): void
    {
        $data = ['target' => '0400000000', 'type' => 'sms'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data, 'carrier-pigeon')->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestExplainsTheTargetFormatOfTheNotificationsOwnType(): void
    {
        $data = ['target' => '0400000000'];
        $request = $this->makeUpdateRequest($data, 'sms');

        $validator = $this->getValidator($data, $request->rules(), $request->messages());

        self::assertFalse($validator->passes());
        self::assertSame(['target' => ['The target must be an Australian mobile, like +61400000000.']], $validator->errors()->toArray());
    }

    public function testUpdateRequestSkipsTheTargetFormatForOtherTypes(): void
    {
        $data = ['target' => 'not-a-phone-number'];

        self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data, 'carrier-pigeon')->rules())->passes());
    }

    public function testUpdateRequestDoesNotAcceptAnotherNotificationsPhoneNumber(): void
    {
        self::assertArrayNotHasKey('phone_number_id', $this->makeUpdateRequest([], 'sms')->rules());
    }

    public function testSendTimeBetweenQuarterHoursGetsTheQuarterHourMessage(): void
    {
        $request = $this->createRequest();
        $validator = $this->getValidator(['send_time' => '18:20:00'], ['send_time' => $request->rules()['send_time']], $request->messages());

        self::assertFalse($validator->passes());
        self::assertSame(['The send time must be on a quarter hour, like 18:00:00 or 18:45:00.'], $validator->errors()->get('send_time'));
    }

    #[DataProvider('getBadSendTimes')]
    public function testSendTimeNotOnAQuarterHourIsExplained(string $sendTime): void
    {
        $request = $this->createRequest();
        $validator = $this->getValidator(['send_time' => $sendTime], ['send_time' => $request->rules()['send_time']], $request->messages());

        self::assertFalse($validator->passes());
        self::assertCount(1, $validator->errors()->get('send_time'));
    }

    public function testSendTimeOnAQuarterHourPasses(): void
    {
        foreach (['00:00:00', '07:15:00', '07:30:00', '18:45:00', '23:45:00'] as $sendTime) {
            self::assertTrue($this->validate(['send_time' => $sendTime])->passes(), $sendTime);
        }
    }

    public function testUpdateRequestAllowsTheNotificationsOwnName(): void
    {
        DB::table('notifications')->insert(['id' => 'n1', 'house_id' => self::HOUSE_ID, 'name' => 'Marty\'s Phone']);
        $data = ['name' => 'Marty\'s Phone'];

        self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data)->rules())->passes());
    }

    public function testUpdateRequestDoesNotAcceptHouseId(): void
    {
        self::assertArrayNotHasKey('house_id', $this->makeUpdateRequest([])->rules());
    }

    public function testUpdateRequestFailsWithANameAlreadyUsedByAnotherNotificationInTheHouse(): void
    {
        DB::table('notifications')->insert(['id' => 'n2', 'house_id' => self::HOUSE_ID, 'name' => 'Doc\'s Phone']);
        $data = ['name' => 'Doc\'s Phone'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data)->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['name'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestFailsWithInvalidValues(): void
    {
        $data = ['lead_days' => 8, 'send_time' => '11:21:00', 'type' => 'carrier-pigeon'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data)->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['lead_days', 'send_time', 'type'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestPassesWithAnEmptyPayload(): void
    {
        self::assertTrue($this->getValidator([], $this->makeUpdateRequest([])->rules())->passes());
    }

    public function testUpdateRequestPassesWithAPartialPayload(): void
    {
        $data = ['send_time' => '07:00:00', 'target' => '+61400000001'];

        self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data)->rules())->passes());
    }

    private function getValidator(array $data, array $rules, array $messages = []): Validator
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules, $messages);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier(House::getConnectionResolver()));

        return $validator;
    }

    private function createRequest(array $data = []): CreateNotificationRequest
    {
        return new CreateNotificationRequest($data);
    }

    private function makeUpdateRequest(array $input, string $notificationType = 'sms'): UpdateNotificationRequest
    {
        $notification = new Notification()->setAttribute('id', 'n1')->setAttribute('house_id', self::HOUSE_ID)->setAttribute('type', $notificationType);

        $route = new Route('PATCH', 'notifications/{notification}', static fn (): null => null);
        $route->bind(Request::create('/notifications/n1', 'PATCH'));
        $route->setParameter('notification', $notification);

        return new UpdateNotificationRequest($input)->setRouteResolver(static fn (): Route => $route);
    }

    private function migrate(): void
    {
        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
            $table->softDeletes();
        });

        DB::schema()->create('notifications', static function (Blueprint $table): void {
            $table->string('id');
            $table->string('house_id');
            $table->string('name');
        });
    }

    /** Validates a payload that is valid except for the fields passed in. */
    private function validate(array $overrides): Validator
    {
        $data = [
            'house_id' => self::HOUSE_ID,
            'lead_days' => 1,
            'name' => 'Marty\'s Phone',
            'send_time' => '18:30:00',
            'target' => '+61400000000',
            'type' => 'sms',
            ...$overrides,
        ];

        $request = $this->createRequest($data);

        return $this->getValidator($data, $request->rules(), $request->messages());
    }
}
