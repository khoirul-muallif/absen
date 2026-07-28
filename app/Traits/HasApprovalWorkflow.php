<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Jadwal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $catatan_approval
 * @property int $karyawan_id
 * @property \Illuminate\Support\Carbon $tanggal_mulai
 * @property \Illuminate\Support\Carbon $tanggal_selesai
 */
trait HasApprovalWorkflow
{
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(User $approver, ?string $catatan = null): bool
    {
        $result = $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'catatan_approval' => $catatan,
        ]);

        if ($result && method_exists($this, 'afterApprove')) {
            $this->afterApprove();
        }

        return $result;
    }

    public function reject(User $approver, ?string $catatan = null): bool
    {
        return $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'catatan_approval' => $catatan,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Sinkronkan Jadwal + Absensi untuk seluruh rentang tanggal_mulai–tanggal_selesai
     * berdasarkan status approval ('cuti' atau 'dinas'). Menimpa Jadwal apa pun yang
     * ada di tanggal tersebut (termasuk sumber 'manual') karena Cuti/Dinas approved
     * adalah fakta yang lebih valid daripada jadwal yang sudah diinput sebelumnya.
     */
    protected function sinkronisasiJadwalDanAbsensi(string $status): void
    {
        $periode = \Carbon\CarbonPeriod::create($this->tanggal_mulai, $this->tanggal_selesai);

        foreach ($periode as $tanggal) {
            Jadwal::updateOrCreate(
                ['karyawan_id' => $this->karyawan_id, 'tanggal' => $tanggal->toDateString()],
                ['shift_id' => null, 'jenis' => $status, 'sumber' => 'generate']
            );

            $absensi = \App\Models\Absensi::firstOrNew(
                ['karyawan_id' => $this->karyawan_id, 'tanggal' => $tanggal->toDateString()]
            );

            $absensi->status = $status;
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
