<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Cycle;
use Illuminate\Database\Eloquent\Factories\Factory;

final class BinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2),
            'cycle' => $this->faker->randomElement(Cycle::cases()),
            'start_date' => $this->faker->date('Y-m-d'),
            'lid_color' => $this->faker->hexColor(),
            'bin_color' => $this->faker->hexColor(),
        ];
    }
}
