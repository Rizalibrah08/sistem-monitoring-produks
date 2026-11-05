<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Klien pemilik merek (FR-66). Nomor WhatsApp disimpan dalam format 62xxxxxxxxxx dan unik,
 * karena dipakai chatbot dan inbox untuk mengenali klien.
 */
#[Fillable(['name', 'company', 'pic_name', 'whatsapp', 'email', 'marketing_user_id'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marketing_user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function marketingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    /** Ubah 0812..., +62 812..., atau 812... menjadi 62812... tanpa tanda baca. */
    public static function normalizeWhatsapp(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return match (true) {
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }
}
