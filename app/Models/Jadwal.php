<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = [
        'karyawan_id', 'shift_id', 'tanggal', 'jenis', 'keterangan','sumber',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    const JENIS_REGULER = 'reguler';
    const JENIS_PIKET = 'piket';
    const JENIS_LIBUR = 'libur';

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function tukarJadwalDiajukan(): HasOne
    {
        return $this->hasOne(TukarJadwal::class, 'jadwal_id');
    }

    public function tukarJadwalDitujukan(): HasOne
    {
        return $this->hasOne(TukarJadwal::class, 'jadwal_tujuan_id');
    }
}
