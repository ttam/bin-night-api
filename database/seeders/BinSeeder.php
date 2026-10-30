<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Cycle;
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
                'cycle' => Cycle::WEEKLY->value,
                'start_date' => '2026-03-25',
                'lid_color' => 'bf001f',
                'bin_color' => '47654e',
            ],
            [
                'name' => 'Recycling',
                'cycle' => Cycle::FORTNIGHTLY->value,
                'start_date' => '2026-04-01',
                'lid_color' => 'ffc700',
                'bin_color' => '0054a9',
            ],
            [
                'name' => 'Food & Garden',
                'cycle' => Cycle::FORTNIGHTLY->value,
                'start_date' => '2026-03-25',
                'lid_color' => '00cb7f',
                'bin_color' => '7c313a',
            ],
            [
                'name' => 'Glass',
                'cycle' => Cycle::FOUR_WEEKLY->value,
                'start_date' => '2026-03-25',
                'lid_color' => '7432b8',
                'bin_color' => '445348',
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
