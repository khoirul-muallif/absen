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

    /**
     * Panjang siklus (jumlah langkah), aman terhadap kolom `langkah` yang null.
     *
     * Versi lama memanggil count() langsung pada $this->langkah. Kalau kolomnya
     * null (bukan array kosong), count() melempar TypeError di PHP 8 — dan
     * karena dipakai di kolom tabel Filament, yang gagal dirender bukan cuma
     * satu baris tapi SELURUH tabel.
     */
    public function panjangSiklus(): int
    {
        return is_array($this->langkah) ? count($this->langkah) : 0;
    }

    /**
     * Ambil langkah pola di posisi tertentu (0-indexed, sudah di-mod di caller).
     */
    public function langkahKe(int $posisi): array
    {
        return $this->langkah[$posisi];
    }

    /**
     * Apakah pola ini masih di-assign ke karyawan?
     *
     * Perilaku FK `karyawan_pola_rotasis.pola_rotasi_id` tidak disebut di
     * SCHEMA.md. Kalau CASCADE, menghapus pola akan menghilangkan assignment
     * diam-diam dan karyawan rotasi kehilangan sumber jadwalnya. Kalau
     * RESTRICT, muncul QueryException 1451 mentah ke layar. Guard ini aman
     * untuk kedua kemungkinan — pola yang sama dengan Shift::sedangDipakai()
     * di fase 30.
     */
    public function sedangDipakai(): bool
    {
        return $this->karyawanPolaRotasis()->exists();
    }
}
