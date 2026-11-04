<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\House;
use App\Models\Notification;
use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $house = House::query()->first();
        $user = User::query()->firstWhere('email', 'admin@binnight.app');

        $notifications = [
            [
                'type' => NotificationType::SMS,
                'name' => 'Marty\'s Phone',
                'number' => '+61400000000',
                'lead_days' => 0,
                'send_time' => '11:00:00',
            ],
            [
                'type' => NotificationType::SMS,
                'name' => 'Doc\'s Phone',
                'number' => '+61400000001',
                'lead_days' => 1,
                'send_time' => '18:00:00',
            ],
            [
                'type' => NotificationType::SMS,
                'name' => 'Biff\'s Phone',
                'number' => '+61411111111',
                'lead_days' => 0,
                'send_time' => '13:00:00',
            ],
        ];

        // Seeded numbers are already verified, so the local app can use them straight away.
        $phoneNumbers = PhoneNumber::factory()
            ->for($user)
            ->verified()
            ->state(new Sequence(...\array_map(static fn (array $notification): array => ['number' => $notification['number']], $notifications)))
            ->count(\count($notifications))
            ->create();

        foreach ($notifications as $index => $values) {
            unset($values['number']);

            Notification::factory()
                ->for($user)
                ->for($house)
                ->for($phoneNumbers[$index])
                ->state($values)
                ->create();
        }
    }
}
