<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instansi extends Model
{
    use HasFactory;

    protected $table = 'instansi';

    protected $fillable = [
        'nama',
        'kode_instansi',
        'latitude',
        'longitude',
        'radius_meter',
        'alamat',
        'telepon',
        'is_active',
    ];

    protected $casts = [
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'radius_meter' => 'integer',
        'is_active'    => 'boolean',
    ];

    public function qrInstansi(): HasMany
    {
        return $this->hasMany(QrInstansi::class);
    }

    public function qrAktif(): HasMany
    {
        return $this->hasMany(QrInstansi::class)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', now()));
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }

    public function shift(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Hari libur milik instansi ini. Sebelumnya relasi ini tidak pernah
     * didefinisikan walau hari_liburs punya instansi_id.
     */
    public function hariLiburs(): HasMany
    {
        return $this->hasMany(HariLibur::class);
    }

    /**
     * Pola rotasi milik instansi ini. Sama, relasinya belum pernah ada.
     */
    public function polaRotasis(): HasMany
    {
        return $this->hasMany(PolaRotasi::class);
    }

    /**
     * Apakah instansi ini sudah dipakai data lain?
     *
     * Lima tabel menggantung ke instansi_id: karyawan, shift, qr_instansi,
     * hari_liburs, pola_rotasis — dan lewat karyawan, seluruh riwayat absensi
     * & pengajuan ikut. Menghapus satu instansi berpotensi melenyapkan hampir
     * seluruh isi sistem. Selama baru ada satu instansi, itu berarti semuanya.
     */
    public function sedangDipakai(): bool
    {
        return $this->karyawan()->exists()
            || $this->shift()->exists()
            || $this->qrInstansi()->exists()
            || $this->hariLiburs()->exists()
            || $this->polaRotasis()->exists();
    }

    /**
     * Apakah kode instansi masih boleh diubah?
     *
     * kode_instansi dikirim ke aplikasi mobile lewat /api/auth/me sebagai
     * identitas instansi. Begitu sudah ada karyawan yang login atau QR yang
     * beredar, mengubahnya berisiko bikin data yang sudah tersimpan di sisi
     * klien tidak lagi cocok.
     *
     * CATATAN: kode ini TIDAK dipakai untuk pemindaian QR — endpoint
     * /api/instansi/qr/{kode} mencari QrInstansi.kode_qr, kolom yang terpisah.
     * Helper text lama menyebut sebaliknya.
     */
    public function kodeMasihBisaDiubah(): bool
    {
        return ! $this->karyawan()->exists() && ! $this->qrInstansi()->exists();
    }

    /**
     * Hitung jarak (meter) dari koordinat ke pusat instansi
     * menggunakan rumus Haversine.
     */
    public function hitungJarak(float $lat, float $lng): float
    {
        $R = 6371000; // radius bumi dalam meter

        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($this->latitude))
           * cos(deg2rad($lat))
           * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /**
     * Apakah koordinat berada dalam radius instansi?
     */
    public function dalamRadius(float $lat, float $lng): bool
    {
        return $this->hitungJarak($lat, $lng) <= $this->radius_meter;
    }
}
