<?php

namespace App\Models;

use App\Models\JenisCuti;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class KuotaCuti extends Model
{
    use HasFactory;

    protected $fillable = [
        'karyawan_id', 'jenis_cuti_id', 'tahun', 'semester', 'kuota', 'terpakai',
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
     * Finder terpusat. $semester default 0 ("tidak semesteran") — pemanggil
     * untuk jenis cuti tahunan tidak perlu berubah sama sekali. Pemanggil
     * untuk jenis cuti semesteran WAJIB hitung semesternya sendiri lewat
     * JenisCuti::semesterDari() dan kirim ke sini.
     *
     * Return null kalau row belum pernah dibuat — lihat sisaUntuk().
     *
     * CATATAN: jangan dipakai di dalam Cuti::afterApprove(); di sana row
     * diambil dengan lockForUpdate() di dalam transaksi (fase 22).
     */
    public static function untuk(int $karyawanId, int $jenisCutiId, int $tahun, int $semester = 0): ?self
    {
        return static::where('karyawan_id', $karyawanId)
            ->where('jenis_cuti_id', $jenisCutiId)
            ->where('tahun', $tahun)
            ->where('semester', $semester)
            ->first();
    }

    /**
     * Sengaja return ?int, BUKAN int — null = belum ada row = tidak ada
     * dasar untuk menolak (kebijakan fase 22). 0 = row ada, kuota habis.
     */
    public static function sisaUntuk(int $karyawanId, int $jenisCutiId, int $tahun, int $semester = 0): ?int
    {
        return static::untuk($karyawanId, $jenisCutiId, $tahun, $semester)?->sisa;
    }

    /**
     * Dipanggil HANYA dari Cuti::afterApprove(), di dalam transaksi, sebelum
     * lockForUpdate(). insertOrIgnore aman dari race condition karena unique
     * (karyawan_id, jenis_cuti_id, tahun, semester) yang menangani duplikat.
     */
    public static function pastikanUntuk(int $karyawanId, int $jenisCutiId, int $tahun, int $defaultKuota, int $semester = 0): void
    {
        DB::table('kuota_cutis')->insertOrIgnore([
            'karyawan_id'   => $karyawanId,
            'jenis_cuti_id' => $jenisCutiId,
            'tahun'         => $tahun,
            'semester'      => $semester,
            'kuota'         => $defaultKuota,
            'terpakai'      => 0,
        ]);
    }
}
