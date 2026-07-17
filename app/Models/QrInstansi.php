<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QrInstansi extends Model
{
        use HasFactory;

    protected $table = 'qr_instansi';

    protected $fillable = [
        'instansi_id',
        'kode_qr',
        'is_active',
        'expired_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expired_at' => 'datetime',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    /**
     * Apakah QR ini masih valid (aktif dan belum expired)?
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expired_at && $this->expired_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Generate kode QR unik baru.
     */
    public static function generateKode(): string
    {
        do {
            $kode = strtoupper(Str::random(32));
        } while (self::where('kode_qr', $kode)->exists());

        return $kode;
    }
}
