<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

final class HouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2),
            'bin_night' => $this->faker->randomElement(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            'timezone' => $this->faker->randomElement(\timezone_identifiers_list()),
        ];
    }
}
