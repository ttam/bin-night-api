<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Feature\Api\ApiTestCase;

/** The API is for the app only: only its own origins may call it from a browser. */
final class CorsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Two, because with a single origin the library names it in every answer, and the browser does the refusing.
        \config(['cors.allowed_origins' => ['https://binnight.app', 'http://localhost:5173']]);
    }

    public function testTheAppsOriginMayCallTheApi(): void
    {
        $this->getJson('/api/v1/me', ['Origin' => 'https://binnight.app'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://binnight.app');
    }

    public function testAnotherOriginIsNotAllowed(): void
    {
        foreach (['https://evil.example', 'http://binnight.app', 'https://binnight.app.evil.example', 'https://www.binnight.app'] as $origin) {
            $this->getJson('/api/v1/me', ['Origin' => $origin])->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function testALoneOriginIsNeverTheCallersWhenTheyAreSomeoneElse(): void
    {
        \config(['cors.allowed_origins' => ['https://binnight.app']]);

        $response = $this->getJson('/api/v1/me', ['Origin' => 'https://evil.example']);

        // The answer names the app's own origin, which the browser will not accept for a page on another one.
        self::assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testThePreflightForWhatTheAppSendsIsAnswered(): void
    {
        $this->call('OPTIONS', '/api/v1/notifications', server: [
            'HTTP_ORIGIN' => 'https://binnight.app',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,accept',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://binnight.app')
            ->assertHeader('Access-Control-Max-Age', '3600');
    }

    public function testThePreflightIsRefusedForAnotherOriginAndOnlyAnswersForTheHeadersTheAppSends(): void
    {
        $preflight = static fn (string $origin, string $headers): array => [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $headers,
        ];

        $this->call('OPTIONS', '/api/v1/notifications', server: $preflight('https://evil.example', 'authorization'))
            ->assertHeaderMissing('Access-Control-Allow-Origin');
        // For a header the app does not send the answer carries only the ones it does, which the browser then holds against it.
        $allowed = $this->call('OPTIONS', '/api/v1/notifications', server: $preflight('https://binnight.app', 'x-anything-else'))
            ->headers->get('Access-Control-Allow-Headers');

        self::assertSame('accept, authorization, content-type', \strtolower((string) $allowed));
    }

    public function testTheBrowserIsAllowedToReadHowLongToWait(): void
    {
        $this->getJson('/api/v1/me', ['Origin' => 'https://binnight.app'])
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
    }

    public function testNoOriginIsAllowedWhenNoneIsConfigured(): void
    {
        \config(['cors.allowed_origins' => []]);

        $this->getJson('/api/v1/me', ['Origin' => 'https://binnight.app'])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    /** @return iterable<string, array{string|null, string|null, list<string>}> */
    public static function environments(): iterable
    {
        yield 'the same origins Clerk tokens are accepted for' => [null, 'https://binnight.app,http://localhost:5173', ['https://binnight.app', 'http://localhost:5173']];
        yield 'its own list, when there is one' => ['https://app.example', 'https://binnight.app', ['https://app.example']];
        yield 'tidied up' => [null, ' https://binnight.app , ,', ['https://binnight.app']];
        yield 'none at all, which allows no origin' => [null, null, []];
    }

    /** @param list<string> $expected */
    #[\PHPUnit\Framework\Attributes\DataProvider('environments')]
    public function testTheOriginsComeFromTheEnvironment(?string $cors, ?string $clerk, array $expected): void
    {
        $repository = \Illuminate\Support\Env::getRepository();
        $names = ['CORS_ALLOWED_ORIGINS' => $cors, 'CLERK_AUTHORIZED_PARTIES' => $clerk];
        $before = \array_map(static fn (string $name): ?string => \Illuminate\Support\Env::get($name), \array_keys($names));

        try {
            foreach ($names as $name => $value) {
                $value === null ? $repository->clear($name) : $repository->set($name, $value);
            }

            /** @var array{allowed_origins: list<string>} $config */
            $config = require \config_path('cors.php');

            self::assertSame($expected, $config['allowed_origins']);
        } finally {
            foreach (\array_keys($names) as $index => $name) {
                $before[$index] === null ? $repository->clear($name) : $repository->set($name, $before[$index]);
            }
        }
    }
}
