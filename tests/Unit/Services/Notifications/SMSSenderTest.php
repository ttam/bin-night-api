<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Models\Bin;
use App\Services\Notifications\SMSSender;
use App\Services\Notifications\Twilio\Interfaces\TwilioServiceInterface;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class SMSSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendCallsService(): void
    {
        $service = \Mockery::mock(TwilioServiceInterface::class);
        $service->shouldReceive('sendMessage')->once()->with('0400000000', 'Bin 1, Bin 2');

        new SMSSender($service)->send('0400000000', new Collection([
            new Bin()->setAttribute('name', 'Bin 1'),
            new Bin()->setAttribute('name', 'Bin 2'),
        ]));
    }
}
