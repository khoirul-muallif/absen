<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends Model
{
    protected $table = 'absensi';

    protected $fillable = [
        'karyawan_id',
        'shift_id',
        'qr_instansi_id',
        'tanggal',
        'waktu_masuk',
        'latitude_masuk',
        'longitude_masuk',
        'foto_masuk',
        'waktu_pulang',
        'latitude_pulang',
        'longitude_pulang',
        'foto_pulang',
        'status',
        'keterangan',
        'menit_terlambat',
    ];

    protected $casts = [
        'tanggal'          => 'date',
        'waktu_masuk'      => 'datetime',
        'waktu_pulang'     => 'datetime',
        'latitude_masuk'   => 'decimal:7',
        'longitude_masuk'  => 'decimal:7',
        'latitude_pulang'  => 'decimal:7',
        'longitude_pulang' => 'decimal:7',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function qrInstansi(): BelongsTo
    {
        return $this->belongsTo(QrInstansi::class);
    }

    /**
     * Durasi kerja dalam menit (null jika belum pulang).
     */
    public function durasiMenit(): ?int
    {
        if (! $this->waktu_masuk || ! $this->waktu_pulang) {
            return null;
        }

        return (int) $this->waktu_masuk->diffInMinutes($this->waktu_pulang);
    }


    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeHariIni($query)
    {
        return $query->whereDate('tanggal', today());
    }

    public function scopeBulanIni($query)
    {
        return $query->whereYear('tanggal', now()->year)
                     ->whereMonth('tanggal', now()->month);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Berapa menit terlambat dari jam masuk shift (0 jika tepat waktu).
     * Prioritas: nilai yang sudah tersimpan di kolom menit_terlambat.
     * Fallback: hitung ulang dari waktu_masuk (untuk data lama sebelum kolom ini ada).
     */
    public function menitTerlambat(): int
    {
        if (isset($this->attributes['menit_terlambat'])) {
            return (int) $this->menit_terlambat;
        }

        if (! $this->waktu_masuk) {
            return 0;
        }

        return $this->shift->hitungMenitTerlambat($this->waktu_masuk);
    }
}
