<?php

namespace Database\Factories;

use App\Models\Stage;
use App\StagePhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('tahap_??????'),
            'name' => fake()->unique()->words(3, true),
            'phase' => fake()->randomElement(StagePhase::cases()),
            'sort_order' => fake()->numberBetween(20, 200),
            'sla_days' => fake()->numberBetween(1, 30),
            'waits_for_external' => false,
            'is_active' => true,
        ];
    }

    /** Tahap yang menunggu BPOM atau pihak luar sehingga tidak memakai SLA. */
    public function waitingForExternalParty(): static
    {
        return $this->state(['waits_for_external' => true, 'sla_days' => null]);
    }
}
