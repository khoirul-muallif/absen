<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisCuti extends Model
{
    use  HasFactory;

    protected $fillable = [
        'nama', 'is_tahunan', 'default_kuota', 'perlu_lampiran', 'potong_kuota', 'is_active', 'periode_kuota',
    ];

    protected $casts = [
        'is_tahunan' => 'boolean',
        'perlu_lampiran' => 'boolean',
        'potong_kuota' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cutis(): HasMany
    {
        return $this->hasMany(Cuti::class);
    }

    public function kuotaCutis(): HasMany
    {
        return $this->hasMany(KuotaCuti::class);
    }

    // consts
    public const PERIODE_TAHUNAN = 'tahunan';
    public const PERIODE_SEMESTERAN = 'semesteran';

    // tambahkan 'periode_kuota' ke $fillable

    /**
     * Semester (1 atau 2) untuk tanggal tertentu, KALAU jenis cuti ini
     * periode_kuota-nya semesteran. Return 0 (sentinel "tidak berlaku")
     * untuk jenis cuti tahunan — konsisten dengan KuotaCuti.semester.
     */
    public function semesterDari(\Carbon\CarbonInterface $tanggal): int
    {
        if ($this->periode_kuota !== self::PERIODE_SEMESTERAN) {
            return 0;
        }

        return $tanggal->month <= 6 ? 1 : 2;
    }
}
