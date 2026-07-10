<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cuti extends Model
{
    use HasApprovalWorkflow;

    protected $fillable = [
        'karyawan_id', 'jenis_cuti_id', 'tanggal_mulai', 'tanggal_selesai',
        'jumlah_hari', 'alasan', 'lampiran',
        'status', 'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'approved_at' => 'datetime',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(JenisCuti::class);
    }

    protected static function booted(): void
    {
        static::created(function (Cuti $cuti) {
            if ($cuti->jenisCuti->potong_kuota) {
                $cuti->karyawan->kuotaCutis()
                    ->where('jenis_cuti_id', $cuti->jenis_cuti_id)
                    ->where('tahun', $cuti->tanggal_mulai->year)
                    ->increment('terpakai', $cuti->jumlah_hari);
            }
        });
    }
}
