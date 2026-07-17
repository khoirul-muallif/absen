<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaCuti extends Model
{
        use HasFactory;

    protected $fillable = [
        'karyawan_id', 'jenis_cuti_id', 'tahun', 'kuota', 'terpakai',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class);
    }

    public function getSisaAttribute(): int
    {
        return $this->kuota - $this->terpakai;
    }
}
