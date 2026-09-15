<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dinas extends Model
{
    use HasApprovalWorkflow, HasFactory;

    protected $table = 'dinas';

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

    /**
     * Sinkronkan ulang Jadwal & Absensi untuk dinas ini.
     *
     * Dinas tidak menyentuh kuota sama sekali, jadi isinya sama persis dengan
     * afterApprove() saat ini. Method terpisah tetap dibuat supaya command
     * re-sync punya API yang sama untuk Cuti & Dinas, dan supaya kalau nanti
     * Dinas dapat efek samping baru di afterApprove(), jalur re-sync tidak
     * ikut terbawa diam-diam (persis yang terjadi pada Cuti di fase 22).
     */
    public function resyncJadwalDanAbsensi(): void
    {
        $this->sinkronisasiJadwalDanAbsensi('dinas');
    }

    public function afterApprove(): void
    {
        $this->sinkronisasiJadwalDanAbsensi('dinas');
    }
}
