<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Enums\NotificationType;
use App\Enums\Plan;
use App\Http\Resources\MeResource;
use App\Services\Plan\PlanLimits;
use App\Services\Plan\PlanSummary;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class MeResourceTest extends TestCase
{
    public function testLimitsAreShownAsTheyAre(): void
    {
        $summary = new PlanSummary(Plan::PLUS, new PlanLimits(1, 5, 4, [NotificationType::SMS]));

        self::assertSame([
            'limits' => [
                'bins_per_house' => 5,
                'houses' => 1,
                'notification_types' => ['sms'],
                'notifications_per_house' => 4,
            ],
            'plan' => 'plus',
        ], new MeResource($summary)->toArray(Request::create('/')));
    }

    public function testNoLimitIsNullRatherThanMinusOne(): void
    {
        $summary = new PlanSummary(Plan::UNLIMITED, new PlanLimits(PlanLimits::UNLIMITED, PlanLimits::UNLIMITED, PlanLimits::UNLIMITED, []));

        self::assertSame([
            'limits' => [
                'bins_per_house' => null,
                'houses' => null,
                'notification_types' => [],
                'notifications_per_house' => null,
            ],
            'plan' => 'unlimited',
        ], new MeResource($summary)->toArray(Request::create('/')));
    }
}
