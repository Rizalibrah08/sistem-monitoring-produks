<?php

namespace Database\Factories;

use App\Models\DosageForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DosageForm>
 */
class DosageFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
