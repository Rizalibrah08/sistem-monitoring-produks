<?php

namespace App;

/** Status satu transaksi Midtrans Snap untuk sebuah tagihan. */
enum PaymentStatus: string
{
    case Created = 'dibuat';
    case Pending = 'menunggu';
    case Paid = 'berhasil';
    case Failed = 'gagal';

    /** Petakan transaction_status (dan fraud_status) notifikasi Midtrans. */
    public static function fromMidtrans(string $transactionStatus, ?string $fraudStatus): self
    {
        return match ($transactionStatus) {
            'settlement' => self::Paid,
            'capture' => $fraudStatus === null || $fraudStatus === 'accept' ? self::Paid : self::Pending,
            'pending' => self::Pending,
            default => self::Failed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Dibuat',
            self::Pending => 'Menunggu',
            self::Paid => 'Berhasil',
            self::Failed => 'Gagal',
        };
    }
}
