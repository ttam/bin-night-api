<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Verification;

use App\Enums\VerificationResult;
use App\Services\Verification\Exceptions\VerificationUnavailableException;
use App\Services\Verification\TwilioVerifier;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use Twilio\Rest\Verify;
use Twilio\Rest\Verify\V2;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;
use Twilio\Rest\Verify\V2\Service\VerificationCheckList;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;
use Twilio\Rest\Verify\V2\Service\VerificationList;
use Twilio\Rest\Verify\V2\ServiceContext;

final class TwilioVerifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Repository $config;

    private ServiceContext $service;

    private Client $client;

    protected function setUp(): void
    {
        $this->config = new Repository(['services' => ['twilio' => ['sid' => 'sid', 'secret' => 'secret', 'verify_sid' => 'VA123']]]);

        $this->service = Mockery::mock(ServiceContext::class);
        $v2 = Mockery::mock(V2::class);
        $v2->shouldReceive('services')->with('VA123')->andReturn($this->service);
        $verify = Mockery::mock(Verify::class);
        $verify->v2 = $v2;
        $this->client = Mockery::mock(Client::class);
        $this->client->verify = $verify;
    }

    public function testEnrolAsksTwilioToTextACode(): void
    {
        $verifications = Mockery::mock(VerificationList::class);
        $verifications->shouldReceive('create')->once()->with('+61400000000', 'sms')->andReturn(Mockery::mock(VerificationInstance::class));
        $this->service->verifications = $verifications;

        $this->verifier()->enrol('+61400000000');
    }

    public function testEnrolKeepsOnlyTheClassAndCodeOfAFailure(): void
    {
        $verifications = Mockery::mock(VerificationList::class);
        $verifications->shouldReceive('create')->andThrow(new RestException('Invalid number +61400000000', 60200, 400));
        $this->service->verifications = $verifications;

        try {
            $this->verifier()->enrol('+61400000000');
            self::fail('Expected an exception.');
        } catch (VerificationUnavailableException $e) {
            self::assertSame('Twilio\Exceptions\RestException (code 60200)', $e->getMessage());
            self::assertStringNotContainsString('+61400000000', $e->getMessage());
        }
    }

    public function testCheckApprovesTheRightCode(): void
    {
        $this->checks()->shouldReceive('create')->once()->with(['to' => '+61400000000', 'code' => '123456'])->andReturn($this->checkResult('approved'));

        self::assertSame(VerificationResult::APPROVED, $this->verifier()->check('+61400000000', '123456'));
    }

    public function testCheckReportsAWrongCode(): void
    {
        $this->checks()->shouldReceive('create')->andReturn($this->checkResult('pending'));

        self::assertSame(VerificationResult::WRONG_CODE, $this->verifier()->check('+61400000000', '000000'));
    }

    /** @return iterable<string, array{TwilioException, VerificationResult}> */
    public static function failures(): iterable
    {
        yield 'no such verification' => [new RestException('Not found', 20404, 404), VerificationResult::EXPIRED];
        yield 'too many attempts' => [new RestException('Max check attempts reached', 60202, 429), VerificationResult::TOO_MANY_ATTEMPTS];
        yield 'another API error' => [new RestException('Server error', 20500, 500), VerificationResult::UNAVAILABLE];
        yield 'a client error' => [new ConfigurationException('No credentials'), VerificationResult::UNAVAILABLE];
    }

    #[DataProvider('failures')]
    public function testCheckTurnsAFailureIntoAResult(TwilioException $error, VerificationResult $expected): void
    {
        $this->checks()->shouldReceive('create')->andThrow($error);

        self::assertSame($expected, $this->verifier()->check('+61400000000', '123456'));
    }

    public function testItBuildsItsOwnClientFromConfigWhenNoneIsGiven(): void
    {
        $this->expectNotToPerformAssertions();

        new TwilioVerifier($this->config);
    }

    private function checks(): VerificationCheckList&Mockery\MockInterface
    {
        $checks = Mockery::mock(VerificationCheckList::class);
        $this->service->verificationChecks = $checks;

        return $checks;
    }

    private function checkResult(string $status): VerificationCheckInstance
    {
        $result = Mockery::mock(VerificationCheckInstance::class);
        $result->status = $status;

        return $result;
    }

    private function verifier(): TwilioVerifier
    {
        return new TwilioVerifier($this->config, $this->client);
    }
}
