<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TukarJadwal extends Model
{
    use HasApprovalWorkflow;

    protected $fillable = [
        'jadwal_id', 'karyawan_pengaju_id', 'tanggal_asal', 'shift_asal_id',
        'jadwal_tujuan_id', 'karyawan_tujuan_id', 'tanggal_tujuan', 'shift_tujuan_id',
        'tanggal_baru', 'alasan',
        'status', 'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'tanggal_asal'   => 'date',
        'tanggal_tujuan' => 'date',
        'tanggal_baru'   => 'date',
        'approved_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TukarJadwal $tukarJadwal) {
            // Snapshot sisi pengaju
            $jadwal = Jadwal::find($tukarJadwal->jadwal_id);
            if ($jadwal) {
                $tukarJadwal->karyawan_pengaju_id = $jadwal->karyawan_id;
                $tukarJadwal->tanggal_asal        = $jadwal->tanggal;
                $tukarJadwal->shift_asal_id       = $jadwal->shift_id;
            }

            // Snapshot sisi tujuan (cuma kalau mode tukar)
            if ($tukarJadwal->jadwal_tujuan_id) {
                $jadwalTujuan = Jadwal::find($tukarJadwal->jadwal_tujuan_id);
                if ($jadwalTujuan) {
                    $tukarJadwal->karyawan_tujuan_id = $jadwalTujuan->karyawan_id;
                    $tukarJadwal->tanggal_tujuan     = $jadwalTujuan->tanggal;
                    $tukarJadwal->shift_tujuan_id    = $jadwalTujuan->shift_id;
                }
            }
        });
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function jadwalTujuan(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_tujuan_id');
    }

    public function karyawanPengaju(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_pengaju_id');
    }

    public function karyawanTujuan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_tujuan_id');
    }

    public function shiftAsal(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_asal_id');
    }

    public function shiftTujuan(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_tujuan_id');
    }

    public function isPindahSendiri(): bool
    {
        return is_null($this->jadwal_tujuan_id);
    }

    /**
     * Approve sekaligus menerapkan perubahan jadwal.
     *
     * FIX race condition: sebelum eksekusi swap/pindah, validasi ulang bahwa
     * kepemilikan jadwal yang direferensikan masih SAMA dengan snapshot saat
     * pengajuan dibuat. Kalau sudah berubah (misal jadwal tujuan sudah
     * ditukar duluan lewat pengajuan lain), tolak dengan pesan jelas —
     * daripada diam-diam nuker jadwal orang yang salah.
     */
    public function approveAndSwap(User $approver, ?string $catatan = null): bool
    {
        return DB::transaction(function () use ($approver, $catatan) {
            $jadwalA = Jadwal::lockForUpdate()->findOrFail($this->jadwal_id);

            if ($jadwalA->karyawan_id !== $this->karyawan_pengaju_id) {
                throw new \Exception(
                    'Gagal: jadwal pengaju sudah berubah kepemilikan sejak pengajuan ini dibuat. '
                    . 'Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                );
            }

            if ($this->isPindahSendiri()) {
                $jadwalA->update(['tanggal' => $this->tanggal_baru]);
            } else {
                $jadwalB = Jadwal::lockForUpdate()->findOrFail($this->jadwal_tujuan_id);

                if ($jadwalB->karyawan_id !== $this->karyawan_tujuan_id) {
                    throw new \Exception(
                        'Gagal: jadwal rekan tujuan sudah berubah kepemilikan sejak pengajuan ini dibuat '
                        . '(kemungkinan sudah ditukar lewat pengajuan lain). '
                        . 'Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                    );
                }

                $karyawanA = $jadwalA->karyawan_id;
                $karyawanB = $jadwalB->karyawan_id;
                $tanggalA  = $jadwalA->tanggal;

                try {
                    $jadwalA->update(['tanggal' => now()->addYears(100)]);
                    $jadwalB->update(['karyawan_id' => $karyawanA]);
                    $jadwalA->update(['karyawan_id' => $karyawanB, 'tanggal' => $tanggalA]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    throw new \Exception(
                        'Gagal menukar jadwal: salah satu karyawan sudah memiliki jadwal sendiri '
                        . 'di tanggal pasangannya.'
                    );
                }
            }

            return $this->approve($approver, $catatan);
        });
    }
}
