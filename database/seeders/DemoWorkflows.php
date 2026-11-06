<?php

namespace Database\Seeders;

use App\Actions\ConfirmContract;
use App\Actions\RecordSampleDecision;
use App\Actions\ReviewTicketDocument;
use App\Actions\SaveContract;
use App\Actions\StoreDocumentVersion;
use App\Actions\StoreSignedContract;
use App\Actions\SyncTicketMaterials;
use App\ContractStatus;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\UploadToken;
use App\Models\User;
use App\PaymentStatus;
use App\ReviewDecision;
use App\ReviewLevel;
use App\Services\BpomWorkflow;
use App\Services\MidtransSnap;
use App\Services\RegistrationCompletion;
use App\StagePhase;
use App\TemplateKind;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Menjalankan tiket demo lewat aksi aplikasi yang sebenarnya sehingga log audit, notifikasi,
 * SLA, tagihan, dan antrean prioritas terbentuk seperti pemakaian nyata:
 *
 * - Kapsul Jahe Merah: revisi sampel pertama, menunggu keputusan klien.
 * - Sirup Temulawak: sampel disetujui, kontrak dikirim, dan tautan unggah klien siap dicoba.
 * - Serbuk Kunyit Asam: kontrak aktif, DP lewat jatuh tempo, dokumen pra-registrasi sebagian
 *   (menunggu QA/QC, menunggu RA, dan dikembalikan), bahan baku dipilih, tahap terlambat SLA.
 * - Tablet Sambiloto: pra-registrasi selesai, LHU sesuai, registrasi diajukan, TD1 terbuka.
 * - Kapsul Kunyit Putih: NIE terbit, tiket selesai, DP dan pelunasan lunas lewat Midtrans.
 */
class DemoWorkflows
{
    /** Token tautan unggah klien demo; hanya dipakai di lingkungan local/testing. */
    public const CLIENT_LINK_TOKEN = 'demo-tautan-klien-sirup-temulawak-aisha-2026-xyz';

    /** @var array<string, User> */
    private array $users = [];

    public function __construct(
        private RecordSampleDecision $samples,
        private SaveContract $contracts,
        private StoreSignedContract $signedContracts,
        private ConfirmContract $confirmations,
        private StoreDocumentVersion $versions,
        private ReviewTicketDocument $reviews,
        private SyncTicketMaterials $materials,
        private BpomWorkflow $bpom,
        private RegistrationCompletion $completion,
        private MidtransSnap $midtrans,
    ) {}

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Ticket>  $tickets
     * @return string tautan unggah klien demo
     */
    public function run(array $users, array $tickets): string
    {
        $this->users = $users;
        $template = $this->contractTemplate();

        $this->samples->handle($tickets['Kapsul Jahe Merah'], ['decision' => 'perlu_revisi', 'note' => 'Rasa terlalu pahit; kurangi ekstrak jahe.'], $users['marketing']);

        $link = $this->sentContract($tickets['Sirup Temulawak'], $template);
        $this->documentsInProgress($tickets['Serbuk Kunyit Asam'], $template);
        $this->registrationSubmitted($tickets['Tablet Sambiloto'], $template);
        $this->completed($tickets['Kapsul Kunyit Putih'], $template);

        Artisan::call('sla:check');
        Artisan::call('priorities:compute');

        return $link;
    }

    private function sentContract(Ticket $ticket, DocumentTemplate $template): string
    {
        $contract = $this->approvedSampleAndContract($ticket, $template, 25_000_000);
        $contract->update(['status' => ContractStatus::Sent, 'sent_at' => now()]);

        UploadToken::query()->create([
            'ticket_id' => $ticket->id,
            'phase' => StagePhase::PreRegistration,
            'token_hash' => UploadToken::hash(self::CLIENT_LINK_TOKEN),
            'expires_at' => now()->addDays((int) config('upload_links.default_days')),
            'created_by' => $this->users['marketing']->id,
        ]);

        return route('client-uploads.show', self::CLIENT_LINK_TOKEN);
    }

    private function documentsInProgress(Ticket $ticket, DocumentTemplate $template): void
    {
        $this->activeContract($ticket, $template, 30_000_000);
        $this->materials->handle($ticket, [Material::query()->where('name', 'Kunyit')->value('id')], $this->users['rnd']);

        $items = $this->items($ticket, StagePhase::PreRegistration);
        $this->upload($items['Master formula']);
        $this->upload($items['Spesifikasi produk jadi']);
        $this->upload($items['Surat kuasa']);
        $formula = $items['Master formula']->refresh();
        $this->reviews->handle($formula, ReviewLevel::Usage, ReviewDecision::NeedsRevision, 'Komposisi ekstrak tidak sesuai sampel yang disetujui.', $this->users['ra'], $formula->load('document')->concurrencyToken());

        $ticket->invoices()->update(['due_date' => now()->subDays(3)->toDateString()]);
        $ticket->stageLogs()->whereNull('finished_at')->whereNotNull('due_at')
            ->update(['started_at' => now()->subDays(6), 'due_at' => now()->subDays(2)]);
        $ticket->forceFill(['created_at' => now()->subDays(40)])->save();
    }

    private function registrationSubmitted(Ticket $ticket, DocumentTemplate $template): void
    {
        $this->throughPreRegistration($ticket, $template, 'Sambiloto');
        $submission = $this->bpom->submit($ticket->refresh(), ['submitted_on' => now()->toDateString(), 'pnbp' => 3_000_000], $this->users['ra']);
        $this->bpom->requestAdditionalData($submission, 'Lengkapi data uji stabilitas 6 bulan.', now()->toDateString(), [], $this->users['ra']);
        $ticket->forceFill(['created_at' => now()->subDays(90)])->save();
    }

    private function completed(Ticket $ticket, DocumentTemplate $template): void
    {
        $this->throughPreRegistration($ticket, $template, 'Kunyit putih');
        $this->bpom->submit($ticket->refresh(), ['submitted_on' => now()->toDateString(), 'pnbp' => 3_000_000], $this->users['ra']);
        $this->completion->requestPackaging($ticket, now()->toDateString(), $this->users['ra']);
        $this->completion->packagingUploaded($ticket, now()->toDateString(), $this->users['ra']);
        $this->completion->recordNie($ticket, 'TR'.now()->format('y').'0300001', now()->toDateString(), $this->users['ra']);

        $ticket->invoices()->get()->each(fn (Invoice $invoice) => $this->payThroughMidtrans($invoice));
        $ticket->forceFill(['created_at' => now()->subDays(150)])->save();
    }

    /**
     * Tiket dibawa sampai fase registrasi: dokumen pra-registrasi disetujui, pengajuan dan
     * hasil pra-registrasi dicatat, LHU sesuai, lalu dokumen registrasi disetujui.
     */
    private function throughPreRegistration(Ticket $ticket, DocumentTemplate $template, string $material): void
    {
        $this->activeContract($ticket, $template, 35_000_000);
        $this->materials->handle($ticket, [Material::query()->where('name', $material)->value('id')], $this->users['rnd']);
        $this->approvePhase($ticket, StagePhase::PreRegistration);

        $submission = $this->bpom->submit($ticket->refresh(), ['submitted_on' => now()->toDateString(), 'pnbp' => 1_500_000], $this->users['ra']);
        $this->bpom->recordPreRegistrationResult($submission, now()->toDateString(), $this->pdf("Hasil pra-registrasi {$ticket->product_name}", 'hasil-pra-registrasi.pdf'), $this->users['ra']);
        $ticket->labTests()->create([
            'attempt' => 1, 'laboratory' => 'Laboratorium Uji Nusantara (contoh)', 'sent_on' => now()->toDateString(), 'estimated_on' => now()->toDateString(),
            'result_on' => now()->toDateString(), 'conforms' => true, 'recorded_by' => $this->users['ra']->id,
        ]);

        $this->bpom->openRegistration($ticket->refresh(), $this->users['ra']);
        $this->approvePhase($ticket->refresh(), StagePhase::Registration);
    }

    private function approvedSampleAndContract(Ticket $ticket, DocumentTemplate $template, int $value): Contract
    {
        $this->samples->handle($ticket, ['decision' => 'disetujui', 'note' => 'Sampel sesuai harapan klien.'], $this->users['marketing']);

        return $this->contracts->create($ticket, [
            'document_template_id' => $template->id, 'service_value' => $value, 'dp_percent' => 50, 'payment_due_days' => 7,
        ], $this->users['marketing']);
    }

    private function activeContract(Ticket $ticket, DocumentTemplate $template, int $value): void
    {
        $contract = $this->approvedSampleAndContract($ticket, $template, $value);
        $contract->update(['status' => ContractStatus::Sent, 'sent_at' => now()]);
        $this->signedContracts->handle($contract, $this->pdf("Kontrak {$contract->number} bertanda tangan", 'kontrak-ttd.pdf'), null);
        $this->confirmations->handle($contract->refresh(), $this->users['marketing']);
    }

    private function approvePhase(Ticket $ticket, StagePhase $phase): void
    {
        foreach ($this->items($ticket, $phase) as $item) {
            if ($item->status !== DocumentStatus::UnderReview) {
                $this->upload($item);
            }

            $item = $item->refresh()->load(['document', 'documentType']);

            if ($item->needsContentReview() && ! $item->contentApproved()) {
                $this->reviews->handle($item, ReviewLevel::Content, ReviewDecision::Approved, null, $this->users['qa'], $item->concurrencyToken());
                $item = $item->refresh()->load('document');
            }

            $this->reviews->handle($item, ReviewLevel::Usage, ReviewDecision::Approved, null, $this->users['ra'], $item->concurrencyToken());
        }
    }

    /** @return array<string, TicketDocument> butir fase itu per nama jenis dokumen */
    private function items(Ticket $ticket, StagePhase $phase): array
    {
        return $ticket->documents()->with(['documentType', 'material', 'ticket'])->get()
            ->filter(fn (TicketDocument $item): bool => $item->belongsToPhase($phase))
            ->keyBy(fn (TicketDocument $item): string => $item->documentType->name.($item->material ? " · {$item->material->name}" : ''))
            ->all();
    }

    private function upload(TicketDocument $item): void
    {
        $type = $item->documentType;
        $uploader = $type->pic_role === DocumentType::CLIENT_PIC ? $this->users['marketing'] : $this->users[$type->pic_role];

        $this->versions->handle($item, $this->pdf("{$type->name} {$item->ticket->product_name}", 'dokumen.pdf'), [
            'number' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $type->name), 0, 3)).'/'.now()->format('Y').'/'.str_pad((string) $item->id, 3, '0', STR_PAD_LEFT),
            'issued_at' => now()->subDays(20)->toDateString(),
            'expires_at' => $type->requires_expiry_date ? now()->addYear()->toDateString() : null,
        ], $uploader, DocumentSource::Internal);
    }

    /** Notifikasi settlement Midtrans bertanda tangan sah, seperti pembayaran nyata (FR-77). */
    private function payThroughMidtrans(Invoice $invoice): void
    {
        $orderId = str_replace('/', '-', $invoice->number).'-DEMO';
        $invoice->payments()->create(['order_id' => $orderId, 'gross_amount' => $invoice->amount, 'status' => PaymentStatus::Created, 'expires_at' => now()->addDay()]);
        $gross = $invoice->amount.'.00';

        $this->midtrans->handleNotification([
            'order_id' => $orderId, 'status_code' => '200', 'gross_amount' => $gross, 'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer', 'transaction_id' => "demo-{$orderId}", 'settlement_time' => now()->format('Y-m-d H:i:s'),
            'signature_key' => hash('sha512', $orderId.'200'.$gross.config('services.midtrans.server_key')),
        ]);
    }

    private function contractTemplate(): DocumentTemplate
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('PERJANJIAN KERJA SAMA (MoU) PENDAFTARAN PRODUK — CONTOH');
        $section->addText('Nomor ${nomor_kontrak}, tanggal ${tanggal_dokumen}, antara ${nama_perusahaan} dan ${nama_klien} (PIC ${nama_pic_klien}).');
        $section->addText('Produk ${nama_produk} (${sediaan}, ${kategori}), kode tiket ${kode_tiket}.');
        $section->addText('Nilai jasa Rp${nilai_jasa}: DP ${persen_dp}% (Rp${nilai_dp}) dan pelunasan ${persen_pelunasan}% (Rp${nilai_pelunasan}).');
        $path = tempnam(sys_get_temp_dir(), 'kontrak');
        IOFactory::createWriter($word, 'Word2007')->save($path);
        Storage::disk('local')->put('templates/demo/kontrak-maklon-v1.docx', (string) file_get_contents($path));
        @unlink($path);

        $template = DocumentTemplate::query()->firstOrCreate(['name' => 'Kontrak maklon dan pendaftaran (contoh)'], ['kind' => TemplateKind::Contract]);
        $template->versions()->firstOrCreate(['version' => 1], [
            'path' => 'templates/demo/kontrak-maklon-v1.docx', 'original_name' => 'kontrak-maklon-v1.docx', 'size' => Storage::disk('local')->size('templates/demo/kontrak-maklon-v1.docx'),
            'checksum' => hash('sha256', Storage::disk('local')->get('templates/demo/kontrak-maklon-v1.docx')),
            'placeholders' => ['kategori', 'kode_tiket', 'nama_klien', 'nama_perusahaan', 'nama_pic_klien', 'nama_produk', 'nilai_dp', 'nilai_jasa', 'nilai_pelunasan', 'nomor_kontrak', 'persen_dp', 'persen_pelunasan', 'sediaan', 'tanggal_dokumen'],
            'uploaded_by' => $this->users['admin']->id,
        ]);

        return $template;
    }

    /** PDF satu halaman berisi teks, sehingga lolos pencocokan nama produk (FR-71). */
    private function pdf(string $text, string $name): UploadedFile
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

        return UploadedFile::fake()->createWithContent($name, $pdf);
    }
}
