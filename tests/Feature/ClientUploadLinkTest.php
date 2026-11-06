<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\UploadToken;
use App\Models\User;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientUploadLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->marketing = $this->createStaff('marketing');
        $this->ticket = Ticket::factory()->create(['client_id' => Client::factory()->create(['marketing_user_id' => $this->marketing->id, 'pic_name' => 'Nadia'])->id]);
    }

    public function test_marketing_creates_a_link_per_phase_that_is_stored_hashed_and_replaces_the_previous_link(): void
    {
        $old = UploadToken::factory()->create(['ticket_id' => $this->ticket->id, 'phase' => StagePhase::PreRegistration, 'created_by' => $this->marketing->id]);
        $otherPhase = UploadToken::factory()->create(['ticket_id' => $this->ticket->id, 'phase' => StagePhase::Registration, 'created_by' => $this->marketing->id]);

        $response = $this->actingAs($this->marketing)->from("/tickets/{$this->ticket->id}")
            ->post("/tickets/{$this->ticket->id}/upload-links", ['phase' => 'pra_registrasi', 'days' => 7])
            ->assertRedirect("/tickets/{$this->ticket->id}")->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Tautan unggah Pra-registrasi dibuat, berlaku sampai 12/11/2026 10:00.');

        $url = $response->getSession()->get('upload_link');
        $plain = Str::afterLast($url, '/unggah/');
        $this->assertSame(UploadToken::TOKEN_LENGTH, strlen($plain));
        $token = UploadToken::query()->latest('id')->first();
        $this->assertSame(UploadToken::hash($plain), $token->token_hash);
        $this->assertDatabaseMissing('upload_tokens', ['token_hash' => $plain]);
        $this->assertSame('2026-11-12 10:00:00', $token->expires_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($old->refresh()->revoked_at);
        $this->assertNull($otherPhase->refresh()->revoked_at);

        $entry = Activity::query()->where('event', 'upload_link.created')->sole();
        $this->assertSame($this->ticket->code, $entry->ticket_code);
        $this->assertSame(1, $entry->attribute_changes['attributes']['replaced_links']);
    }

    public function test_links_need_a_registration_phase_and_a_bounded_lifetime_and_only_marketing_or_regulatory_affairs_create_them(): void
    {
        $this->actingAs($this->marketing)->from("/tickets/{$this->ticket->id}")
            ->post("/tickets/{$this->ticket->id}/upload-links", ['phase' => 'awal', 'days' => 61])
            ->assertSessionHasErrors(['phase' => 'Fase harus pra-registrasi atau registrasi.', 'days' => 'Masa berlaku maksimal 60 hari.']);

        foreach (['qa', 'rnd', 'finance'] as $role) {
            $this->actingAs($this->createStaff($role, ['email' => "{$role}@aisha.test"]))
                ->post("/tickets/{$this->ticket->id}/upload-links", ['phase' => 'pra_registrasi', 'days' => 14])->assertForbidden();
        }

        $otherMarketing = $this->createStaff('marketing', ['email' => 'marketing2@aisha.test']);
        $this->actingAs($otherMarketing)->post("/tickets/{$this->ticket->id}/upload-links", ['phase' => 'pra_registrasi', 'days' => 14])->assertForbidden();

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->actingAs($regulatory)->post("/tickets/{$this->ticket->id}/upload-links", ['phase' => 'registrasi', 'days' => 14])->assertSessionHasNoErrors();
        $this->assertSame(1, UploadToken::query()->count());
    }

    public function test_the_client_page_shows_only_client_documents_of_the_ticket_and_phase_without_login(): void
    {
        $powerOfAttorney = $this->item('Surat kuasa', 'client', StagePhase::BothPhases);
        $trademark = $this->item('Sertifikat merek', 'client', StagePhase::PreRegistration);
        $this->item('Master formula', 'rnd', StagePhase::PreRegistration);
        $this->item('Desain kemasan', 'client', StagePhase::Registration);
        $otherTicket = Ticket::factory()->create();
        $otherTicket->documents()->create(['document_type_id' => $trademark->document_type_id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => DocumentStatus::Missing]);
        UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);

        $this->withoutVite()->get("/unggah/{$plain}")->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page->component('Client/UploadLink')
                ->where('ticket.code', $this->ticket->code)
                ->where('ticket.client_pic', 'Nadia')
                ->where('ticket.phase_label', 'Pra-registrasi')
                ->where('expires_at', '19/11/2026 10:00')
                ->has('documents', 2)
                ->where('documents.0.id', $trademark->id)
                ->where('documents.1.id', $powerOfAttorney->id)
                ->where('documents.0.upload_url', "/unggah/{$plain}/dokumen/{$trademark->id}"));
        $this->assertTrue(str_contains((string) $this->get("/unggah/{$plain}")->headers->get('Cache-Control'), 'no-store'));
    }

    public function test_unknown_malformed_expired_or_revoked_links_show_the_expired_page_without_ticket_data(): void
    {
        $this->item('Surat kuasa', 'client', StagePhase::PreRegistration);
        UploadToken::factory()->withToken($expired = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'expires_at' => now()->subMinute(), 'created_by' => $this->marketing->id]);
        UploadToken::factory()->withToken($revoked = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'revoked_at' => now(), 'created_by' => $this->marketing->id]);

        foreach ([$expired, $revoked, Str::random(UploadToken::TOKEN_LENGTH), 'pendek'] as $token) {
            $response = $this->withoutVite()->get("/unggah/{$token}")->assertStatus(410)
                ->assertInertia(fn (Assert $page) => $page->component('Client/UploadLinkExpired')->missing('ticket')->missing('documents'));
            $this->assertStringNotContainsString($this->ticket->code, $response->getContent());
        }
    }

    public function test_a_client_upload_becomes_a_validated_version_from_the_client_source(): void
    {
        $item = $this->item('Surat kuasa', 'client', StagePhase::PreRegistration, ['scope' => DocumentScope::Client]);
        $token = UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);

        $this->from("/unggah/{$plain}")->post("/unggah/{$plain}/dokumen/{$item->id}", $this->upload())
            ->assertRedirect("/unggah/{$plain}")->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Surat kuasa diterima dan sedang diverifikasi tim kami.');

        $item->refresh();
        $this->assertSame(DocumentStatus::UnderReview, $item->status);
        $version = $item->document->currentVersion;
        $this->assertSame(DocumentSource::Client, $version->source);
        $this->assertNull($version->uploaded_by);
        $this->assertSame($this->ticket->client_id, $item->document->client_id);
        $this->assertNotNull($token->refresh()->last_used_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'document.uploaded', 'source' => 'client', 'ticket_code' => $this->ticket->code, 'causer_id' => null]);

        $this->post("/unggah/{$plain}/dokumen/{$item->id}", $this->upload(['number' => '']))->assertSessionHasErrors(['number' => 'Nomor dokumen wajib diisi.']);
    }

    public function test_a_link_cannot_upload_staff_documents_other_tickets_approved_items_or_after_it_expires(): void
    {
        $staffItem = $this->item('Master formula', 'rnd', StagePhase::PreRegistration);
        $approved = $this->item('Surat kuasa', 'client', StagePhase::PreRegistration, [], DocumentStatus::Approved);
        $clientItem = $this->item('Sertifikat merek', 'client', StagePhase::PreRegistration);
        $otherTicketItem = Ticket::factory()->create()->documents()->create(['document_type_id' => $clientItem->document_type_id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => DocumentStatus::Missing]);
        UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id, 'expires_at' => now()->addHour()]);

        foreach ([$staffItem, $approved, $otherTicketItem] as $item) {
            $this->post("/unggah/{$plain}/dokumen/{$item->id}", $this->upload())->assertNotFound();
        }

        $this->travel(2)->hours();
        $this->post("/unggah/{$plain}/dokumen/{$clientItem->id}", $this->upload())->assertRedirect("/unggah/{$plain}");
        $this->assertNull($clientItem->refresh()->document_id);
    }

    public function test_links_of_a_ticket_that_is_no_longer_active_cannot_upload(): void
    {
        $item = $this->item('Surat kuasa', 'client', StagePhase::PreRegistration);
        UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);
        $this->ticket->update(['status' => TicketStatus::Completed]);

        $this->withoutVite()->get("/unggah/{$plain}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ticket.is_active', false)->where('documents.0.can_upload', false));
        $this->post("/unggah/{$plain}/dokumen/{$item->id}", $this->upload())->assertNotFound();
    }

    public function test_marketing_revokes_a_link_and_sees_active_links_on_the_ticket_page(): void
    {
        $token = UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);

        $this->actingAs($this->marketing)->withoutVite()->get("/tickets/{$this->ticket->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_upload_links', true)
                ->where('uploadLinks.default_days', 14)
                ->where('uploadLinks.active.0.phase_label', 'Pra-registrasi')
                ->where('uploadLinks.active.0.revoke_url', "/tickets/{$this->ticket->id}/upload-links/{$token->id}"));

        $this->delete("/tickets/{$this->ticket->id}/upload-links/{$token->id}")->assertSessionHas('status', 'Tautan unggah dicabut.');

        $this->assertSame($this->marketing->id, $token->refresh()->revoked_by);
        $this->assertDatabaseHas('activity_log', ['event' => 'upload_link.revoked', 'ticket_code' => $this->ticket->code]);
        $this->get("/unggah/{$plain}")->assertStatus(410);

        $quality = $this->createStaff('qa', ['email' => 'qa@aisha.test']);
        $this->actingAs($quality)->get("/tickets/{$this->ticket->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('uploadLinks', null)->where('can.manage_upload_links', false));
        $this->delete("/tickets/{$this->ticket->id}/upload-links/{$token->id}")->assertForbidden();
    }

    /** @param array<string, mixed> $typeAttributes */
    private function item(string $name, string $picRole, StagePhase $phase, array $typeAttributes = [], DocumentStatus $status = DocumentStatus::Missing): TicketDocument
    {
        $type = DocumentType::factory()->create([
            'name' => $name,
            'pic_role' => $picRole,
            'phase' => $phase,
            'scope' => DocumentScope::Ticket,
            'requirement' => DocumentRequirement::Required,
            'requires_expiry_date' => false,
            'max_age_months' => null,
            ...$typeAttributes,
        ]);

        return $this->ticket->documents()->create([
            'document_type_id' => $type->id,
            'phase' => $phase,
            'requirement' => $type->requirement,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function upload(array $overrides = []): array
    {
        return [
            'file' => UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf'),
            'number' => 'DOC/2026/001',
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
