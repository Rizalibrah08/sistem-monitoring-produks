<?php

namespace App\Http\Requests;

class ClientSignedContractRequest extends UploadSignedContractRequest
{
    /**
     * Klien tidak memiliki akun; hak unggahnya diperiksa dari token tautan di controller (FR-74).
     */
    public function authorize(): bool
    {
        return true;
    }
}
