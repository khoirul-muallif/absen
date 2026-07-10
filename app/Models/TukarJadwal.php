<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TukarJadwal extends Model
{
    use HasApprovalWorkflow;

    protected $fillable = [
        'jadwal_id', 'jadwal_tujuan_id', 'alasan',
        'status', 'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function jadwalTujuan(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_tujuan_id');
    }
}
