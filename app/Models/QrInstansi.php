<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QrInstansi extends Model
{
    use HasFactory;

    protected $table = 'qr_instansi';

    protected $fillable = [
        'instansi_id',
        'kode_qr',
        'is_active',
        'expired_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expired_at' => 'datetime',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /**
     * Apakah QR ini masih valid (aktif dan belum expired)?
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expired_at && $this->expired_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Status validitas dalam bentuk label.
     *
     * Tabel sebelumnya cuma menampilkan is_active, jadi QR yang expired_at-nya
     * sudah lewat tetap terlihat bercentang hijau padahal sudah tidak bisa
     * dipakai absen. isValid() sudah ada sejak fase 1 tapi tidak pernah dipakai
     * di UI — kelas masalah yang sama dengan label "Aktif" di KaryawanShift
     * (fase 31).
     */
    public function statusValiditas(): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        if ($this->expired_at && $this->expired_at->isPast()) {
            return 'Kedaluwarsa';
        }

        return 'Berlaku';
    }

    /**
     * Apakah QR ini sudah pernah dipakai absen?
     *
     * `absensi.qr_instansi_id` memakai ON DELETE RESTRICT (SCHEMA.md), jadi
     * menghapus QR yang pernah dipakai melempar QueryException 1451 mentah ke
     * layar — kelas bug yang sama dengan Shift di fase 30.
     */
    public function sedangDipakai(): bool
    {
        return $this->absensi()->exists();
    }

    /**
     * Apakah kode QR masih boleh diubah?
     *
     * Begitu sudah pernah dipakai absen, berarti QR fisiknya sudah dicetak dan
     * ditempel. Mengubah kodenya membuat semua QR yang terpasang jadi tidak
     * valid — karyawan tidak bisa absen dan tidak ada yang tahu penyebabnya.
     * Kalau memang perlu kode baru, buat baris QR baru dan nonaktifkan yang
     * lama, supaya riwayat absensinya tetap bisa ditelusuri.
     */
    public function kodeMasihBisaDiubah(): bool
    {
        return ! $this->sedangDipakai();
    }

    /**
     * Generate kode QR unik baru.
     */
    public static function generateKode(): string
    {
        do {
            $kode = strtoupper(Str::random(32));
        } while (self::where('kode_qr', $kode)->exists());

        return $kode;
    }
}
