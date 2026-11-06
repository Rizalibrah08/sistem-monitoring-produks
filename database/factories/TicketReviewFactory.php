<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Ticket;
use App\Models\TicketReview;
use App\ReviewTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketReview>
 */
class TicketReviewFactory extends Factory
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
            'document_id' => Document::factory(),
            'document_version_id' => fn (array $attributes): int => DocumentVersion::query()->create([
                'document_id' => $attributes['document_id'], 'version' => 1, 'path' => 'documents/v1.pdf', 'original_name' => 'v1.pdf',
                'mime_type' => 'application/pdf', 'size' => 100, 'checksum' => str_repeat('a', 64), 'number' => 'DOC-1',
                'source' => 'internal', 'validation_passed' => true, 'validation_errors' => [],
            ])->id,
            'trigger' => ReviewTrigger::NewVersion,
            'message' => 'Dokumen bersama Surat kuasa mendapat versi 2.',
        ];
    }
}
