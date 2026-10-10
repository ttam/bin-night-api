<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\LoadActiveEntities;
use App\Services\Plan\Interfaces\AttachActiveEntitiesInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class LoadActiveEntitiesTest extends TestCase
{
    public function testAttachesActiveEntitiesBeforePassingTheRequestOn(): void
    {
        $request = Request::create('/api/bins');
        $attached = false;

        $attachActiveEntities = $this->createMock(AttachActiveEntitiesInterface::class);
        $attachActiveEntities->expects($this->once())->method('attachToRequest')->with($request)
            ->willReturnCallback(static function () use (&$attached): void {
                $attached = true;
            });

        $response = new LoadActiveEntities($attachActiveEntities)->handle(
            $request,
            static function () use (&$attached): Response {
                return new Response($attached ? 'attached' : 'not attached');
            },
        );

        self::assertSame('attached', $response->getContent());
    }
}
