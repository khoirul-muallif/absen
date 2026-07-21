<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaRotasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'instansi_id',
        'unit_kerja',
        'nama_pola',
        'langkah',
        'berlaku_saat_libur_nasional',
        'is_active',
    ];

    protected $casts = [
        'langkah' => 'array',
        'berlaku_saat_libur_nasional' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function karyawanPolaRotasis(): HasMany
    {
        return $this->hasMany(KaryawanPolaRotasi::class);
    }

    public function panjangSiklus(): int
    {
        return count($this->langkah);
    }

    /**
     * Ambil langkah pola di posisi tertentu (0-indexed, sudah di-mod di caller).
     */
    public function langkahKe(int $posisi): array
    {
        return $this->langkah[$posisi];
    }
}
