<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

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

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function adalahHariKerja(\Carbon\Carbon $tanggal): bool
    {
        if (empty($this->hari_kerja)) {
            return true; // null/kosong = dianggap kerja tiap hari (fallback aman)
        }

        return in_array($tanggal->dayOfWeek, $this->hari_kerja);
    }

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

    /**
     * Label shift yang menyertakan jamnya, mis. "Pagi (07:00–14:00)".
     *
     * WAJIB dipakai untuk label dropdown shift di seluruh Filament, bukan
     * `nama_shift` mentah. Tabel `shift` TIDAK punya kolom unit_kerja, jadi
     * satu-satunya cara merepresentasikan jam masuk yang berbeda antar unit
     * (IGD 07:00, Rawat Jalan 08:00) adalah dua baris yang sama-sama bernama
     * "Pagi". Itu data yang sah — yang tidak boleh adalah dropdown yang
     * menampilkan keduanya sebagai teks identik sehingga tidak bisa dibedakan.
     * Bandingkan bug JenisCuti.nama di fase 25.
     */
    public function labelLengkap(): string
    {
        return sprintf(
            '%s (%s–%s)',
            $this->nama_shift,
            $this->jam_masuk->format('H:i'),
            $this->jam_pulang->format('H:i')
        );
    }

    /**
     * Apakah shift ini masih direferensikan data lain?
     *
     * `absensi.shift_id` memakai ON DELETE RESTRICT, jadi menghapus shift yang
     * pernah dipakai absensi melempar QueryException 1451 mentah ke layar.
     * Relasi lain (jadwals, karyawan_shift) ikut dicek supaya penghapusan tidak
     * diam-diam menghilangkan jadwal atau assignment periode.
     */
    public function sedangDipakai(): bool
    {
        return $this->absensi()->exists()
            || $this->jadwals()->exists()
            || $this->karyawan()->exists();
    }

    /**
     * Jam masuk shift sebagai string "H:i:s".
     *
     * WAJIB dipakai kalau nilainya mau diserahkan ke setTimeFromTimeString()
     * atau ditempel ke teks. `jam_masuk` di-cast 'datetime:H:i', jadi
     * $this->jam_masuk SELALU objek Carbon — format H:i itu cuma memengaruhi
     * serialisasi, bukan tipe propertinya.
     *
     * Kalau objek Carbon itu diserahkan langsung ke setTimeFromTimeString(),
     * Carbon meng-__toString()-kannya jadi "Y-m-d H:i:s" (tanggal HARI INI +
     * jam shift). setTimeFromTimeString() memanggil modify() di dalamnya, dan
     * modify() dengan string bertanggal lengkap akan MENIMPA TANGGALNYA juga,
     * bukan cuma jamnya. Ini root cause bug fase 14 dan sudah kambuh di 3
     * tempat lain sesudahnya.
     */
    public function jamMasukString(): string
    {
        return $this->jam_masuk->format('H:i:s');
    }

    /**
     * Jam pulang shift sebagai string "H:i:s". Lihat catatan di
     * jamMasukString() — jebakan cast-nya sama persis.
     */
    public function jamPulangString(): string
    {
        return $this->jam_pulang->format('H:i:s');
    }
}
