<?php

namespace Database\Factories;

use App\ContractStatus;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\TemplateKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
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
            'number' => 'KTR/'.now()->year.'/'.fake()->unique()->numerify('9###'),
            'service_value' => 30_000_000,
            'dp_percent' => Contract::DEFAULT_DP_PERCENT,
            'payment_due_days' => Contract::DEFAULT_PAYMENT_DUE_DAYS,
            'status' => ContractStatus::Draft,
            'document_template_id' => DocumentTemplate::factory()->state(['kind' => TemplateKind::Contract]),
            'created_by' => User::factory(),
        ];
    }

    public function status(ContractStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
