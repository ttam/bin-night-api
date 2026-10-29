<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications\Twilio;

use App\Services\Notifications\Exceptions\DeliveryFailedException;
use App\Services\Notifications\Twilio\TwilioService;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Twilio\Exceptions\RestException;
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

    public function testSendMessageSendsTheMessage(): void
    {
        $instance = Mockery::mock(MessageInstance::class);

        $messages = Mockery::mock(MessageList::class);
        $messages->shouldReceive('create')->once()->with('0400000000', [
            'from' => '+123456789',
            'body' => 'example',
        ])->andReturn($instance);

        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        new TwilioService($this->config, $client)->sendMessage('0400000000', 'example');
    }

    public function testSendMessageThrowsAShortReasonOnError(): void
    {
        $error = new RestException('The number +61400000000 is not valid', 21211, 400);

        $messages = Mockery::mock(MessageList::class);
        $messages->shouldReceive('create')->once()->andThrow($error);

        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        try {
            new TwilioService($this->config, $client)->sendMessage('+61400000000', 'example');
            self::fail('Expected the failure to be thrown.');
        } catch (DeliveryFailedException $e) {
            // Only the class and Twilio's error code are kept: Twilio's own message can contain the phone number.
            self::assertSame('Twilio\Exceptions\RestException (code 21211)', $e->getMessage());
            self::assertSame($error, $e->getPrevious());
        }
    }
}
