<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class HariLibur extends Model
{
        use HasFactory;
    protected $fillable = [
        'instansi_id', 'tanggal', 'nama', 'keterangan', 'is_cuti_bersama',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'is_cuti_bersama' => 'boolean',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }
    /**
     * Hanya hari yang benar-benar meliburkan karyawan (libur nasional/instansi).
     * Cuti bersama BUMS tidak meliburkan: karyawan tetap masuk, yang ingin libur
     * mengajukan cuti biasa. Semua pemakai HariLibur untuk keputusan "libur atau
     * tidak" wajib lewat scope ini, supaya aturannya hanya ada di satu tempat.
     */
    public function scopeMeliburkan(Builder $query): Builder
    {
        return $query->where('is_cuti_bersama', false);
    }
}
