<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dinas extends Model
{
    use HasApprovalWorkflow;

    protected $table = 'dinas'; // biar Laravel gak nebak 'dina'

    protected $fillable = [
        'karyawan_id', 'tanggal_mulai', 'tanggal_selesai', 'tujuan', 'keperluan',
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
}
