<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Enums\NotificationType;
use App\Factories\NotificationSenderFactory;
use App\Services\Notifications\Interfaces\NotificationSenderInterface;
use Illuminate\Container\Container;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationSenderFactoryTest extends TestCase
{
    public static function getEnumValues(): iterable
    {
        foreach (NotificationType::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[DataProvider('getEnumValues')]
    public function testCoversEveryEnumValue(NotificationType $type): void
    {
        $this->expectNotToPerformAssertions();

        $sender = Mockery::mock(NotificationSenderInterface::class);

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturn($sender);

        new NotificationSenderFactory($container)->create($type);
    }
}
