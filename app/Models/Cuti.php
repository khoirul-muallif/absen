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

    public function afterApprove(): void
    {
        if ($this->jenisCuti->potong_kuota) {
            $this->karyawan->kuotaCutis()
                ->where('jenis_cuti_id', $this->jenis_cuti_id)
                ->where('tahun', $this->tanggal_mulai->year)
                ->increment('terpakai', $this->jumlah_hari);
        }

        $this->sinkronisasiAbsensi('cuti');
    }

    protected function sinkronisasiAbsensi(string $status): void
    {
        $periode = \Carbon\CarbonPeriod::create($this->tanggal_mulai, $this->tanggal_selesai);

        foreach ($periode as $tanggal) {
            $absensi = \App\Models\Absensi::firstOrNew(
                ['karyawan_id' => $this->karyawan_id, 'tanggal' => $tanggal->toDateString()]
            );

            // Jangan timpa kalau sudah ada kehadiran asli (waktu_masuk terisi)
            if ($absensi->exists && $absensi->waktu_masuk !== null) {
                continue;
            }

            $absensi->status = $status;
            $absensi->save();
        }
    }
}
