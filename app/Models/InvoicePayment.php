<?php

namespace App\Models;

use App\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu transaksi Midtrans Snap untuk sebuah tagihan. Order ID unik per percobaan bayar,
 * sehingga transaksi yang kedaluwarsa dapat diganti tanpa kehilangan riwayatnya.
 */
#[Fillable(['invoice_id', 'order_id', 'gross_amount', 'status', 'snap_token', 'redirect_url', 'expires_at', 'transaction_status', 'payment_type', 'transaction_id', 'paid_at', 'last_notification'])]
class InvoicePayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'gross_amount' => 'integer',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_notification' => 'array',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
