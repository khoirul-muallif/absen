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

    /**
     * $semester default 0 ("tidak semesteran", kompatibel dengan pemanggil
     * lama). Untuk jenis cuti semesteran, scope ke bulan-bulan semester itu
     * saja — kalau tidak, pending semester 2 ikut kehitung waktu approve
     * semester 1, dan sebaliknya.
     */
    public static function hariPendingUntuk(
        int $karyawanId,
        int $jenisCutiId,
        int $tahun,
        ?int $kecualiCutiId = null,
        int $semester = 0
    ): int {
        $query = static::where('karyawan_id', $karyawanId)
            ->where('jenis_cuti_id', $jenisCutiId)
            ->where('status', 'pending')
            ->whereYear('tanggal_mulai', $tahun)
            ->when($kecualiCutiId, fn ($q) => $q->where('id', '!=', $kecualiCutiId));

        if ($semester > 0) {
            [$bulanAwal, $bulanAkhir] = $semester === 1 ? [1, 6] : [7, 12];
            $query->whereMonth('tanggal_mulai', '>=', $bulanAwal)
                ->whereMonth('tanggal_mulai', '<=', $bulanAkhir);
        }

        return (int) $query->sum('jumlah_hari');
    }

    /**
     * Sinkronkan ulang Jadwal & Absensi untuk cuti ini TANPA menyentuh kuota.
     *
     * Ini bagian dari afterApprove() yang aman diulang (idempoten). Dipakai
     * oleh command absensi:backfill-cuti-dinas — jangan panggil afterApprove()
     * untuk keperluan re-sync, karena sejak fase 22 method itu juga menaikkan
     * KuotaCuti.terpakai dan akan menghitung ganda kalau dijalankan lagi.
     */
    public function resyncJadwalDanAbsensi(): void
    {
        $this->sinkronisasiJadwalDanAbsensi('cuti');
    }

    public function afterApprove(): void
    {
        if ($this->jenisCuti->potong_kuota) {
            DB::transaction(function () {
                $tahun = $this->tanggal_mulai->year;
                $semester = $this->jenisCuti->semesterDari($this->tanggal_mulai);

                KuotaCuti::pastikanUntuk(
                    $this->karyawan_id,
                    $this->jenis_cuti_id,
                    $tahun,
                    $this->jenisCuti->default_kuota,
                    $semester
                );

                $kuota = $this->karyawan->kuotaCutis()
                    ->where('jenis_cuti_id', $this->jenis_cuti_id)
                    ->where('tahun', $tahun)
                    ->where('semester', $semester)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($kuota->terpakai + $this->jumlah_hari > $kuota->kuota) {
                    throw new KuotaCutiTidakCukupException(
                        "Kuota {$this->jenisCuti->nama} tahun {$tahun}".
                        ($semester > 0 ? " semester {$semester}" : '')." tidak cukup. ".
                        "Sisa: {$kuota->sisa} hari, diajukan: {$this->jumlah_hari} hari."
                    );
                }

                $kuota->increment('terpakai', $this->jumlah_hari);
            });
        }

        $this->sinkronisasiJadwalDanAbsensi('cuti');
    }
}
