<?php

namespace Database\Factories;

use App\DocumentRequirement;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRule>
 */
class DocumentRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory()->state(['requirement' => DocumentRequirement::Conditional]),
            'dosage_form_id' => DosageForm::factory(),
            'category_id' => null,
            'effective_from' => fake()->date('Y-m-d'),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
