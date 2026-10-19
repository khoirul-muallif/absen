<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Izin extends Model
{
    use HasApprovalWorkflow, HasFactory;

    protected $fillable = [
        'karyawan_id', 'tanggal', 'jam_keluar', 'jam_kembali', 'keperluan',
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
     * jam_keluar/jam_kembali TIDAK di-cast (beda dari Shift::jam_masuk),
     * jadi keluar dari DB sebagai string "H:i:s" apa adanya (fase 27).
     * Pakai method ini di response API, JANGAN panggil ->format() di
     * kolomnya langsung — nilainya string, bukan Carbon.
     */
    public function jamKeluarString(): string
    {
        return \Carbon\Carbon::parse($this->jam_keluar)->format('H:i');
    }

    public function jamKembaliString(): ?string
    {
        return $this->jam_kembali
            ? \Carbon\Carbon::parse($this->jam_kembali)->format('H:i')
            : null;
    }
}
