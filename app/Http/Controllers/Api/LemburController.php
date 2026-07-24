<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lembur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * POST /api/lembur
     * Ajukan lembur baru
     */
    public function ajukan(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal'     => 'required|date',
            'jam_mulai'   => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'alasan'      => 'required|string|max:1000',
        ]);

        $karyawan = $request->user();

        $lembur = Lembur::create([
            'karyawan_id' => $karyawan->id,
            'tanggal'     => $request->tanggal,
            'jam_mulai'   => $request->jam_mulai,
            'jam_selesai' => $request->jam_selesai,
            'alasan'      => $request->alasan,
            'status'      => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur berhasil dikirim, menunggu persetujuan.',
            'data'    => [
                'id'      => $lembur->id,
                'tanggal' => $lembur->tanggal->format('d M Y'),
                'status'  => $lembur->status,
            ],
        ], 201);
    }

    /**
     * GET /api/lembur?status=pending
     * Riwayat pengajuan lembur milik karyawan yang login
     */
    public function riwayat(Request $request): JsonResponse
    {
        $query = $request->user()->lemburs()->latest('tanggal');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $lembur = $query->get()->map(fn ($l) => [
            'id'               => $l->id,
            'tanggal'          => $l->tanggal->format('d M Y'),
            'jam_mulai'        => $l->jam_mulai,
            'jam_selesai'      => $l->jam_selesai,
            'alasan'           => $l->alasan,
            'status'           => $l->status,
            'catatan_approval' => $l->catatan_approval,
            'diajukan_at'      => $l->created_at->format('d M Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => $lembur->count(),
                'records' => $lembur,
            ],
        ]);
    }

    /**
     * DELETE /api/lembur/{id}
     * Batalkan pengajuan lembur — hanya boleh kalau masih pending
     */
    public function batalkan(Request $request, string $id): JsonResponse
    {
        $lembur = $request->user()->lemburs()->where('id', $id)->first();

        if (! $lembur) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan lembur tidak ditemukan.',
            ], 404);
        }

        if (! $lembur->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan lembur yang sudah diproses tidak bisa dibatalkan.',
            ], 422);
        }

        $lembur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur dibatalkan.',
        ]);
    }
}
