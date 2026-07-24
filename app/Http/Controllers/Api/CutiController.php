<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cuti;
use App\Models\JenisCuti;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    /**
     * GET /api/cuti/kuota?tahun=2026
     * Lihat sisa kuota semua jenis cuti untuk karyawan yang login
     */
    public function kuota(Request $request): JsonResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $karyawan = $request->user();

        $jenisCutiAktif = JenisCuti::where('is_active', true)->get();

        $data = $jenisCutiAktif->map(function ($jenis) use ($karyawan, $tahun) {
            $kuota = $karyawan->kuotaCutis()
                ->where('jenis_cuti_id', $jenis->id)
                ->where('tahun', $tahun)
                ->first();

            return [
                'jenis_cuti_id'  => $jenis->id,
                'nama'           => $jenis->nama,
                'potong_kuota'   => $jenis->potong_kuota,
                'perlu_lampiran' => $jenis->perlu_lampiran,
                'kuota'          => $kuota->kuota ?? $jenis->default_kuota,
                'terpakai'       => $kuota->terpakai ?? 0,
                'sisa'           => $kuota ? $kuota->sisa : $jenis->default_kuota,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'tahun'  => $tahun,
                'records' => $data,
            ],
        ]);
    }

    /**
     * POST /api/cuti
     * Ajukan cuti baru
     */
    public function ajukan(Request $request): JsonResponse
    {
        $request->validate([
            'jenis_cuti_id'   => 'required|exists:jenis_cutis,id',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan'          => 'required|string|max:1000',
            'lampiran'        => 'nullable|file|max:2048',
        ]);

        $karyawan  = $request->user();
        $jenisCuti = JenisCuti::findOrFail($request->jenis_cuti_id);

        if (! $jenisCuti->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis cuti ini sudah tidak aktif.',
            ], 422);
        }

        if ($jenisCuti->perlu_lampiran && ! $request->hasFile('lampiran')) {
            return response()->json([
                'success' => false,
                'message' => "Jenis cuti \"{$jenisCuti->nama}\" wajib melampirkan surat keterangan.",
            ], 422);
        }

        $tanggalMulai   = Carbon::parse($request->tanggal_mulai);
        $tanggalSelesai = Carbon::parse($request->tanggal_selesai);
        $jumlahHari     = $tanggalMulai->diffInDays($tanggalSelesai) + 1;

        // Cek sisa kuota kalau jenis ini memotong kuota
        if ($jenisCuti->potong_kuota) {
            $kuota = $karyawan->kuotaCutis()
                ->where('jenis_cuti_id', $jenisCuti->id)
                ->where('tahun', $tanggalMulai->year)
                ->first();

            $sisaKuota = $kuota ? $kuota->sisa : $jenisCuti->default_kuota;

            if ($jumlahHari > $sisaKuota) {
                return response()->json([
                    'success' => false,
                    'message' => "Sisa kuota {$jenisCuti->nama} Anda tahun {$tanggalMulai->year} tinggal {$sisaKuota} hari, tidak cukup untuk {$jumlahHari} hari yang diajukan.",
                    'data'    => ['sisa_kuota' => $sisaKuota],
                ], 422);
            }
        }

        $lampiranPath = $request->hasFile('lampiran')
            ? $request->file('lampiran')->store('lampiran-cuti', 'public')
            : null;

        $cuti = Cuti::create([
            'karyawan_id'     => $karyawan->id,
            'jenis_cuti_id'   => $jenisCuti->id,
            'tanggal_mulai'   => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'jumlah_hari'     => $jumlahHari,
            'alasan'          => $request->alasan,
            'lampiran'        => $lampiranPath,
            'status'          => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dikirim, menunggu persetujuan.',
            'data'    => [
                'id'          => $cuti->id,
                'jenis_cuti'  => $jenisCuti->nama,
                'tanggal_mulai'   => $cuti->tanggal_mulai->format('d M Y'),
                'tanggal_selesai' => $cuti->tanggal_selesai->format('d M Y'),
                'jumlah_hari' => $cuti->jumlah_hari,
                'status'      => $cuti->status,
            ],
        ], 201);
    }

    /**
     * GET /api/cuti?status=pending
     * Riwayat pengajuan cuti milik karyawan yang login
     */
    public function riwayat(Request $request): JsonResponse
    {
        $query = $request->user()->cutis()->with('jenisCuti')->latest('tanggal_mulai');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $cuti = $query->get()->map(fn ($c) => [
            'id'               => $c->id,
            'jenis_cuti'       => $c->jenisCuti->nama,
            'tanggal_mulai'    => $c->tanggal_mulai->format('d M Y'),
            'tanggal_selesai'  => $c->tanggal_selesai->format('d M Y'),
            'jumlah_hari'      => $c->jumlah_hari,
            'alasan'           => $c->alasan,
            'status'           => $c->status,
            'catatan_approval' => $c->catatan_approval,
            'diajukan_at'      => $c->created_at->format('d M Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => $cuti->count(),
                'records' => $cuti,
            ],
        ]);
    }

    /**
     * DELETE /api/cuti/{id}
     * Batalkan pengajuan cuti — hanya boleh kalau masih pending
     * (setelah approved, sudah menyentuh kuota + Absensi — pembatalan
     * harus lewat admin, bukan self-service)
     */
    public function batalkan(Request $request, string $id): JsonResponse
    {
        $cuti = $request->user()->cutis()->where('id', $id)->first();

        if (! $cuti) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan cuti tidak ditemukan.',
            ], 404);
        }

        if (! $cuti->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan cuti yang sudah diproses tidak bisa dibatalkan sendiri. Hubungi admin.',
            ], 422);
        }

        $cuti->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti dibatalkan.',
        ]);
    }
}
