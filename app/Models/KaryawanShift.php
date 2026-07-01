<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaryawanShift extends Model
{
    protected $table = 'karyawan_shift';

    protected $fillable = [
        'karyawan_id',
        'shift_id',
        'tanggal_berlaku',
        'tanggal_berakhir',
    ];

    protected $casts = [
        'tanggal_berlaku'  => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
