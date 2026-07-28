<?php

namespace App\Models;

use App\Exceptions\KuotaCutiTidakCukupException;
use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Cuti extends Model
{
    use HasApprovalWorkflow, HasFactory;

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
            DB::transaction(function () {
                $kuota = $this->karyawan->kuotaCutis()
                    ->where('jenis_cuti_id', $this->jenis_cuti_id)
                    ->where('tahun', $this->tanggal_mulai->year)
                    ->lockForUpdate()
                    ->first();

                // Belum ada row KuotaCuti sama sekali = belum ada tracking untuk
                // karyawan/jenis/tahun ini - dibiarkan seperti behavior lama,
                // tidak dianggap error (tidak ada dasar buat menolak).
                if ($kuota) {
                    if ($kuota->terpakai + $this->jumlah_hari > $kuota->kuota) {
                        throw new KuotaCutiTidakCukupException(
                            "Kuota {$this->jenisCuti->nama} tahun {$this->tanggal_mulai->year} tidak cukup. ".
                            "Sisa: {$kuota->sisa} hari, diajukan: {$this->jumlah_hari} hari."
                        );
                    }

                    $kuota->increment('terpakai', $this->jumlah_hari);
                }
            });
        }

        $this->sinkronisasiJadwalDanAbsensi('cuti');
    }
}
