<?php

namespace App\Models;

use Carbon\Carbon;
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
     * Apakah assignment ini berlaku pada tanggal tersebut?
     *
     * tanggal_berakhir null = berlaku sampai diganti.
     */
    public function berlakuPada(Carbon $tanggal): bool
    {
        $tanggal = $tanggal->copy()->startOfDay();

        if ($this->tanggal_mulai->copy()->startOfDay()->gt($tanggal)) {
            return false;
        }

        return $this->tanggal_berakhir === null
            || $this->tanggal_berakhir->copy()->startOfDay()->gte($tanggal);
    }

    /**
     * Hitung posisi (0-indexed) di siklus pola untuk tanggal tertentu.
     * Ini bagian yang paling rawan salah, mirip kasus hitungMenitTerlambat —
     * makanya perlu ditest eksplisit dengan berbagai tanggal, bukan cuma "hari ini".
     *
     * Dua perbaikan di fase 33:
     *
     * 1. Panjang siklus 0 sebelumnya bikin `% 0` → DivisionByZeroError. Pola
     *    dengan `langkah` array kosong memang mungkin terjadi lewat seeder atau
     *    insert langsung (minItems(1) cuma berlaku di form — lihat fase 32).
     *    Yang memanggil method ini bukan cuma Filament tapi GenerateJadwalRotasi,
     *    jadi generator bisa mati di tengah jalan. Sekarang melempar
     *    LogicException dengan pesan yang menyebut pola mana yang bermasalah.
     *
     * 2. diffInDays() sebelumnya dipanggil tanpa argumen kedua, jadi
     *    mengembalikan nilai ABSOLUT. Untuk tanggal sebelum tanggal_mulai,
     *    posisinya tetap dihitung positif — assignment yang mulai 10 Agustus,
     *    ditanya posisi 5 Agustus, menjawab seolah sudah berjalan 5 hari.
     *    Pola bug yang sama dengan hitungMenitTerlambat() di fase 14. Sekarang
     *    selisihnya bertanda, lalu dinormalisasi supaya hasilnya tetap 0..n-1
     *    (ekstrapolasi mundur yang konsisten secara matematis).
     *
     *    CATATAN: pemanggil tetap sebaiknya menyaring dengan berlakuPada()
     *    dulu — posisi untuk tanggal sebelum assignment berlaku memang tidak
     *    punya arti bisnis, sekadar tidak lagi ngawur.
     */
    public function posisiSiklusPada(Carbon $tanggal): int
    {
        $panjangSiklus = $this->polaRotasi->panjangSiklus();

        if ($panjangSiklus === 0) {
            throw new \LogicException(sprintf(
                'Pola rotasi "%s" tidak punya langkah sama sekali, posisi siklus tidak bisa dihitung.',
                $this->polaRotasi->nama_pola ?? '(tanpa nama)'
            ));
        }

        $selisihHari = (int) $this->tanggal_mulai->copy()->startOfDay()
            ->diffInDays($tanggal->copy()->startOfDay(), false);

        return (($selisihHari % $panjangSiklus) + $panjangSiklus) % $panjangSiklus;
    }
}
