<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hari libur nasional atau cuti bersama yang dikecualikan dari perhitungan hari kerja.
 * Kolom date disimpan sebagai teks Y-m-d agar mudah dibandingkan dan unik per tanggal.
 */
#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;
}
