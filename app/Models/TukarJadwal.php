<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TukarJadwal extends Model
{
    use HasApprovalWorkflow, HasFactory;

    protected $fillable = [
        'jadwal_id', 'karyawan_pengaju_id', 'tanggal_asal', 'shift_asal_id',
        'jadwal_tujuan_id', 'karyawan_tujuan_id', 'tanggal_tujuan', 'shift_tujuan_id',
        'tanggal_baru', 'alasan', 'status',
        'direspon_oleh_rekan_id', 'direspon_rekan_at', 'catatan_penolakan_rekan',
        'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'tanggal_asal'      => 'date',
        'tanggal_tujuan'    => 'date',
        'tanggal_baru'      => 'date',
        'direspon_rekan_at' => 'datetime',
        'approved_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TukarJadwal $tukarJadwal) {
            $jadwal = Jadwal::find($tukarJadwal->jadwal_id);
            if ($jadwal) {
                $tukarJadwal->karyawan_pengaju_id = $jadwal->karyawan_id;
                $tukarJadwal->tanggal_asal        = $jadwal->tanggal;
                $tukarJadwal->shift_asal_id       = $jadwal->shift_id;
            }

            if ($tukarJadwal->jadwal_tujuan_id) {
                $jadwalTujuan = Jadwal::find($tukarJadwal->jadwal_tujuan_id);
                if ($jadwalTujuan) {
                    $tukarJadwal->karyawan_tujuan_id = $jadwalTujuan->karyawan_id;
                    $tukarJadwal->tanggal_tujuan     = $jadwalTujuan->tanggal;
                    $tukarJadwal->shift_tujuan_id    = $jadwalTujuan->shift_id;
                }
            }

            if (! $tukarJadwal->status) {
                $tukarJadwal->status = $tukarJadwal->jadwal_tujuan_id ? 'menunggu_rekan' : 'menunggu_admin';
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

    public function scopeMenungguRekan(Builder $query): Builder
    {
        return $query->where('status', 'menunggu_rekan');
    }

    // Override dari HasApprovalWorkflow — trait pakai 'pending', TukarJadwal pakai 'menunggu_admin'
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'menunggu_admin');
    }

    public function isPending(): bool
    {
        return $this->status === 'menunggu_admin';
    }

    public function butuhResponRekan(): bool
    {
        return $this->status === 'menunggu_rekan';
    }

    /**
     * Dipanggil oleh karyawan tujuan untuk merespon pengajuan tukar
     * yang mengarah ke jadwalnya.
     */
    public function responRekan(Karyawan $rekan, bool $setuju, ?string $catatan = null): bool
    {
        if ($this->status !== 'menunggu_rekan') {
            throw new \Exception('Pengajuan ini bukan lagi tahap menunggu respon rekan.');
        }

        if ($rekan->id !== $this->karyawan_tujuan_id) {
            throw new \Exception('Anda bukan pihak yang dituju pada pengajuan ini.');
        }

        return $this->update([
            'status' => $setuju ? 'menunggu_admin' : 'ditolak_rekan',
            'direspon_oleh_rekan_id' => $rekan->id,
            'direspon_rekan_at' => now(),
            'catatan_penolakan_rekan' => $setuju ? null : $catatan,
        ]);
    }

    /**
     * Approve sekaligus menerapkan perubahan jadwal.
     *
     * FIX race condition: sebelum eksekusi swap/pindah, validasi ulang bahwa
     * kepemilikan jadwal yang direferensikan masih SAMA dengan snapshot saat
     * pengajuan dibuat.
     */
    public function approveAndSwap(User $approver, ?string $catatan = null): bool
    {
        if ($this->status !== 'menunggu_admin') {
            throw new \Exception(
                'Gagal: pengajuan ini belum berstatus menunggu_admin (kemungkinan masih '
                . 'menunggu respon rekan, atau sudah selesai diproses sebelumnya).'
            );
        }

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

    public static function karyawanCutiDinasApproved(int $karyawanId, \Carbon\Carbon $tanggal): bool
    {
        return Cuti::where('karyawan_id', $karyawanId)
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->exists()
            || Dinas::where('karyawan_id', $karyawanId)
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->exists();
    }
}
