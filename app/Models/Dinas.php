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

   public function afterApprove(): void
    {
        $periode = \Carbon\CarbonPeriod::create($this->tanggal_mulai, $this->tanggal_selesai);

        foreach ($periode as $tanggal) {
            $absensi = \App\Models\Absensi::firstOrNew(
                ['karyawan_id' => $this->karyawan_id, 'tanggal' => $tanggal->toDateString()]
            );

            $absensi->status = 'dinas';
            $absensi->waktu_masuk = null;
            $absensi->waktu_pulang = null;
            $absensi->menit_terlambat = 0;
            $absensi->melebihi_toleransi_bulanan = false;
            $absensi->foto_masuk = null;
            $absensi->latitude_masuk = null;
            $absensi->longitude_masuk = null;
            $absensi->foto_pulang = null;
            $absensi->latitude_pulang = null;
            $absensi->longitude_pulang = null;
            $absensi->qr_instansi_id = null;
            $absensi->save();
        }
    }
}
