<?php

namespace App\Models;

use App\Exceptions\TukarJadwalKonflikException;
use App\Traits\HasApprovalWorkflow;
use App\Notifications\TukarJadwalDiproses;
use App\Notifications\TukarJadwalDiresponRekan;
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

    public function labelJadwalAsal(): string
    {
        return $this->tanggal_asal->format('d M Y')
            . ' (' . ($this->shiftAsal?->nama_shift ?? 'Libur') . ')';
    }

    public function labelJadwalTujuan(): ?string
    {
        if ($this->isPindahSendiri()) {
            return null;
        }

        return $this->tanggal_tujuan?->format('d M Y')
            . ' (' . ($this->shiftTujuan?->nama_shift ?? 'Libur') . ')';
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
            throw new TukarJadwalKonflikException('Pengajuan ini bukan lagi tahap menunggu respon rekan.');
        }

        if ($rekan->id !== $this->karyawan_tujuan_id) {
            throw new TukarJadwalKonflikException('Anda bukan pihak yang dituju pada pengajuan ini.');
        }

        $berhasil = $this->update([
            'status' => $setuju ? 'menunggu_admin' : 'ditolak_rekan',
            'direspon_oleh_rekan_id' => $rekan->id,
            'direspon_rekan_at' => now(),
            'catatan_penolakan_rekan' => $setuju ? null : $catatan,
        ]);

        if ($berhasil) {
            $this->loadMissing(['karyawanPengaju', 'karyawanTujuan', 'shiftAsal', 'shiftTujuan']);
            $this->karyawanPengaju?->notify(new TukarJadwalDiresponRekan($this, $setuju));
        }

        return $berhasil;
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
            throw new TukarJadwalKonflikException(
                'Gagal: pengajuan ini belum berstatus menunggu_admin (kemungkinan masih '
                . 'menunggu respon rekan, atau sudah selesai diproses sebelumnya).'
            );
        }

        $berhasil = DB::transaction(function () use ($approver, $catatan) {
            $jadwalA = Jadwal::lockForUpdate()->findOrFail($this->jadwal_id);

            if ($jadwalA->karyawan_id !== $this->karyawan_pengaju_id) {
                throw new TukarJadwalKonflikException(
                    'Gagal: jadwal pengaju sudah berubah kepemilikan sejak pengajuan ini dibuat. '
                    . 'Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                );
            }

            if ($this->isPindahSendiri()) {
                $existing = Jadwal::lockForUpdate()
                    ->where('karyawan_id', $jadwalA->karyawan_id)
                    ->where('tanggal', $this->tanggal_baru)
                    ->first();

                if (! $existing) {
                    $jadwalA->update(['tanggal' => $this->tanggal_baru, 'sumber' => 'manual']);
                } elseif ($existing->jenis === Jadwal::JENIS_LIBUR) {
                    $jenisAsal = $jadwalA->jenis;
                    $shiftIdAsal = $jadwalA->shift_id;

                    $jadwalA->update(['jenis' => Jadwal::JENIS_LIBUR, 'shift_id' => null, 'sumber' => 'manual']);
                    $existing->update(['jenis' => $jenisAsal, 'shift_id' => $shiftIdAsal, 'sumber' => 'manual']);
                } else {
                    throw new TukarJadwalKonflikException(
                        'Gagal: tanggal tujuan sudah terisi Jadwal lain (bukan libur) sejak pengajuan '
                        . 'dibuat. Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                    );
                }
            } else {
                $jadwalB = Jadwal::lockForUpdate()->findOrFail($this->jadwal_tujuan_id);

                if ($jadwalB->karyawan_id !== $this->karyawan_tujuan_id) {
                    throw new TukarJadwalKonflikException(
                        'Gagal: jadwal tujuan sudah berubah kepemilikan sejak pengajuan ini dibuat. '
                        . 'Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                    );
                }

                $karyawanA = $jadwalA->karyawan_id;
                $karyawanB = $jadwalB->karyawan_id;
                $tanggalA  = $jadwalA->tanggal;
                $tanggalB  = $jadwalB->tanggal;

                // Re-cek race condition: baris di tanggal pasangan bisa saja berubah sejak
                // pengajuan dibuat (ajukan() sudah validasi ini, tapi approve bisa terjadi
                // belakangan). Baris LIBUR boleh diserap (dihapus, karena hasil swap di
                // bawah jadi representasi final tanggal itu); baris KERJA menggagalkan
                // approve seluruhnya.
                $existingDiTujuan = Jadwal::lockForUpdate()
                    ->where('karyawan_id', $karyawanA)
                    ->where('tanggal', $tanggalB)
                    // exclude kedua baris yang sedang di-swap sendiri -- kalau tanggalA
                    // == tanggalB (tukar tanggal sama), jadwalA bisa match query ini.
                    ->whereNotIn('id', [$jadwalA->id, $jadwalB->id])

                    ->first();

                $existingDiAsal = Jadwal::lockForUpdate()
                    ->where('karyawan_id', $karyawanB)
                    ->where('tanggal', $tanggalA)
                    ->whereNotIn('id', [$jadwalA->id, $jadwalB->id])
                    ->first();

                foreach ([$existingDiTujuan, $existingDiAsal] as $existing) {
                    if (! $existing) {
                        continue;
                    }
                    if ($existing->jenis !== Jadwal::JENIS_LIBUR) {
                        throw new TukarJadwalKonflikException(
                            'Gagal: salah satu karyawan sudah punya jadwal KERJA sendiri di tanggal '
                            . 'pasangannya sejak pengajuan ini dibuat. Tolak pengajuan ini dan minta '
                            . 'karyawan mengajukan ulang.'
                        );
                    }
                    $dipakaiPengajuanLain = self::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
                        ->where('id', '!=', $this->id)
                        ->where(fn ($q) => $q->where('jadwal_id', $existing->id)->orWhere('jadwal_tujuan_id', $existing->id))
                        ->exists();
                    if ($dipakaiPengajuanLain) {
                        throw new TukarJadwalKonflikException(
                            'Gagal: baris libur yang akan diserap sedang dipakai pengajuan tukar/pindah '
                            . 'lain yang masih diproses. Selesaikan pengajuan itu dulu.'
                        );
                    }
                }

                try {
                    $jadwalA->update(['tanggal' => now()->addYears(100)]);
                    $existingDiTujuan?->delete();
                    $existingDiAsal?->delete();
                    $jadwalB->update(['karyawan_id' => $karyawanA, 'sumber' => 'manual']);
                    $jadwalA->update(['karyawan_id' => $karyawanB, 'tanggal' => $tanggalA, 'sumber' => 'manual']);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    throw new TukarJadwalKonflikException(
                        'Gagal: salah satu karyawan sudah punya jadwal sendiri di tanggal pasangannya. '
                        . 'Tolak pengajuan ini dan minta karyawan mengajukan ulang.'
                    );
                }
            }

            return $this->approve($approver, $catatan);
        });

        // Sengaja DI LUAR transaksi: kalau swap gagal di tengah, transaksi
        // rollback tapi notifikasi yang sudah terkirim tidak bisa ikut ditarik.
        if ($berhasil) {
            $this->kirimNotifikasiHasilAdmin(TukarJadwalDiproses::HASIL_APPROVED);
        }

        return $berhasil;
    }

    /**
     * Override dari HasApprovalWorkflow: trait-nya dipakai 5 model, jadi
     * notifikasi khusus TukarJadwal dipasang di sini, bukan di trait.
     */
    public function reject(User $approver, ?string $catatan = null): bool
    {
        $berhasil = $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'catatan_approval' => $catatan,
        ]);

        if ($berhasil) {
            $this->kirimNotifikasiHasilAdmin(TukarJadwalDiproses::HASIL_REJECTED);
        }

        return $berhasil;
    }

    protected function kirimNotifikasiHasilAdmin(string $hasil): void
    {
        $this->refresh()->loadMissing([
            'karyawanPengaju', 'karyawanTujuan', 'shiftAsal', 'shiftTujuan',
        ]);

        $this->karyawanPengaju?->notify(new TukarJadwalDiproses($this, $hasil));

        if (! $this->isPindahSendiri()) {
            $this->karyawanTujuan?->notify(new TukarJadwalDiproses($this, $hasil));
        }
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
    /**
     * Dipanggil ADMIN LEWAT FILAMENT SAJA (tidak ada endpoint API untuk ini —
     * keputusan RS 8 Okt 2026: admin-only) untuk membatalkan pengajuan yang
     * macet di menunggu_rekan karena rekan tujuan tidak kunjung merespons.
     *
     * Status diarahkan ke 'rejected' (bukan enum baru) — tidak ada reservasi
     * Jadwal yang perlu di-rollback, karena menunggu_rekan murni status flag:
     * jadwal pengaju & rekan tujuan tetap utuh sampai approveAndSwap() benar2
     * dijalankan. "Kuncian" anti-rebutan di TukarJadwalController::ajukan()
     * otomatis lepas begitu status keluar dari menunggu_rekan/menunggu_admin.
     */
    public function batalkanOlehAdmin(User $admin, string $catatan): bool
    {
        if ($this->status !== 'menunggu_rekan') {
            throw new TukarJadwalKonflikException(
                'Hanya pengajuan berstatus menunggu_rekan yang bisa dibatalkan lewat tombol ini. '
                . 'Pengajuan yang sudah menunggu_admin bisa ditolak lewat tombol Tolak biasa.'
            );
        }

        $berhasil = $this->update([
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'catatan_approval' => "[Dibatalkan admin — rekan tidak merespons] {$catatan}",
        ]);

        if ($berhasil) {
            $this->kirimNotifikasiHasilAdmin(TukarJadwalDiproses::HASIL_DIBATALKAN_ADMIN);
        }

        return $berhasil;
    }
}
