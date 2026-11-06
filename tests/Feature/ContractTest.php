<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\SampleApproval;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\UploadToken;
use App\Models\User;
use App\StagePhase;
use App\TemplateKind;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private Ticket $ticket;

    private DocumentTemplate $template;

    private bool $gotenbergDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*/forms/libreoffice/convert' => fn () => $this->gotenbergDown ? Http::response('down', 503) : Http::response('%PDF-1.7 kontrak', 200)]);
        $this->travelTo('2026-11-05 10:00:00');
        $this->marketing = $this->createStaff('marketing');
        $sampleStage = Stage::query()->where('code', 'pengembangan_sampel')->sole();
        $this->ticket = Ticket::factory()->create([
            'client_id' => Client::factory()->create(['marketing_user_id' => $this->marketing->id, 'name' => 'Nusantara Herbal'])->id,
            'product_name' => 'Kapsul Jahe Merah',
            'current_stage_id' => $sampleStage->id,
        ]);
        $this->ticket->stageLogs()->create(['stage_id' => $sampleStage->id, 'started_at' => now()->subDays(5)]);
        SampleApproval::factory()->approved()->create(['ticket_id' => $this->ticket->id, 'recorded_by' => $this->marketing->id]);
        $this->template = $this->contractTemplate();
    }

    public function test_marketing_creates_a_numbered_draft_contract_with_filled_word_and_pdf_files(): void
    {
        $this->actingAs($this->marketing)->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))
            ->assertRedirect("/tickets/{$this->ticket->id}")->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Kontrak KTR/2026/0001 dibuat. Berkasnya sedang disiapkan.');

        $contract = $this->ticket->contract()->sole();
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame(30_000_000, $contract->service_value);
        $this->assertSame(15_000_000, $contract->dpAmount());
        $this->assertSame($this->template->versions()->value('id'), $contract->template_version_id);
        $this->assertNull($contract->generation_error);
        Storage::disk('local')->assertExists($contract->word_path);
        $this->assertSame('%PDF-1.7 kontrak', Storage::disk('local')->get($contract->pdf_path));
        $this->assertStringContainsString('Nusantara Herbal', $this->wordText($contract->word_path));
        $this->assertStringContainsString('KTR/2026/0001', $this->wordText($contract->word_path));
        $this->assertStringContainsString('15.000.000', $this->wordText($contract->word_path));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/forms/libreoffice/convert'));
        $this->assertDatabaseHas('activity_log', ['event' => 'contract.created', 'ticket_code' => $this->ticket->code]);
    }

    public function test_contracts_need_an_approved_sample_one_per_ticket_valid_terms_and_a_template_with_a_file(): void
    {
        $pending = Ticket::factory()->create(['client_id' => $this->ticket->client_id]);
        $this->actingAs($this->marketing)->from('/contracts')->post('/contracts', $this->terms(['ticket_id' => $pending->id]))
            ->assertSessionHasErrors(['ticket_id' => 'Kontrak dibuat setelah sampel disetujui klien.']);

        $other = Ticket::factory()->create();
        $this->post('/contracts', $this->terms(['ticket_id' => $other->id]))->assertSessionHasErrors(['ticket_id' => 'Pilih tiket klien yang Anda tangani.']);

        $empty = DocumentTemplate::factory()->create(['kind' => TemplateKind::Contract]);
        $this->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id, 'document_template_id' => $empty->id, 'dp_percent' => 100]))
            ->assertSessionHasErrors(['document_template_id' => 'Templat kontrak belum memiliki berkas.', 'dp_percent' => 'Persentase DP maksimal 99% agar ada pelunasan.']);

        $this->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertSessionHasNoErrors();
        $this->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertSessionHasErrors(['ticket_id' => 'Tiket ini sudah memiliki kontrak atau tidak aktif.']);
        $this->assertSame(1, Contract::query()->count());

        $this->actingAs($this->createStaff('ra', ['email' => 'ra@aisha.test']))->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertForbidden();
    }

    public function test_the_word_file_is_kept_when_the_pdf_conversion_fails(): void
    {
        $this->gotenbergDown = true;

        $this->actingAs($this->marketing)->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertSessionHasNoErrors();

        $contract = $this->ticket->contract()->sole();
        Storage::disk('local')->assertExists($contract->word_path);
        $this->assertNull($contract->pdf_path);
        $this->assertSame('PDF gagal dibuat; berkas Word tetap dapat diunduh.', $contract->generation_error);
        $this->post("/contracts/{$contract->id}/send")->assertSessionHasNoErrors();
        $this->assertSame(ContractStatus::Sent, $contract->refresh()->status);
    }

    public function test_draft_terms_can_change_with_every_change_logged_but_sent_contracts_are_locked(): void
    {
        $this->actingAs($this->marketing)->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertSessionHasNoErrors();
        $contract = $this->ticket->contract()->sole();

        $this->put("/contracts/{$contract->id}", $this->terms(['dp_percent' => 40, 'service_value' => 25_000_000]))->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame(10_000_000, $contract->dpAmount());
        $this->assertStringContainsString('10.000.000', $this->wordText($contract->word_path));
        $entry = Activity::query()->where('event', 'contract.updated')->sole();
        $this->assertSame(50, $entry->attribute_changes['old']['dp_percent']);
        $this->assertSame(40, $entry->attribute_changes['attributes']['dp_percent']);

        $this->post("/contracts/{$contract->id}/send")->assertSessionHasNoErrors();
        $this->from("/tickets/{$this->ticket->id}")->put("/contracts/{$contract->id}", $this->terms(['dp_percent' => 30]))
            ->assertSessionHasErrors(['service_value' => 'Hanya kontrak berstatus draf yang dapat diubah.']);
    }

    public function test_the_client_signs_through_the_link_and_marketing_confirmation_opens_pre_registration(): void
    {
        $this->actingAs($this->marketing)->post('/contracts', $this->terms(['ticket_id' => $this->ticket->id]))->assertSessionHasNoErrors();
        $contract = $this->ticket->contract()->sole();
        UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);

        $this->get("/unggah/{$plain}/kontrak")->assertNotFound();
        $this->post("/contracts/{$contract->id}/send")->assertSessionHas('status');
        $this->post("/contracts/{$contract->id}/confirm")->assertSessionHasErrors(['contract' => 'Kontrak bertanda tangan belum diunggah.']);

        $this->withoutVite()->get("/unggah/{$plain}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('contract.number', 'KTR/2026/0001')->where('contract.can_sign', true));
        $this->get("/unggah/{$plain}/kontrak")->assertOk()->assertDownload('Kontrak KTR-2026-0001.pdf');
        $this->post("/unggah/{$plain}/kontrak", ['file' => UploadedFile::fake()->create('ttd.exe', 10)])
            ->assertSessionHasErrors(['file' => 'Kontrak bertanda tangan harus berupa PDF, JPG, atau PNG.']);
        $this->from("/unggah/{$plain}")->post("/unggah/{$plain}/kontrak", ['file' => UploadedFile::fake()->create('kontrak-ttd.pdf', 300, 'application/pdf')])
            ->assertRedirect("/unggah/{$plain}")->assertSessionHas('status', 'Kontrak bertanda tangan diterima dan menunggu konfirmasi tim kami.');

        $contract->refresh();
        $this->assertSame(ContractStatus::Signed, $contract->status);
        $this->assertSame('kontrak-ttd.pdf', $contract->signed_original_name);
        $this->assertDatabaseHas('activity_log', ['event' => 'contract.signed_uploaded', 'source' => 'client', 'causer_id' => null]);

        $this->actingAs($this->marketing)->post("/contracts/{$contract->id}/confirm")->assertSessionHas('status', 'Kontrak KTR/2026/0001 aktif. Fase pra-registrasi dibuka.');

        $contract->refresh();
        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertSame($this->marketing->id, $contract->confirmed_by);
        $this->ticket->refresh();
        $this->assertSame(StagePhase::PreRegistration, $this->ticket->phase);
        $this->assertSame('dokumen_pra_registrasi', $this->ticket->currentStage->code);
        $this->assertSame(0, $this->ticket->stageLogs()->whereNull('finished_at')->where('stage_id', '!=', $this->ticket->current_stage_id)->count());
        $this->assertTrue(Activity::query()->where('event', 'contract.confirmed')->sole()->attribute_changes['attributes']['pre_registration_opened']);
        $this->post("/unggah/{$plain}/kontrak", ['file' => UploadedFile::fake()->create('lagi.pdf', 10, 'application/pdf')])->assertNotFound();
    }

    public function test_marketing_uploads_the_signed_copy_for_the_client_and_files_are_limited_to_staff_who_see_the_ticket(): void
    {
        $contract = Contract::factory()->status(ContractStatus::Sent)->create(['ticket_id' => $this->ticket->id, 'document_template_id' => $this->template->id, 'created_by' => $this->marketing->id, 'pdf_path' => 'contracts/x/k.pdf', 'generated_at' => now()]);
        Storage::disk('local')->put('contracts/x/k.pdf', '%PDF');

        $this->actingAs($this->marketing)->post("/contracts/{$contract->id}/signed", ['file' => UploadedFile::fake()->create('ttd.jpg', 100, 'image/jpeg')])->assertSessionHasNoErrors();
        $this->assertSame(ContractStatus::Signed, $contract->refresh()->status);
        $this->get("/contracts/{$contract->id}/files/signed")->assertOk()->assertDownload('ttd.jpg');

        $otherMarketing = $this->createStaff('marketing', ['email' => 'marketing2@aisha.test']);
        $this->actingAs($otherMarketing)->get("/contracts/{$contract->id}/files/pdf")->assertForbidden();
        $this->post("/contracts/{$contract->id}/confirm")->assertForbidden();

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->actingAs($regulatory)->get("/contracts/{$contract->id}/files/pdf")->assertOk();
        $this->post("/contracts/{$contract->id}/confirm")->assertForbidden();
    }

    public function test_the_contract_pages_show_contracts_and_eligible_tickets(): void
    {
        $contracted = Ticket::factory()->create(['client_id' => $this->ticket->client_id]);
        Contract::factory()->create(['ticket_id' => $contracted->id, 'document_template_id' => $this->template->id, 'number' => 'KTR/2026/0007']);
        Contract::factory()->create(['document_template_id' => $this->template->id]);

        $this->actingAs($this->marketing)->withoutVite()->get('/contracts')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Contracts', false)
                ->has('contracts.data', 1)
                ->where('contracts.data.0.number', 'KTR/2026/0007')
                ->where('createOptions.tickets.0.value', $this->ticket->id)
                ->has('createOptions.tickets', 1)
                ->where('createOptions.templates.0.value', $this->template->id)
                ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'contracts' && $item['available'] === true)));

        $this->get("/tickets/{$this->ticket->id}")->assertInertia(fn (Assert $page) => $page->where('contract', null)->where('can.create_contract', true));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terms(array $overrides = []): array
    {
        return ['document_template_id' => $this->template->id, 'service_value' => 30_000_000, 'dp_percent' => 50, 'payment_due_days' => 7, ...$overrides];
    }

    private function contractTemplate(): DocumentTemplate
    {
        $word = new PhpWord;
        $word->addSection()->addText('Kontrak ${nomor_kontrak} untuk ${nama_klien}, DP Rp${nilai_dp}.');
        $path = tempnam(sys_get_temp_dir(), 'kontrak');
        IOFactory::createWriter($word, 'Word2007')->save($path);
        Storage::disk('local')->put('templates/kontrak.docx', (string) file_get_contents($path));
        $template = DocumentTemplate::factory()->create(['name' => 'Kontrak maklon', 'kind' => TemplateKind::Contract]);
        $template->versions()->create(['version' => 1, 'path' => 'templates/kontrak.docx', 'original_name' => 'kontrak.docx', 'size' => 1000, 'checksum' => str_repeat('a', 64), 'placeholders' => ['nama_klien', 'nilai_dp', 'nomor_kontrak'], 'uploaded_by' => $this->marketing->id]);

        return $template;
    }

    private function wordText(string $path): string
    {
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($path));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags($xml);
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
