<?php

namespace Database\Factories;

use App\CriterionType;
use App\Models\Criterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Criterion>
 */
class CriterionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'queue' => fake()->randomElement(Criterion::QUEUES),
            'code' => fake()->randomElement(Criterion::CODES),
            'name' => fake()->words(3, true),
            'weight' => 0.25,
            'type' => fake()->randomElement(CriterionType::cases()),
        ];
    }
}
