<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Client;
use App\Models\DosageForm;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRD-'.now()->year.'-'.fake()->unique()->numerify('9###'),
            'client_id' => Client::factory(),
            'product_name' => 'Kapsul '.fake()->word(),
            'dosage_form_id' => DosageForm::factory(),
            'category_id' => Category::factory(),
            'phase' => StagePhase::Initial,
            'current_stage_id' => null,
            'status' => TicketStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
