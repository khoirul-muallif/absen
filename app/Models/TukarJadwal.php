<?php

namespace App\Models;

use App\Models\User;
use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TukarJadwal extends Model
{
    use HasApprovalWorkflow;

    protected $fillable = [
        'jadwal_id', 'jadwal_tujuan_id', 'alasan',
        'status', 'approved_by', 'approved_at', 'catatan_approval',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function jadwalTujuan(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_tujuan_id');
    }

    /**
     * Approve sekaligus nukar karyawan_id di kedua jadwal yang terlibat.
     */
    public function approveAndSwap(User $approver, ?string $catatan = null): bool
{
    return DB::transaction(function () use ($approver, $catatan) {
        $jadwalA = Jadwal::lockForUpdate()->findOrFail($this->jadwal_id);
        $jadwalB = Jadwal::lockForUpdate()->findOrFail($this->jadwal_tujuan_id);

        $karyawanA = $jadwalA->karyawan_id;
        $karyawanB = $jadwalB->karyawan_id;
        $tanggalA = $jadwalA->tanggal;

        // Langkah 1: parkir jadwalA ke tanggal jauh yang mustahil bentrok
        $jadwalA->update(['tanggal' => now()->addYears(100)]);

        // Langkah 2: jadwalB ganti karyawan jadi karyawan A (tanggal tetap)
        $jadwalB->update(['karyawan_id' => $karyawanA]);

        // Langkah 3: jadwalA balik ke tanggal asli, karyawan jadi karyawan B
        $jadwalA->update(['karyawan_id' => $karyawanB, 'tanggal' => $tanggalA]);

        return $this->approve($approver, $catatan);
    });
}
}
