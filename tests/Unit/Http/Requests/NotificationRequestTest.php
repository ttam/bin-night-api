<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\NotificationType;
use App\Http\Requests\Notification\AbstractNotificationRequest;
use App\Http\Requests\Notification\CreateNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Models\House;
use App\Models\Notification;
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

        DB::table('houses')->insert(['id' => self::HOUSE_ID]);
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
        yield 'Not on the half hour' => ['11:21'];

        yield 'Not a time' => ['noon'];

        yield 'Not a real time' => ['25:00'];

        yield 'Missing the leading zero' => ['9:00'];
    }

    public static function getBadSmsTargets(): iterable
    {
        yield 'Local format' => ['0400000000'];

        yield 'Not a number' => ['example'];

        yield 'Starts with zero' => ['+0400000000'];

        yield 'Too short' => ['+6140'];
    }

    /** Adding a notification type breaks this until the type says what a good and a bad target look like. */
    public static function getTargetExamples(): iterable
    {
        foreach (NotificationType::cases() as $type) {
            yield $type->name => [$type, ...match ($type) {
                NotificationType::SMS => [
                    '+61400000000',
                    '0400000000',
                    'The target must be an international phone number, like +61400000000.',
                ],
            }];
        }
    }

    public function testAuthorize(): void
    {
        $request = new class() extends AbstractNotificationRequest {
            protected string $presence = 'required';
        };

        self::assertTrue($request->authorize());
    }

    #[DataProvider('getTargetExamples')]
    public function testCreateRequestChecksTheTargetFormatOfEveryType(
        NotificationType $type,
        string $valid,
        string $invalid,
        string $message,
    ): void {
        self::assertTrue($this->validate(['target' => $valid, 'type' => $type->value])->passes());

        $validator = $this->validate(['target' => $invalid, 'type' => $type->value]);

        self::assertFalse($validator->passes());
        self::assertSame(['target' => [$message]], $validator->errors()->toArray());
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

    #[DataProvider('getBadSmsTargets')]
    public function testCreateRequestFailsWithInvalidSmsTarget(string $target): void
    {
        $validator = $this->validate(['target' => $target]);

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
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
        $validator = $this->getValidator([], new CreateNotificationRequest()->rules());

        self::assertFalse($validator->passes());
        self::assertSame(
            ['lead_days', 'send_time', 'target', 'type', 'house_id'],
            \array_keys($validator->errors()->toArray()),
        );
    }

    public function testMessagesAreEmptyWithoutAType(): void
    {
        self::assertSame([], new CreateNotificationRequest()->messages());
    }

    public function testUpdateRequestChecksTheTargetAgainstTheNewTypeWhenItIsSent(): void
    {
        $data = ['target' => '0400000000', 'type' => 'sms'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data, 'carrier-pigeon')->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestChecksTheTargetAgainstTheNotificationsOwnType(): void
    {
        $data = ['target' => '0400000000'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data, 'sms')->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['target'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestDoesNotAcceptHouseId(): void
    {
        self::assertArrayNotHasKey('house_id', new UpdateNotificationRequest()->rules());
    }

    public function testUpdateRequestExplainsTheTargetFormatOfTheNotificationsOwnType(): void
    {
        $data = ['target' => '0400000000'];
        $request = $this->makeUpdateRequest($data, 'sms');

        $validator = $this->getValidator($data, $request->rules(), $request->messages());

        self::assertFalse($validator->passes());
        self::assertSame(
            ['target' => ['The target must be an international phone number, like +61400000000.']],
            $validator->errors()->toArray(),
        );
    }

    public function testUpdateRequestFailsWithInvalidValues(): void
    {
        $data = ['lead_days' => 8, 'send_time' => '11:21', 'type' => 'carrier-pigeon'];

        $validator = $this->getValidator($data, $this->makeUpdateRequest($data, 'sms')->rules());

        self::assertFalse($validator->passes());
        self::assertSame(['lead_days', 'send_time', 'type'], \array_keys($validator->errors()->toArray()));
    }

    public function testUpdateRequestPassesWithAPartialPayload(): void
    {
        $data = ['send_time' => '07:00', 'target' => '+61400000001'];

        self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data, 'sms')->rules())->passes());
    }

    public function testUpdateRequestPassesWithAnEmptyPayload(): void
    {
        self::assertTrue($this->getValidator([], $this->makeUpdateRequest([], 'sms')->rules())->passes());
    }

    public function testUpdateRequestSkipsTheTargetFormatForOtherTypes(): void
    {
        $data = ['target' => 'not-a-phone-number'];

        self::assertTrue($this->getValidator($data, $this->makeUpdateRequest($data, 'carrier-pigeon')->rules())->passes());
    }

    public function testUpdateRequestWorksWithoutARouteNotification(): void
    {
        $data = ['target' => 'anything'];

        self::assertTrue($this->getValidator($data, new UpdateNotificationRequest($data)->rules())->passes());
    }

    private function getValidator(array $data, array $rules, array $messages = []): Validator
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en-AU'), $data, $rules, $messages);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier(House::getConnectionResolver()));

        return $validator;
    }

    private function makeUpdateRequest(array $input, string $notificationType): UpdateNotificationRequest
    {
        $notification = new Notification()->setAttribute('id', 'n1')->setAttribute('type', $notificationType);

        $route = new Route('PATCH', 'notifications/{notification}', static fn (): null => null);
        $route->bind(Request::create('/notifications/n1', 'PATCH'));
        $route->setParameter('notification', $notification);

        return new UpdateNotificationRequest($input)->setRouteResolver(static fn (): Route => $route);
    }

    private function migrate(): void
    {
        DB::schema()->create('houses', static function (Blueprint $table): void {
            $table->string('id');
        });
    }

    /** Validates a payload that is valid except for the fields passed in. */
    private function validate(array $overrides): Validator
    {
        $data = [
            'house_id' => self::HOUSE_ID,
            'lead_days' => 1,
            'send_time' => '18:30',
            'target' => '+61400000000',
            'type' => 'sms',
            ...$overrides,
        ];

        $request = new CreateNotificationRequest($data);

        return $this->getValidator($data, $request->rules(), $request->messages());
    }
}
