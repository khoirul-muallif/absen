<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Karyawan extends Authenticatable
{
    use HasApiTokens, Notifiable;
    // use  Notifiable;
    protected $table = 'karyawan';

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'foto_wajah', // tidak dikirim ke API umum
    ];

    protected $casts = [
        'tanggal_bergabung' => 'date',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

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

    /**
     * Shift yang sedang aktif hari ini.
     */
    public function shiftAktif(): HasOne
    {
        return $this->hasOne(KaryawanShift::class)
            ->where('tanggal_berlaku', '<=', today())
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')->orWhere('tanggal_berakhir', '>=', today()))
            ->latestOfMany('tanggal_berlaku');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /**
     * Absensi hari ini.
     */
    public function absensiHariIni(): HasOne
    {
        return $this->hasOne(Absensi::class)
            ->whereDate('tanggal', today());
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
