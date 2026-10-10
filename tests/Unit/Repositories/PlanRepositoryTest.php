<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\NotificationType;
use App\Enums\Plan;
use App\Repositories\PlanRepository;
use App\Services\Plan\PlanLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanRepositoryTest extends TestCase
{
    public static function getPlans(): iterable
    {
        foreach (Plan::cases() as $plan) {
            yield $plan->name => [$plan];
        }
    }

    public static function getPlanUpgrades(): iterable
    {
        $plans = Plan::cases();
        \usort($plans, static fn (Plan $a, Plan $b): int => $a->tiers() <=> $b->tiers());

        foreach ($plans as $index => $plan) {
            if (isset($plans[$index + 1])) {
                yield \sprintf('%s to %s', $plan->name, $plans[$index + 1]->name) => [$plan, $plans[$index + 1]];
            }
        }
    }

    public function testEveryPlanHasItsOwnTier(): void
    {
        $tiers = \array_map(static fn (Plan $plan): int => $plan->tiers(), Plan::cases());

        self::assertSame(\count($tiers), \count(\array_unique($tiers)));
    }

    #[DataProvider('getPlans')]
    public function testEveryPlanHasUsableLimits(Plan $plan): void
    {
        $limits = new PlanRepository()->getLimits($plan);

        self::assertValidPlanValue($limits->houses);
        self::assertValidPlanValue($limits->binsPerHouse);
        self::assertValidPlanValue($limits->notificationsPerHouse);
        self::assertNotEmpty($limits->notificationTypes);
        self::assertTrue($limits->allowsNotificationType(NotificationType::SMS));
    }

    #[DataProvider('getPlanUpgrades')]
    public function testPlansNeverShrinkWhenUpgrading(Plan $lower, Plan $higher): void
    {
        $repository = new PlanRepository();
        $from = $repository->getLimits($lower);
        $to = $repository->getLimits($higher);

        self::assertValidPlanUpgrade($to->houses, $from->houses);
        self::assertValidPlanUpgrade($to->binsPerHouse, $from->binsPerHouse);
        self::assertValidPlanUpgrade($to->notificationsPerHouse, $from->notificationsPerHouse);
    }

    private static function assertValidPlanUpgrade(int $actual, int $previous, string $message = ''): void
    {
        self::assertThat(
            $actual,
            $previous === PlanLimits::UNLIMITED
                ? self::equalTo(PlanLimits::UNLIMITED)
                : self::logicalOr(
                    self::equalTo(PlanLimits::UNLIMITED),
                    self::greaterThanOrEqual($previous),
                ),
            $message,
        );
    }

    private static function assertValidPlanValue(int $actual, string $message = ''): void
    {
        self::assertThat(
            $actual,
            self::logicalOr(
                self::equalTo(PlanLimits::UNLIMITED),
                self::greaterThanOrEqual(1),
            ),
            $message,
        );
    }
}
