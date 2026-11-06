<?php

namespace App\Http\Controllers;

use App\Services\MidtransSnap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends Controller
{
    /**
     * Endpoint notifikasi HTTP Midtrans. Status selain 200 membuat Midtrans mengulang
     * pengiriman, kecuali tanda tangan tidak valid yang ditolak permanen.
     */
    public function __invoke(Request $request, MidtransSnap $snap): JsonResponse
    {
        $result = $snap->handleNotification($request->all());

        return response()->json(['status' => $result], match ($result) {
            'ok' => 200,
            'invalid_signature' => 403,
            'unknown_order' => 404,
            default => 422,
        });
    }
}
