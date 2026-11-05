<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateVersion>
 */
class TemplateVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_template_id' => DocumentTemplate::factory(),
            'version' => 1,
            'path' => 'templates/'.fake()->uuid().'.docx',
            'original_name' => 'templat.docx',
            'size' => fake()->numberBetween(1000, 50000),
            'checksum' => hash('sha256', fake()->uuid()),
            'placeholders' => ['nama_klien'],
            'notes' => null,
            'uploaded_by' => User::factory(),
        ];
    }
}
