<?php

namespace Database\Factories;

use App\InvoiceStatus;
use App\InvoiceTerm;
use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'ticket_id' => fn (array $attributes): int => Contract::query()->findOrFail($attributes['contract_id'])->ticket_id,
            'number' => 'INV/'.now()->year.'/'.fake()->unique()->numerify('9###'),
            'term' => InvoiceTerm::DownPayment,
            'percent' => 50,
            'amount' => 15_000_000,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => InvoiceStatus::Issued,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid, 'paid_at' => now(), 'payment_type' => 'bank_transfer']);
    }
}
