<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'admin@binnight.app'], [
            'name' => 'Admin',
            'password' => \bcrypt('admin'),
        ]);
    }
}
