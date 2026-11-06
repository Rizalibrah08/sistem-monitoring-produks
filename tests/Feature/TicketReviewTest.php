<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketReview;
use App\Models\User;
use App\ReviewTrigger;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->marketing = $this->createStaff('marketing');
        $this->client = Client::factory()->create(['marketing_user_id' => $this->marketing->id]);
    }

    public function test_a_new_version_of_a_shared_document_flags_the_other_active_tickets_that_use_it(): void
    {
        $type = $this->type('Surat kuasa', DocumentScope::Client);
        $first = $this->itemFor(Ticket::factory()->create(['client_id' => $this->client->id]), $type);
        $this->actingAs($this->marketing)->post($this->uploadUrl($first), $this->upload())->assertSessionHasNoErrors();
        $document = $first->refresh()->document;
        $second = $this->itemFor(Ticket::factory()->create(['client_id' => $this->client->id]), $type, $document);
        $completed = $this->itemFor(Ticket::factory()->create(['client_id' => $this->client->id, 'status' => TicketStatus::Completed]), $type, $document);
        $this->assertSame(0, TicketReview::query()->count());

        $this->post($this->uploadUrl($second), $this->upload(['number' => 'SK-2']))->assertSessionHasNoErrors();

        $review = TicketReview::query()->sole();
        $this->assertSame($first->ticket_id, $review->ticket_id);
        $this->assertSame(ReviewTrigger::NewVersion, $review->trigger);
        $this->assertSame('Dokumen bersama Surat kuasa mendapat versi 2.', $review->message);
        $this->assertSame($document->refresh()->currentVersion->id, $review->document_version_id);
        $this->assertSame(0, $completed->ticket->reviews()->count());
        $this->assertDatabaseHas('activity_log', ['event' => 'ticket.review_flagged', 'ticket_code' => $first->ticket->code, 'source' => 'system']);
    }

    public function test_new_versions_of_ticket_documents_do_not_flag_other_tickets(): void
    {
        $item = $this->itemFor(Ticket::factory()->create(['client_id' => $this->client->id]), $this->type('Master formula', DocumentScope::Ticket));

        $this->actingAs($this->marketing)->post($this->uploadUrl($item), $this->upload())->assertSessionHasNoErrors();
        $this->post($this->uploadUrl($item), $this->upload(['number' => 'MF-2']))->assertSessionHasNoErrors();

        $this->assertSame(0, TicketReview::query()->count());
    }

    public function test_the_daily_command_flags_tickets_whose_shared_documents_expire_within_thirty_days_only_once(): void
    {
        $halal = $this->sharedDocument($this->type('Sertifikat halal', DocumentScope::Company, ['requires_expiry_date' => true]), '2026-12-05');
        $batch = $this->sharedDocument($this->type('Sistem penomoran batch', DocumentScope::Company, ['requires_expiry_date' => true]), '2026-12-06');
        $ticket = Ticket::factory()->create(['client_id' => $this->client->id]);
        $this->itemFor($ticket, $halal->documentType, $halal);
        $this->itemFor($ticket, $batch->documentType, $batch);
        $this->itemFor(Ticket::factory()->create(['status' => TicketStatus::Rejected]), $halal->documentType, $halal);

        $this->artisan('documents:flag-expiring')->expectsOutput('1 tiket ditandai perlu ditinjau.')->assertSuccessful();
        $this->artisan('documents:flag-expiring')->expectsOutput('0 tiket ditandai perlu ditinjau.')->assertSuccessful();

        $review = $ticket->reviews()->sole();
        $this->assertSame(ReviewTrigger::ExpiringSoon, $review->trigger);
        $this->assertSame('Dokumen bersama Sertifikat halal berlaku sampai 05/12/2026.', $review->message);

        $review->update(['resolved_at' => now()]);
        $this->travel(1)->day();
        $this->artisan('documents:flag-expiring')->expectsOutput('1 tiket ditandai perlu ditinjau.');
        $this->assertSame(2, $ticket->reviews()->count());
        $this->assertSame($batch->id, $ticket->reviews()->latest('id')->first()->document_id);
    }

    public function test_regulatory_affairs_marks_a_ticket_as_reviewed(): void
    {
        $ticket = Ticket::factory()->create(['client_id' => $this->client->id]);
        TicketReview::factory()->count(2)->create(['ticket_id' => $ticket->id]);

        $this->actingAs($this->marketing)->post("/tickets/{$ticket->id}/reviews/resolve")->assertForbidden();

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->actingAs($regulatory)->from("/tickets/{$ticket->id}")->post("/tickets/{$ticket->id}/reviews/resolve")
            ->assertRedirect("/tickets/{$ticket->id}")->assertSessionHas('status', 'Tiket ditandai sudah ditinjau.');

        $this->assertSame(0, $ticket->reviews()->open()->count());
        $this->assertSame([$regulatory->id], $ticket->reviews()->pluck('resolved_by')->unique()->values()->all());
        $entry = Activity::query()->where('event', 'ticket.review_resolved')->sole();
        $this->assertSame(2, $entry->attribute_changes['attributes']['resolved_reviews']);
    }

    public function test_the_ticket_pages_show_which_tickets_need_review(): void
    {
        $flagged = Ticket::factory()->create(['client_id' => $this->client->id]);
        $calm = Ticket::factory()->create(['client_id' => $this->client->id]);
        TicketReview::factory()->create(['ticket_id' => $flagged->id]);
        TicketReview::factory()->create(['ticket_id' => $calm->id, 'resolved_at' => now()]);
        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);

        $this->actingAs($regulatory)->withoutVite()->get('/tickets')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tickets.data', fn ($rows): bool => collect($rows)->firstWhere('id', $flagged->id)['needs_review'] === true
                    && collect($rows)->firstWhere('id', $calm->id)['needs_review'] === false));

        $this->get("/tickets/{$flagged->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.message', 'Dokumen bersama Surat kuasa mendapat versi 2.')
                ->where('can.resolve_reviews', true)
                ->where('urls.resolve_reviews', "/tickets/{$flagged->id}/reviews/resolve"));
        $this->get("/tickets/{$calm->id}")->assertInertia(fn (Assert $page) => $page->has('reviews', 0));
    }

    /** @param array<string, mixed> $attributes */
    private function type(string $name, DocumentScope $scope, array $attributes = []): DocumentType
    {
        return DocumentType::factory()->create([
            'name' => $name,
            'scope' => $scope,
            'pic_role' => 'marketing',
            'phase' => StagePhase::PreRegistration,
            'requirement' => DocumentRequirement::Required,
            'requires_expiry_date' => false,
            'max_age_months' => null,
            ...$attributes,
        ]);
    }

    private function sharedDocument(DocumentType $type, string $expiresAt): Document
    {
        $document = Document::factory()->create(['document_type_id' => $type->id, 'scope' => $type->scope, 'ticket_id' => null]);
        $version = $document->versions()->create([
            'version' => 1, 'path' => "documents/{$document->id}/v1.pdf", 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
            'checksum' => str_repeat('a', 64), 'number' => 'DOC-1', 'expires_at' => $expiresAt, 'source' => 'internal', 'validation_passed' => true, 'validation_errors' => [],
        ]);
        $document->update(['current_version_id' => $version->id]);

        return $document->load('documentType');
    }

    private function itemFor(Ticket $ticket, DocumentType $type, ?Document $document = null): TicketDocument
    {
        return $ticket->documents()->create([
            'document_type_id' => $type->id,
            'phase' => $type->phase,
            'requirement' => $type->requirement,
            'document_id' => $document?->id,
            'status' => $document === null ? DocumentStatus::Missing : DocumentStatus::UnderReview,
        ]);
    }

    private function uploadUrl(TicketDocument $item): string
    {
        return "/tickets/{$item->ticket_id}/documents/{$item->id}/versions";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function upload(array $overrides = []): array
    {
        return [
            'file' => UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf'),
            'number' => 'SK-1',
            'issued_at' => '2026-10-01',
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
