<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\NotificationType;
use App\Events\NotificationEvent;
use App\Factories\Interfaces\NotificationSenderFactoryInterface;
use App\Listeners\NotificationListener;
use App\Models\Bin;
use App\Models\Notification;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class NotificationListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testFactoryResolvesSenderAndSends(): void
    {
        $notification = new Notification()->setAttribute('type', 'sms')->setAttribute('target', '0400000000');
        $bins = new Collection([new Bin()]);

        $sender = Mockery::mock(NotificationSenderInterface::class);
        $sender->shouldReceive('send')->once()->with('0400000000', $bins);

        $factory = Mockery::mock(NotificationSenderFactoryInterface::class);
        $factory->shouldReceive('create')->once()->with(NotificationType::SMS)->andReturn($sender);

        new NotificationListener($factory)->handle(new NotificationEvent($notification, $bins));
    }
}
