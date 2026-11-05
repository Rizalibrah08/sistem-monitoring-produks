<?php

namespace Database\Factories;

use App\DocumentRequirement;
use App\DocumentScope;
use App\Models\DocumentType;
use App\StagePhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'phase' => fake()->randomElement([StagePhase::PreRegistration, StagePhase::Registration, StagePhase::BothPhases]),
            'scope' => DocumentScope::Ticket,
            'requirement' => DocumentRequirement::Required,
            'pic_role' => fake()->randomElement(DocumentType::PIC_ROLES),
            'verifier_role' => null,
            'requires_expiry_date' => false,
            'max_age_months' => null,
            'is_active' => true,
        ];
    }
}
