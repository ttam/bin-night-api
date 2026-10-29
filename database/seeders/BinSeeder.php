<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\House;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

final class BinSeeder extends Seeder
{
    public function run(): void
    {
        $house = House::query()->first();
        $user = User::query()->firstWhere('email', 'admin@binnight.app');

        $bins = [
            [
                'name' => 'Rubbish',
                'cycle' => 'weekly',
                'start_date' => '2026-03-25',
                'lid_color' => '00dd00',
                'bin_color' => '00dd00',
            ],
            [
                'name' => 'Recycling',
                'cycle' => 'fortnightly',
                'start_date' => '2026-04-01',
                'lid_color' => 'ffff00',
                'bin_color' => '0000ff',
            ],
            [
                'name' => 'Food & Garden',
                'cycle' => 'fortnightly',
                'start_date' => '2026-03-25',
                'lid_color' => 'dd0000',
                'bin_color' => '00dd00',
            ],
        ];

        Bin::factory()
            ->for($user)
            ->for($house)
            ->state(new Sequence(...$bins))
            ->count(\count($bins))
            ->create();
    }
}
