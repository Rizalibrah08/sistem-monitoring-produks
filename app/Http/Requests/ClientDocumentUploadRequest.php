<?php

namespace App\Http\Requests;

class ClientDocumentUploadRequest extends UploadDocumentVersionRequest
{
    /**
     * Klien tidak memiliki akun; hak unggahnya diperiksa dari token tautan di controller (FR-18).
     */
    public function authorize(): bool
    {
        return true;
    }
}
