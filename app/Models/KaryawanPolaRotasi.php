<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaryawanPolaRotasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'karyawan_id',
        'pola_rotasi_id',
        'tanggal_mulai',
        'tanggal_berakhir',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function polaRotasi(): BelongsTo
    {
        return $this->belongsTo(PolaRotasi::class);
    }

    /**
     * Hitung posisi (0-indexed) di siklus pola untuk tanggal tertentu.
     * Ini bagian yang paling rawan salah, mirip kasus hitungMenitTerlambat —
     * makanya perlu ditest eksplisit dengan berbagai tanggal, bukan cuma "hari ini".
     */
    public function posisiSiklusPada(\Carbon\Carbon $tanggal): int
    {
        $panjangSiklus = $this->polaRotasi->panjangSiklus();
        $selisihHari = $this->tanggal_mulai->diffInDays($tanggal);

        return $selisihHari % $panjangSiklus;
    }
}
