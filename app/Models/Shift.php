<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $table = 'shift';

    protected $fillable = [
        'instansi_id',
        'nama_shift',
        'jam_masuk',
        'jam_pulang',
        'toleransi_menit',
        'is_active',
    ];

    protected $casts = [
        'jam_masuk'       => 'datetime:H:i',
        'jam_pulang'      => 'datetime:H:i',
        'toleransi_menit' => 'integer',
        'is_active'       => 'boolean',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function karyawan(): BelongsToMany
    {
        return $this->belongsToMany(Karyawan::class, 'karyawan_shift')
            ->withPivot(['tanggal_berlaku', 'tanggal_berakhir'])
            ->withTimestamps();
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /**
     * Tentukan status kehadiran berdasarkan waktu masuk aktual.
     * Mengembalikan: 'tepat_waktu' | 'terlambat'
     */
    public function tentukanStatus(\Carbon\Carbon $waktuMasuk): string
    {
        $batasWaktu = today()->setTimeFromTimeString($this->jam_masuk)
            ->addMinutes($this->toleransi_menit);

        return $waktuMasuk->lte($batasWaktu) ? 'tepat_waktu' : 'terlambat';
    }
}
