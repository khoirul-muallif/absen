<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaCuti extends Model
{
    use HasFactory;

    protected $fillable = [
        'karyawan_id', 'jenis_cuti_id', 'tahun', 'kuota', 'terpakai',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class);
    }

    public function getSisaAttribute(): int
    {
        return $this->kuota - $this->terpakai;
    }

    /**
     * Finder terpusat untuk kombinasi (karyawan, jenis cuti, tahun).
     *
     * Return null kalau row-nya memang belum pernah dibuat. Pemanggil WAJIB
     * membedakan null dari 0 — lihat sisaUntuk() di bawah.
     *
     * CATATAN: jangan dipakai di dalam alur approve (Cuti::afterApprove()).
     * Di sana row-nya harus diambil dengan lockForUpdate() di dalam transaksi
     * (kebijakan race condition fase 22); memanggil finder ini justru
     * menghilangkan lock-nya.
     */
    public static function untuk(int $karyawanId, int $jenisCutiId, int $tahun): ?self
    {
        return static::where('karyawan_id', $karyawanId)
            ->where('jenis_cuti_id', $jenisCutiId)
            ->where('tahun', $tahun)
            ->first();
    }

    /**
     * Sisa kuota (kuota - terpakai) untuk kombinasi tersebut.
     *
     * Sengaja return ?int, BUKAN int:
     *   null = belum ada row KuotaCuti sama sekali -> TIDAK ADA DASAR UNTUK
     *          MENOLAK (kebijakan fase 22). Bukan berarti sisanya nol.
     *   0    = row-nya ada, dan kuotanya memang benar-benar habis.
     *
     * Menyamakan keduanya jadi 0 sudah pernah jadi bug dua kali (CutiForm
     * fase 25, CutisTable fase 26) — tipe nullable ini yang memaksa tiap
     * pemanggil memilih secara eksplisit.
     */
    public static function sisaUntuk(int $karyawanId, int $jenisCutiId, int $tahun): ?int
    {
        return static::untuk($karyawanId, $jenisCutiId, $tahun)?->sisa;
    }
}
