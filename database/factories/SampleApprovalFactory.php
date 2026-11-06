<?php

namespace Database\Factories;

use App\Models\SampleApproval;
use App\Models\Ticket;
use App\Models\User;
use App\SampleDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleApproval>
 */
class SampleApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'revision' => 1,
            'decision' => SampleDecision::NeedsRevision,
            'note' => 'Rasa perlu disesuaikan.',
            'recorded_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['decision' => SampleDecision::Approved, 'note' => 'Sampel sesuai.']);
    }
}
