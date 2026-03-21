<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\House;
use App\Models\User;
use Illuminate\Database\Seeder;

final class HouseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstWhere('email', 'admin@binnight.app');

        House::factory()->for($user)->create([
            'name' => 'Example',
            'bin_night' => 'wednesday',
            'timezone' => 'Australia/Melbourne',
        ]);
    }
}
