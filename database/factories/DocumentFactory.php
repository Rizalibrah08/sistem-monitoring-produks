<?php

namespace Database\Factories;

use App\DocumentScope;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory(),
            'scope' => DocumentScope::Ticket,
            'ticket_id' => Ticket::factory(),
            'client_id' => null,
            'material_id' => null,
            'current_version_id' => null,
        ];
    }
}
