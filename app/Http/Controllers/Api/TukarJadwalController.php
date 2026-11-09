<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\TukarJadwal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Notifications\TukarJadwalDiajukan;
use App\Notifications\TukarJadwalDibatalkanPengaju;
use Illuminate\Validation\ValidationException;

class TukarJadwalController extends Controller
{
    /**
     * POST /api/tukar-jadwal
     */
    public function ajukan(Request $request): JsonResponse
    {
        $karyawan = $request->user();

        $request->validate([
            'mode' => 'required|in:tukar,pindah',
            'jadwal_id' => 'required|integer|exists:jadwals,id',
            'jadwal_tujuan_id' => 'required_if:mode,tukar|nullable|integer|exists:jadwals,id|different:jadwal_id',
            'tanggal_baru' => 'required_if:mode,pindah|nullable|date',
            'alasan' => 'required|string',
        ]);


        $jadwal = Jadwal::findOrFail($request->jadwal_id);

        // Pastikan jadwal yang dipilih memang milik karyawan yang login
        if ($jadwal->karyawan_id !== $karyawan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini bukan milik Anda.',
            ], 403);
        }

        if (self::isLewat($jadwal->tanggal)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa mengajukan tukar/pindah untuk jadwal yang sudah lewat.',
            ], 422);
        }

        // Cek 1: rebutan dengan pengajuan lain yang masih aktif (belum final)
        $konflikJadwalAsal = TukarJadwal::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
            ->where(fn ($q) => $q->where('jadwal_id', $jadwal->id)->orWhere('jadwal_tujuan_id', $jadwal->id))
            ->exists();

        if ($konflikJadwalAsal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini sudah dipakai di pengajuan tukar/pindah lain yang masih diproses.',
            ], 422);
        }

        if ($request->mode === 'pindah') {
            return $this->ajukanPindah($karyawan, $jadwal, $request);
        }

        return $this->ajukanTukar($karyawan, $jadwal, $request);
    }

    protected function ajukanPindah($karyawan, Jadwal $jadwal, Request $request): JsonResponse
    {
        $tanggalBaru = \Carbon\Carbon::parse($request->tanggal_baru);
        if ($jadwal->jenis === Jadwal::JENIS_LIBUR) {
            return response()->json([
                'success' => false,
                'message' => 'Hari libur hanya bisa dipindah lewat mode Tukar dengan rekan, bukan Pindah Tanggal.',
            ], 422);
        }
        if (self::isLewat($tanggalBaru)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa memindah ke tanggal yang sudah lewat.',
            ], 422);
        }

        // Tujuan pindah harus hari libur milik sendiri (kebijakan fase 50/66).
        // approveAndSwap() menerima 2 kasus: tidak ada baris sama sekali (tinggal
        // dipindah), atau ada baris existing berjenis libur (ditukar posisi).
        // Baris existing SELAIN libur tetap harus ditolak di sini.
        $existingTujuan = Jadwal::where('karyawan_id', $karyawan->id)
            ->where('tanggal', $tanggalBaru->toDateString())
            ->where('id', '!=', $jadwal->id)
            ->first();

        if ($existingTujuan && $existingTujuan->jenis !== Jadwal::JENIS_LIBUR) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah punya jadwal di tanggal tersebut.',
            ], 422);
        }

        if (TukarJadwal::karyawanCutiDinasApproved($karyawan->id, $tanggalBaru)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sedang cuti/dinas (disetujui) pada tanggal tersebut.',
            ], 422);
        }

        $tukarJadwal = TukarJadwal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal_baru' => $tanggalBaru,
            'alasan' => $request->alasan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pindah jadwal berhasil diajukan, menunggu approval admin.',
            'data' => ['id' => $tukarJadwal->id, 'status' => $tukarJadwal->status],
        ], 201);
    }

    protected function ajukanTukar($karyawan, Jadwal $jadwalAsal, Request $request): JsonResponse
    {
        $jadwalTujuan = Jadwal::findOrFail($request->jadwal_tujuan_id);

        if (self::isLewat($jadwalTujuan->tanggal)) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal rekan tujuan sudah lewat, tidak bisa ditukar.',
            ], 422);
        }

        if ($jadwalTujuan->karyawan_id === $karyawan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menukar dengan jadwal milik sendiri.',
            ], 422);
        }

        // Cek rebutan untuk sisi jadwal tujuan juga
        $konflikJadwalTujuan = TukarJadwal::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
            ->where(fn ($q) => $q->where('jadwal_id', $jadwalTujuan->id)->orWhere('jadwal_tujuan_id', $jadwalTujuan->id))
            ->exists();

        if ($konflikJadwalTujuan) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal rekan tujuan sudah dipakai di pengajuan lain yang masih diproses.',
            ], 422);
        }

        // Konflik jadwal existing (kalau ditukar, masing-masing sudah punya jadwal lain
        // di tanggal pasangannya?). KEBIJAKAN (2 Nov 2026): kalau baris yang bentrok itu
        // LIBUR, tetap diizinkan -- baris libur itu akan diserap/ditimpa oleh hasil swap
        // saat approve (lihat TukarJadwal::approveAndSwap()). Kalau bentrok dengan shift
        // KERJA sungguhan, tetap ditolak -- itu akan menghilangkan cover shift orang lain
        // tanpa ketahuan siapa pun.
        $existingDiTujuan = Jadwal::where('karyawan_id', $jadwalAsal->karyawan_id)
            ->where('tanggal', $jadwalTujuan->tanggal)
            ->where('id', '!=', $jadwalAsal->id)
            ->first();

        $existingDiAsal = Jadwal::where('karyawan_id', $jadwalTujuan->karyawan_id)
            ->where('tanggal', $jadwalAsal->tanggal)
            ->where('id', '!=', $jadwalTujuan->id)
            ->first();

        if (($existingDiTujuan && $existingDiTujuan->jenis !== Jadwal::JENIS_LIBUR)
            || ($existingDiAsal && $existingDiAsal->jenis !== Jadwal::JENIS_LIBUR)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa ditukar: salah satu karyawan sudah punya jadwal KERJA sendiri di tanggal pasangannya.',
            ], 422);
        }

        // Cek cuti/dinas approved di 4 kombinasi karyawan×tanggal
        if (TukarJadwal::karyawanCutiDinasApproved($jadwalAsal->karyawan_id, $jadwalAsal->tanggal)
            || TukarJadwal::karyawanCutiDinasApproved($jadwalAsal->karyawan_id, $jadwalTujuan->tanggal)
            || TukarJadwal::karyawanCutiDinasApproved($jadwalTujuan->karyawan_id, $jadwalTujuan->tanggal)
            || TukarJadwal::karyawanCutiDinasApproved($jadwalTujuan->karyawan_id, $jadwalAsal->tanggal)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa ditukar: salah satu karyawan sedang cuti/dinas (disetujui) di tanggal yang terlibat.',
            ], 422);
        }

        $tukarJadwal = TukarJadwal::create([
            'jadwal_id' => $jadwalAsal->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => $request->alasan,
        ]);

        // Sengaja di controller, BUKAN di model event `created` — factory dan
        // SimulasiMassalSeeder juga membuat baris TukarJadwal, dan itu akan
        // memuntahkan notifikasi massal saat seeding.
        $tukarJadwal->karyawanTujuan?->notify(new TukarJadwalDiajukan($tukarJadwal));

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan tukar jadwal berhasil diajukan, menunggu persetujuan rekan.',
            'data' => ['id' => $tukarJadwal->id, 'status' => $tukarJadwal->status],
        ], 201);
    }

    /**
     * GET /api/tukar-jadwal
     */
    public function riwayat(Request $request): JsonResponse
    {
        $karyawan = $request->user();
        $status = $request->query('status');

        $pengajuan = TukarJadwal::with(['shiftAsal', 'shiftTujuan', 'karyawanTujuan', 'karyawanPengaju'])
            ->where(fn ($q) => $q->where('karyawan_pengaju_id', $karyawan->id)
                ->orWhere('karyawan_tujuan_id', $karyawan->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => self::formatTukarJadwal($t));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $pengajuan->count(),
                'records' => $pengajuan,
            ],
        ]);
    }

    /**
     * GET /api/tukar-jadwal/menunggu-respon-saya
     * Daftar pengajuan tukar yang menunggu respon karyawan yang login (sebagai rekan tujuan)
     */
    public function menungguResponSaya(Request $request): JsonResponse
    {
        $karyawan = $request->user();

        $pengajuan = TukarJadwal::with(['shiftAsal', 'shiftTujuan', 'karyawanPengaju'])
            ->where('karyawan_tujuan_id', $karyawan->id)
            ->where('status', 'menunggu_rekan')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => self::formatTukarJadwal($t));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $pengajuan->count(),
                'records' => $pengajuan,
            ],
        ]);
    }

    /**
     * POST /api/tukar-jadwal/{id}/respon-rekan
     */
    public function responRekan(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'setuju' => 'required|boolean',
            'catatan' => 'nullable|string',
        ]);

        $karyawan = $request->user();
        $tukarJadwal = TukarJadwal::findOrFail($id);

        try {
            $tukarJadwal->responRekan($karyawan, $request->boolean('setuju'), $request->catatan);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $request->boolean('setuju')
                ? 'Persetujuan diterima, menunggu approval admin.'
                : 'Pengajuan ditolak.',
            'data' => ['id' => $tukarJadwal->id, 'status' => $tukarJadwal->fresh()->status],
        ]);
    }

    /**
     * DELETE /api/tukar-jadwal/{id}
     */
    public function batalkan(Request $request, int $id): JsonResponse
    {
        $karyawan = $request->user();

        $tukarJadwal = TukarJadwal::where('karyawan_pengaju_id', $karyawan->id)->findOrFail($id);

        if (! in_array($tukarJadwal->status, ['menunggu_rekan', 'menunggu_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan ini sudah tidak bisa dibatalkan (sudah diproses).',
            ], 422);
        }

        // Relasi di-load dan notifikasi disiapkan SEBELUM delete(): setelah
        // barisnya hilang, karyawanTujuan/shiftAsal tidak bisa di-load lagi
        // dan labelJadwalAsal() kehilangan datanya.
        $tukarJadwal->loadMissing(['karyawanPengaju', 'karyawanTujuan', 'shiftAsal', 'shiftTujuan']);

        $rekan = $tukarJadwal->isPindahSendiri() ? null : $tukarJadwal->karyawanTujuan;
        $notifikasi = $rekan
            ? new TukarJadwalDibatalkanPengaju($tukarJadwal, $tukarJadwal->status)
            : null;

        $tukarJadwal->delete();

        $rekan?->notify($notifikasi);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil dibatalkan.',
        ]);
    }

    private static function formatTukarJadwal(TukarJadwal $t): array
    {
        return [
            'id' => $t->id,
            'mode' => $t->isPindahSendiri() ? 'pindah' : 'tukar',
            'status' => $t->status,
            'alasan' => $t->alasan,
            'jadwal_asal' => [
                'tanggal' => $t->tanggal_asal->format('d M Y'),
                'shift' => $t->shiftAsal?->nama_shift,
                'karyawan' => $t->karyawanPengaju->nama,
                'karyawan_id' => $t->karyawan_pengaju_id,
            ],
            'jadwal_tujuan' => ! $t->isPindahSendiri() ? [
                'tanggal' => $t->tanggal_tujuan->format('d M Y'),
                'shift' => $t->shiftTujuan?->nama_shift,
                'karyawan' => $t->karyawanTujuan?->nama,
                'karyawan_id' => $t->karyawan_tujuan_id,
            ] : null,
            'tanggal_baru' => $t->tanggal_baru?->format('d M Y'),
            'catatan_penolakan_rekan' => $t->catatan_penolakan_rekan,
            'catatan_approval' => $t->catatan_approval,
            'diajukan_at' => $t->created_at->format('d M Y H:i'),
        ];
    }
    /**
     * KEPUTUSAN (30 Okt 2026): tanggal yang sudah lewat tidak boleh dipakai
     * di tukar/pindah jadwal, baik sisi asal maupun tujuan. Jendela mundur
     * 30 hari yang sebelumnya ada di FE cuma kemudahan testing, bukan
     * keputusan bisnis -- dicabut sepenuhnya di sini.
     */
    private static function isLewat(\Carbon\Carbon $tanggal): bool
    {
        return $tanggal->copy()->startOfDay()->lt(now()->startOfDay());
    }
}
