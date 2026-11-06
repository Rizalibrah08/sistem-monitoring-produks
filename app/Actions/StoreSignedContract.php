<?php

namespace App\Actions;

use App\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreSignedContract
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Simpan kontrak bertanda tangan dari klien lewat tautan, atau dari Marketing atas nama
     * klien, sehingga kontrak berstatus ditandatangani dan menunggu konfirmasi (FR-74).
     * Unggahan ulang sebelum dikonfirmasi menggantikan berkas sebelumnya.
     */
    public function handle(Contract $contract, UploadedFile $file, ?User $actor): Contract
    {
        if (! in_array($contract->status, [ContractStatus::Sent, ContractStatus::Signed], true)) {
            throw ValidationException::withMessages(['file' => 'Kontrak belum dikirim atau sudah aktif.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $path = Storage::disk('local')->putFileAs("contracts/{$contract->id}", $file, 'ttd-'.Str::uuid().".{$extension}");
        $previous = $contract->signed_path;

        $contract->update([
            'signed_path' => $path,
            'signed_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'signed_at' => now(),
            'status' => ContractStatus::Signed,
        ]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $this->audit->record('contract.signed_uploaded', $contract, after: [
            'number' => $contract->number,
            'file' => $contract->signed_original_name,
        ], source: $actor === null ? 'client' : 'web', actor: $actor, ticketCode: $contract->ticket->code);

        return $contract;
    }
}
