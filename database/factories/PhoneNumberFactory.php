<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PhoneNumberStatus;
use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PhoneNumber> */
final class PhoneNumberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => '+614' . $this->faker->unique()->numerify('########'),
            'status' => PhoneNumberStatus::PENDING->value,
        ];
    }

    public function verified(): self
    {
        return $this->state([
            'status' => PhoneNumberStatus::VERIFIED->value,
            'verified_at' => \now(),
        ]);
    }
}
