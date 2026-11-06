<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductNameCheckTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $quality;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
        $this->quality = User::factory()->create(['email' => 'qa@aisha.test']);
        $this->quality->assignRole('qa');
        $this->ticket = Ticket::factory()->create(['product_name' => 'Kapsul Jahe Merah']);
    }

    public function test_a_text_pdf_must_mention_the_product_name_when_the_document_type_requires_it(): void
    {
        $coa = $this->item(['checks_product_name' => true]);

        $this->upload($coa, $this->pdf('CERTIFICATE OF ANALYSIS - KAPSUL JAHE-MERAH, batch 01'));
        $this->assertSame(DocumentStatus::UnderReview, $coa->refresh()->status);

        $this->upload($coa, $this->pdf('Certificate of analysis - Sirup Temulawak'));
        $coa->refresh();
        $this->assertSame(DocumentStatus::NeedsRevision, $coa->status);
        $this->assertSame('Nama produk “Kapsul Jahe Merah” tidak ditemukan pada PDF.', $coa->revision_reason);
        $this->assertFalse($coa->document->currentVersion->validation_passed);
    }

    public function test_scanned_pdfs_other_formats_unchecked_types_and_shared_documents_are_not_matched(): void
    {
        $checked = $this->item(['checks_product_name' => true]);
        $this->upload($checked, UploadedFile::fake()->create('pindaian.pdf', 100, 'application/pdf'));
        $this->assertSame(DocumentStatus::UnderReview, $checked->refresh()->status);
        $this->upload($checked, UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'));
        $this->assertSame(DocumentStatus::UnderReview, $checked->refresh()->status);

        $unchecked = $this->item(['checks_product_name' => false]);
        $this->upload($unchecked, $this->pdf('Dokumen tanpa nama produk'));
        $this->assertSame(DocumentStatus::UnderReview, $unchecked->refresh()->status);

        $shared = $this->item(['checks_product_name' => true, 'scope' => DocumentScope::Company]);
        $this->upload($shared, $this->pdf('Sertifikat halal perusahaan'));
        $this->assertSame(DocumentStatus::UnderReview, $shared->refresh()->status);
    }

    public function test_admins_enable_the_product_name_check_per_document_type(): void
    {
        $admin = User::factory()->create(['email' => 'admin@aisha.test']);
        $admin->assignRole('admin');
        $type = DocumentType::factory()->create(['name' => 'CoA produk jadi', 'scope' => DocumentScope::Ticket, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'pic_role' => 'qa']);

        $this->actingAs($admin)->put("/master/document-types/{$type->id}", [
            'name' => 'CoA produk jadi', 'phase' => 'pra_registrasi', 'scope' => 'tiket', 'requirement' => 'wajib', 'pic_role' => 'qa',
            'verifier_role' => 'qa', 'requires_expiry_date' => false, 'max_age_months' => 6, 'checks_product_name' => true, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($type->refresh()->checks_product_name);
        $this->withoutVite()->get('/master?tab=document-types')->assertInertia(fn (Assert $page) => $page
            ->where('documentTypes', fn ($types): bool => collect($types)->firstWhere('id', $type->id)['checks_product_name'] === true));
    }

    /** @param array<string, mixed> $attributes */
    private function item(array $attributes): TicketDocument
    {
        $type = DocumentType::factory()->create([
            'phase' => StagePhase::PreRegistration, 'scope' => DocumentScope::Ticket, 'requirement' => DocumentRequirement::Required,
            'pic_role' => 'qa', 'requires_expiry_date' => false, 'max_age_months' => null, ...$attributes,
        ]);

        return $this->ticket->documents()->create(['document_type_id' => $type->id, 'phase' => $type->phase, 'requirement' => $type->requirement, 'status' => DocumentStatus::Missing]);
    }

    private function upload(TicketDocument $item, UploadedFile $file): void
    {
        $this->actingAs($this->quality)->post("/tickets/{$item->ticket_id}/documents/{$item->id}/versions", ['file' => $file, 'number' => 'DOC-1', 'issued_at' => '2026-10-01'])
            ->assertSessionHasNoErrors();
    }

    /** PDF satu halaman berisi teks, cukup untuk dibaca pengurai PDF. */
    private function pdf(string $text): UploadedFile
    {
        $stream = 'BT /F1 12 Tf 72 712 Td ('.addcslashes($text, '()\\').') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return UploadedFile::fake()->createWithContent('coa.pdf', $pdf);
    }
}
