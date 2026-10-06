<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\HariLibur;
use App\Models\Shift;
use Carbon\Carbon;

class Karyawan extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $table = 'karyawan';

    const TIPE_UMUM = 'umum';

    const TIPE_ROTASI = 'rotasi';

    protected $fillable = [
        'instansi_id',
        'nip',
        'nama',
        'email',
        'password',
        'nomor_telepon',
        'foto_profil',
        'foto_wajah',
        'status_pegawai',
        'role',
        'unit_kerja',
        'jabatan',
        'tanggal_bergabung',
        'is_active',
        'tipe_jadwal',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'foto_wajah',
    ];

    protected $casts = [
        'tanggal_bergabung' => 'date',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ── Routing notifikasi ───────────────────────────────────────────────

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    // ── Relasi ───────────────────────────────────────────────────────────

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function shift(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'karyawan_shift')
            ->withPivot(['tanggal_berlaku', 'tanggal_berakhir'])
            ->withTimestamps();
    }

    public function shiftAktif(): HasOne
    {
        return $this->hasOne(KaryawanShift::class)
            ->where('tanggal_berlaku', '<=', today())
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', today()))
            ->latestOfMany('tanggal_berlaku');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function absensiHariIni(): HasOne
    {
        return $this->hasOne(Absensi::class)
            ->whereDate('tanggal', today());
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function cutis(): HasMany
    {
        return $this->hasMany(Cuti::class);
    }

    public function izins(): HasMany
    {
        return $this->hasMany(Izin::class);
    }

    public function lemburs(): HasMany
    {
        return $this->hasMany(Lembur::class);
    }

    public function dinas(): HasMany
    {
        return $this->hasMany(Dinas::class);
    }

    public function kuotaCutis(): HasMany
    {
        return $this->hasMany(KuotaCuti::class);
    }

    public function karyawanShift(): HasMany
    {
        return $this->hasMany(KaryawanShift::class);
    }

    /**
     * Assignment pola rotasi. Pasangan dari karyawanShift() untuk tipe rotasi —
     * sebelumnya relasi ini tidak pernah didefinisikan di model.
     */
    public function karyawanPolaRotasis(): HasMany
    {
        return $this->hasMany(KaryawanPolaRotasi::class);
    }

    public function isRotasi(): bool
    {
        return $this->tipe_jadwal === self::TIPE_ROTASI;
    }

    public function isUmum(): bool
    {
        return $this->tipe_jadwal === self::TIPE_UMUM;
    }

    /**
     * Apakah karyawan ini punya assignment penjadwalan (shift periode untuk
     * tipe umum, atau pola rotasi untuk tipe rotasi)?
     *
     * Dipakai guard di KaryawanForm: mengubah tipe_jadwal saat assignment masih
     * ada akan meninggalkan baris yang menurut guard fase 18 seharusnya
     * mustahil — karyawan rotasi yang punya KaryawanShift, atau sebaliknya.
     * Anomali itu selama ini baru terdeteksi belakangan oleh command
     * karyawan:cek-tipe-jadwal (fase 13); lebih masuk akal dicegah di sumbernya.
     */
    public function punyaAssignmentJadwal(): bool
    {
        return $this->karyawanShift()->exists()
            || $this->karyawanPolaRotasis()->exists();
    }

    /**
     * Apakah karyawan ini sudah punya riwayat transaksi?
     *
     * SELURUH FK ke karyawan_id memakai ON DELETE CASCADE (lihat SCHEMA.md),
     * jadi menghapus satu karyawan melenyapkan absensi, cuti, izin, lembur,
     * dinas, jadwal, kuota, dan assignment-nya sekaligus — permanen, tanpa
     * jejak. Untuk karyawan yang sudah berhenti bekerja, yang benar adalah
     * menonaktifkan (is_active = false), bukan menghapus.
     */
    public function punyaRiwayat(): bool
    {
        return $this->absensi()->exists()
            || $this->cutis()->exists()
            || $this->izins()->exists()
            || $this->lemburs()->exists()
            || $this->dinas()->exists()
            || $this->jadwals()->exists()
            || $this->kuotaCutis()->exists()
            || $this->punyaAssignmentJadwal();
    }



    /**
     * Shift yang SEHARUSNYA dijalani karyawan pada tanggal ini.
     * Null = tidak ada kewajiban masuk.
     *
     * Sumber kebenaran tunggal untuk pengingat & rekap harian.
     * Urutan cek: cuti/dinas approved -> libur instansi -> jadwal -> fallback shift periode.
     */
    public function shiftYangDiharapkanPada(Carbon $tanggal): ?Shift
    {
        // Cuti/Dinas approved menang atas semua. Sinkronisasi ke Jadwal & Absensi
        // dilakukan saat approve (fase 20-21), tapi cek langsung di sini supaya
        // tidak bergantung pada urutan sinkronisasi.
        $cutiAtauDinas = Cuti::where('karyawan_id', $this->id)
                ->where('status', 'approved')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists()
            || Dinas::where('karyawan_id', $this->id)
                ->where('status', 'approved')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists();

        if ($cutiAtauDinas) {
            return null;
        }

        // Libur instansi. TODO(is_cuti_bersama): sementara semua baris diperlakukan
        // sebagai libur, sama seperti GenerateJadwal* dan RekapHarian sekarang.
        // Saat bug is_cuti_bersama diperbaiki, filter di sini harus ikut berubah.
        $adaLibur = HariLibur::where('instansi_id', $this->instansi_id)
            ->whereDate('tanggal', $tanggal)
            ->exists();

        if ($adaLibur) {
            return null;
        }

        $jadwal = $this->jadwals()
            ->whereDate('tanggal', $tanggal)
            ->with('shift')
            ->first();

        if ($this->isRotasi()) {
            // Rotasi: Jadwal WAJIB ada. Tidak ada fallback ke KaryawanShift.
            // Jadwal dengan jenis libur atau shift_id null = tidak wajib masuk.
            if (! $jadwal || $jadwal->jenis === Jadwal::JENIS_LIBUR) {
                return null;
            }

            return $jadwal->shift; // bisa null kalau jenis cuti/dinas tanpa shift
        }

        // Umum: Jadwal eksplisit menang (termasuk override dan piket),
        // kalau tidak ada fallback ke assignment shift periode + pola hari_kerja.
        if ($jadwal) {
            if ($jadwal->jenis === Jadwal::JENIS_LIBUR) {
                return null;
            }

            return in_array($jadwal->jenis, [Jadwal::JENIS_REGULER, Jadwal::JENIS_PIKET], true)
                ? $jadwal->shift
                : null; // cuti/dinas tanpa shift
        }

        $ks = $this->karyawanShift()
            ->whereDate('tanggal_berlaku', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhereDate('tanggal_berakhir', '>=', $tanggal))
            ->with('shift')
            ->latest('tanggal_berlaku')
            ->first();

        $shift = $ks?->shift;

        if (! $shift || ! $shift->adalahHariKerja($tanggal)) {
            return null;
        }

        return $shift;
    }

    /**
     * Anomali: karyawan rotasi yang seharusnya masuk tapi tidak punya Jadwal.
     * Dipakai RekapHarian untuk stat jadwal_hilang. Pengingat TIDAK mengirim
     * notifikasi untuk kondisi ini, karena bukan tanggung jawab karyawan.
     */
    public function jadwalRotasiHilangPada(Carbon $tanggal): bool
    {
        return $this->isRotasi()
            && ! $this->jadwals()->whereDate('tanggal', $tanggal)->exists();
    }

}
