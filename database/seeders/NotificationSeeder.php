<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\House;
use App\Models\Notification;
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
                'type' => 'SMS',
                'target' => '+61400000000',
                'lead_days' => 0,
                'send_time' => '11:00',
            ],
            [
                'type' => 'SMS',
                'target' => '+61400000001',
                'lead_days' => 1,
                'send_time' => '18:00',
            ],
            [
                'type' => 'SMS',
                'target' => '+61411111111',
                'lead_days' => 0,
                'send_time' => '13:00',
            ],
        ];

        Notification::factory()
            ->for($user)
            ->for($house)
            ->state(new Sequence(...$notifications))
            ->count(\count($notifications))
            ->create();
    }
}
