<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\Me;

use App\Auth\AuthenticatedUser;
use App\Enums\NotificationType;
use App\Enums\Plan;
use App\Http\Controllers\Api\Me\ShowController;
use App\Models\User;
use App\Repositories\Interfaces\PlanRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Plan\PlanLimits;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ShowControllerTest extends TestCase
{
    public function testShowsTheUsersPlanAndItsLimits(): void
    {
        $limits = new PlanLimits(1, 5, 5, [NotificationType::SMS]);
        $request = Request::create('/api/v1/me')->setUserResolver(static fn (): AuthenticatedUser => new AuthenticatedUser(['sub' => 'user_111']));

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())->method('getUserFromExternalId')->with('user_111')
            ->willReturn(new User()->setAttribute('plan', Plan::PLUS->value));

        $plans = $this->createMock(PlanRepositoryInterface::class);
        $plans->expects($this->once())->method('getLimits')->with(Plan::PLUS)->willReturn($limits);

        $summary = new ShowController()($request, $plans, $users)->resource;

        self::assertSame(Plan::PLUS, $summary->plan);
        self::assertSame($limits, $summary->limits);
    }
}
