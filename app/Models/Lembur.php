<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lembur extends Model
{
    use HasApprovalWorkflow, HasFactory;

    protected $fillable = [
        'karyawan_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'alasan',
        'status', 'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /**
     * jam_mulai/jam_selesai TIDAK di-cast, keluar dari DB sebagai string
     * "H:i:s" apa adanya (fase 27, sama seperti Izin). Pakai method ini
     * di response API, JANGAN panggil ->format() di kolomnya langsung.
     */
    public function jamMulaiString(): string
    {
        return \Carbon\Carbon::parse($this->jam_mulai)->format('H:i');
    }

    public function jamSelesaiString(): string
    {
        return \Carbon\Carbon::parse($this->jam_selesai)->format('H:i');
    }
}
