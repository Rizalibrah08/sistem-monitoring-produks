<?php

namespace App\Jobs;

use App\ContractStatus;
use App\Models\Contract;
use App\Services\DocumentRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateContractDocuments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $contractId) {}

    /**
     * Isi templat kontrak dengan data klien, produk, dan termin, lalu simpan berkas Word dan
     * PDF-nya sebagai pratinjau sebelum kontrak dikirim (FR-69, FR-73). Bila konversi PDF
     * gagal, berkas Word tetap tersedia dan alasannya dicatat.
     */
    public function handle(DocumentRenderer $renderer): void
    {
        $contract = Contract::query()->with(['ticket.client', 'ticket.dosageForm', 'ticket.category', 'documentTemplate'])->find($this->contractId);

        if ($contract === null || $contract->status !== ContractStatus::Draft) {
            return;
        }

        $version = $contract->documentTemplate->versions()->orderByDesc('version')->first();

        if ($version === null) {
            $contract->update(['generation_error' => 'Templat kontrak belum memiliki versi.']);

            return;
        }

        $disk = Storage::disk('local');
        $stamp = now()->format('YmdHis');
        $base = 'kontrak-'.str_replace('/', '-', $contract->number);
        $wordPath = "contracts/{$contract->id}/{$base}-{$stamp}.docx";
        $docx = $renderer->fill($version, $this->variables($contract));
        $disk->put($wordPath, $docx);

        $pdfPath = null;
        $error = null;

        try {
            $pdfPath = "contracts/{$contract->id}/{$base}-{$stamp}.pdf";
            $disk->put($pdfPath, $renderer->toPdf($docx, "{$base}.docx"));
        } catch (Throwable $exception) {
            report($exception);
            $pdfPath = null;
            $error = 'PDF gagal dibuat; berkas Word tetap dapat diunduh.';
        }

        $contract->update([
            'template_version_id' => $version->id,
            'word_path' => $wordPath,
            'pdf_path' => $pdfPath,
            'generated_at' => now(),
            'generation_error' => $error,
        ]);
    }

    /** @return array<string, string> */
    private function variables(Contract $contract): array
    {
        $ticket = $contract->ticket;
        $client = $ticket->client;
        $rupiah = fn (int $value): string => number_format($value, 0, ',', '.');

        return [
            'nama_perusahaan' => (string) config('document_templates.company_name'),
            'tanggal_dokumen' => now()->format('d/m/Y'),
            'nama_klien' => (string) $client->name,
            'nama_pic_klien' => (string) $client->pic_name,
            'email_klien' => (string) $client->email,
            'nomor_whatsapp_klien' => (string) $client->whatsapp,
            'kode_tiket' => $ticket->code,
            'nama_produk' => $ticket->product_name,
            'sediaan' => $ticket->dosageForm->name,
            'kategori' => $ticket->category->name,
            'nomor_kontrak' => $contract->number,
            'nilai_jasa' => $rupiah($contract->service_value),
            'persen_dp' => (string) $contract->dp_percent,
            'nilai_dp' => $rupiah($contract->dpAmount()),
            'persen_pelunasan' => (string) $contract->settlementPercent(),
            'nilai_pelunasan' => $rupiah($contract->settlementAmount()),
        ];
    }
}
