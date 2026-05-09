<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications\Twilio;

use App\Services\Notifications\Twilio\TwilioService;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Client;

final class TwilioServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Repository $config;
    private MessageList $messages;

    private TwilioService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Repository([
            'services' => [
                'twilio' => [
                    'sid' => 'test-sid',
                    'secret' => 'test-secret',
                    'phone_number' => '+123456789',
                ],
            ],
        ]);
    }

    public function testSendMessageReturnsTrueOnSuccess(): void
    {
        $instance = Mockery::mock(MessageInstance::class);

        $messages = Mockery::mock(MessageList::class);
        $messages->shouldReceive('create')->once()->with('0400000000', [
            'from' => '+123456789',
            'body' => 'example',
        ])->andReturn($instance);

        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        self::assertTrue(new TwilioService($this->config, $client)->sendMessage('0400000000', 'example'));
    }

    public function testSendMessageReturnsFalseOnError(): void
    {
        $messages = Mockery::mock(MessageList::class);
        $messages->shouldReceive('create')->once()->andThrow(new TwilioException('Exception'));

        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        self::assertFalse(new TwilioService($this->config, $client)->sendMessage('0400000000', 'example'));
    }
}
