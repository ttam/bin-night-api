<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Notifications;

use App\Auth\AuthenticatedUser;
use App\DTO\Notification\UpdateNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Api\Notifications\UpdateController;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Models\Notification;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Services\Notifications\Interfaces\TargetResolverInterface;
use Illuminate\Contracts\Validation\Validator;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\TestCase;

final class UpdateControllerTest extends TestCase
{
    public function testAnotherTargetMovesTheNotificationToItsDestination(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $request = $this->makeRequest(['target' => '+61400000001'], ['name' => 'Marty\'s Phone', 'target' => '+61400000001'])
            ->setUserResolver(static fn (): AuthenticatedUser => $user);
        $notification = new Notification()->setAttribute('type', 'sms');

        $destination = new PhoneNumber();
        $destination->setAttribute('id', '22222222-2222-4222-8222-222222222222');
        $targets = $this->createMock(TargetResolverInterface::class);
        $targets->expects($this->once())->method('resolve')->with($user, NotificationType::SMS, '+61400000001')->willReturn($destination);

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateNotification')
            ->with($notification, $this->callback(
                static fn (UpdateNotification $data): bool => $data->toDatabaseArray() === ['name' => 'Marty\'s Phone', 'phone_number_id' => '22222222-2222-4222-8222-222222222222'],
            ))
            ->willReturn(new Notification()->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000001')));

        $response = new UpdateController()($request, $repository, $targets, $notification);

        self::assertSame('+61400000001', $response->resource->target);
    }

    public function testTheTypeSentWithTheTargetIsUsedToFindItsDestination(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $request = $this->makeRequest(['target' => '+61400000001', 'type' => 'sms'], ['target' => '+61400000001', 'type' => 'sms'])
            ->setUserResolver(static fn (): AuthenticatedUser => $user);

        $targets = $this->createMock(TargetResolverInterface::class);
        $targets->expects($this->once())->method('resolve')->with($user, NotificationType::SMS, '+61400000001')->willReturn(new PhoneNumber()->setAttribute('id', '22222222-2222-4222-8222-222222222222'));

        $repository = $this->createStub(NotificationRepositoryInterface::class);
        $repository->method('updateNotification')->willReturn(new Notification());

        new UpdateController()($request, $repository, $targets, new Notification());
    }

    public function testWithoutATargetTheDestinationIsLeftAlone(): void
    {
        $request = $this->makeRequest([], ['name' => 'Renamed']);
        $notification = new Notification();

        $targets = $this->createMock(TargetResolverInterface::class);
        $targets->expects($this->never())->method('resolve');

        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateNotification')
            ->with($notification, $this->callback(static fn (UpdateNotification $data): bool => $data->toDatabaseArray() === ['name' => 'Renamed']))
            ->willReturn(new Notification()->setRelation('phoneNumber', new PhoneNumber()->setAttribute('number', '+61400000000')));

        $response = new UpdateController()($request, $repository, $targets, $notification);

        self::assertSame('+61400000000', $response->resource->target);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $validated
     */
    private function makeRequest(array $input, array $validated): UpdateNotificationRequest
    {
        $validator = new MockGenerator()->testDouble(Validator::class, false);
        $validator->method('validated')->willReturn($validated);

        return new UpdateNotificationRequest($input)->setValidator($validator);
    }
}
