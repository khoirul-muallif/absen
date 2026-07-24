<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dinas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DinasController extends Controller
{
    /**
     * POST /api/dinas
     * Ajukan dinas luar baru
     */
    public function ajukan(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'tujuan'          => 'required|string|max:255',
            'keperluan'       => 'required|string|max:1000',
        ]);

        $karyawan = $request->user();

        $tanggalMulai   = Carbon::parse($request->tanggal_mulai);
        $tanggalSelesai = Carbon::parse($request->tanggal_selesai);

        $dinas = Dinas::create([
            'karyawan_id'     => $karyawan->id,
            'tanggal_mulai'   => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'tujuan'          => $request->tujuan,
            'keperluan'       => $request->keperluan,
            'status'          => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan dinas berhasil dikirim, menunggu persetujuan.',
            'data'    => [
                'id'              => $dinas->id,
                'tujuan'          => $dinas->tujuan,
                'tanggal_mulai'   => $dinas->tanggal_mulai->format('d M Y'),
                'tanggal_selesai' => $dinas->tanggal_selesai->format('d M Y'),
                'status'          => $dinas->status,
            ],
        ], 201);
    }

    /**
     * GET /api/dinas?status=pending
     * Riwayat pengajuan dinas milik karyawan yang login
     */
    public function riwayat(Request $request): JsonResponse
    {
        $query = $request->user()->dinas()->latest('tanggal_mulai');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $dinas = $query->get()->map(fn ($d) => [
            'id'               => $d->id,
            'tujuan'           => $d->tujuan,
            'keperluan'        => $d->keperluan,
            'tanggal_mulai'    => $d->tanggal_mulai->format('d M Y'),
            'tanggal_selesai'  => $d->tanggal_selesai->format('d M Y'),
            'status'           => $d->status,
            'catatan_approval' => $d->catatan_approval,
            'diajukan_at'      => $d->created_at->format('d M Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => $dinas->count(),
                'records' => $dinas,
            ],
        ]);
    }

    /**
     * DELETE /api/dinas/{id}
     * Batalkan pengajuan dinas — hanya boleh kalau masih pending
     */
    public function batalkan(Request $request, string $id): JsonResponse
    {
        $dinas = $request->user()->dinas()->where('id', $id)->first();

        if (! $dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan dinas tidak ditemukan.',
            ], 404);
        }

        if (! $dinas->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan dinas yang sudah diproses tidak bisa dibatalkan sendiri. Hubungi admin.',
            ], 422);
        }

        $dinas->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan dinas dibatalkan.',
        ]);
    }
}
