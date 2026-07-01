<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instansi extends Model
{
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
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
        'radius_meter'=> 'integer',
        'is_active'   => 'boolean',
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
