<?php

namespace App;

/** Status tagihan; pelunasan dikonfirmasi otomatis oleh notifikasi Midtrans (FR-75, FR-77). */
enum InvoiceStatus: string
{
    case Issued = 'terbit';
    case AwaitingPayment = 'menunggu_pembayaran';
    case Paid = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Belum dibayar',
            self::AwaitingPayment => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
        };
    }
}
