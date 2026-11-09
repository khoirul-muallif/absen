<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Informasi extends Model
{
    use HasFactory;

    /**
     * Singular, ikut pola instansi/shift/absensi. Tanpa ini Laravel
     * menebak `informasis`.
     */
    protected $table = 'informasi';

    protected $fillable = [
        'instansi_id', 'judul', 'isi', 'tanggal_terbit', 'is_aktif', 'created_by',
    ];

    protected $casts = [
        'tanggal_terbit' => 'date',
        'is_aktif' => 'boolean',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * Potongan isi untuk kartu di list. Dibuat di backend supaya payload
     * list tidak membawa isi penuh tiap pengumuman.
     */
    public function getRingkasanAttribute(): string
    {
        return Str::limit(strip_tags($this->isi), 150);
    }
}
