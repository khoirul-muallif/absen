<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\TukarJadwal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $sudahAdaJadwal = Jadwal::where('karyawan_id', $karyawan->id)
            ->where('tanggal', $tanggalBaru->toDateString())
            ->where('id', '!=', $jadwal->id)
            ->exists();

        if ($sudahAdaJadwal) {
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

        // Konflik jadwal existing (kalau ditukar, masing-masing sudah punya jadwal lain di tanggal pasangannya?)
        $konflikAsal = Jadwal::where('karyawan_id', $jadwalAsal->karyawan_id)
            ->where('tanggal', $jadwalTujuan->tanggal)
            ->where('id', '!=', $jadwalAsal->id)
            ->exists();

        $konflikTujuan = Jadwal::where('karyawan_id', $jadwalTujuan->karyawan_id)
            ->where('tanggal', $jadwalAsal->tanggal)
            ->where('id', '!=', $jadwalTujuan->id)
            ->exists();

        if ($konflikAsal || $konflikTujuan) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa ditukar: salah satu karyawan sudah punya jadwal sendiri di tanggal pasangannya.',
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

        $pengajuan = TukarJadwal::with(['jadwal.shift', 'jadwalTujuan.shift', 'karyawanTujuan', 'karyawanPengaju'])
            ->where(fn ($q) => $q->where('karyawan_pengaju_id', $karyawan->id)
                ->orWhere('karyawan_tujuan_id', $karyawan->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pengajuan,
        ]);
    }

    /**
     * GET /api/tukar-jadwal/menunggu-respon-saya
     * Daftar pengajuan tukar yang menunggu respon karyawan yang login (sebagai rekan tujuan)
     */
    public function menungguResponSaya(Request $request): JsonResponse
    {
        $karyawan = $request->user();

        $pengajuan = TukarJadwal::with(['jadwal.shift', 'jadwal.karyawan', 'jadwalTujuan.shift'])
            ->where('karyawan_tujuan_id', $karyawan->id)
            ->where('status', 'menunggu_rekan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pengajuan,
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

        $tukarJadwal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil dibatalkan.',
        ]);
    }
}
