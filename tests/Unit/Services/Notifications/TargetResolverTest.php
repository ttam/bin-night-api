<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Models\PhoneNumber;
use App\Repositories\Interfaces\PhoneNumberRepositoryInterface;
use App\Services\Notifications\TargetResolver;
use PHPUnit\Framework\TestCase;

final class TargetResolverTest extends TestCase
{
    public function testAnSmsTargetIsTheUsersPhoneNumber(): void
    {
        $user = new AuthenticatedUser(['sub' => 'user_111']);
        $phoneNumber = new PhoneNumber();

        $phoneNumbers = $this->createMock(PhoneNumberRepositoryInterface::class);
        $phoneNumbers->expects($this->once())->method('findOrCreate')->with($user, '+61400000000')->willReturn($phoneNumber);

        self::assertSame($phoneNumber, new TargetResolver($phoneNumbers)->resolve($user, NotificationType::SMS, '+61400000000'));
    }
}
