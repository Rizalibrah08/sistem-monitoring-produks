<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\TemplateKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
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
            'kind' => fake()->randomElement(TemplateKind::cases()),
            'is_active' => true,
        ];
    }
}
