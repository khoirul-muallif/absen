<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use  HasFactory;

    protected $table = 'shift';

    protected $fillable = [
        'instansi_id',
        'nama_shift',
        'jam_masuk',
        'jam_pulang',
        'toleransi_menit',
        'mode_toleransi',
        'hari_kerja',
        'is_active',
    ];

    protected $casts = [
        'jam_masuk'       => 'datetime:H:i',
        'jam_pulang'      => 'datetime:H:i',
        'toleransi_menit' => 'integer',
        'is_active'       => 'boolean',
        'hari_kerja'      => 'array',

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

    public function adalahHariKerja(\Carbon\Carbon $tanggal): bool
    {
        if (empty($this->hari_kerja)) {
            return true; // null/kosong = dianggap kerja tiap hari (fallback aman)
        }

        return in_array($tanggal->dayOfWeek, $this->hari_kerja);
    }

    /**
     * Hitung menit terlambat mentah (belum dibandingkan toleransi).
     */
    // public function hitungMenitTerlambat(\Carbon\Carbon $waktuMasuk): int
    // {
    //     $jamMasukShift = today()->setTimeFromTimeString($this->jam_masuk);
    //     $selisih = $jamMasukShift->diffInMinutes($waktuMasuk, false);

    //     return max(0, (int) $selisih);
    // }
    public function hitungMenitTerlambat(\Carbon\Carbon $waktuMasuk): int
    {
        $jamMasukShift = $waktuMasuk->copy()->setTimeFromTimeString(
            $this->jam_masuk->format('H:i:s')
        );
        $selisih = $jamMasukShift->diffInMinutes($waktuMasuk, false);

        return max(0, (int) $selisih);
    }

    /**
     * Status harian: selalu berdasarkan keterlambatan hari itu saja.
     * Ini yang dilihat karyawan — buat awareness, bukan penalti.
     */
    public function tentukanStatus(\Carbon\Carbon $waktuMasuk): string
    {
        return $this->hitungMenitTerlambat($waktuMasuk) > 0 ? 'terlambat' : 'tepat_waktu';
    }

    /**
     * Cek apakah akumulasi bulanan sudah melebihi kuota toleransi.
     * Ini yang jadi acuan KPI/pelanggaran — cuma relevan buat mode akumulasi_bulanan.
     */
    public function sudahMelebihiToleransiBulanan(int $totalTerlambatBulanIniTermasukHariIni): bool
    {
        if ($this->mode_toleransi !== 'akumulasi_bulanan') {
            return false;
        }

        return $totalTerlambatBulanIniTermasukHariIni > $this->toleransi_menit;
    }
}
