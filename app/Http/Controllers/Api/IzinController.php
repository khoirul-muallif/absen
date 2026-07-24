<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IzinController extends Controller
{
    /**
     * POST /api/izin
     * Ajukan izin baru
     */
    public function ajukan(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal'     => 'required|date',
            'jam_keluar'  => 'required|date_format:H:i',
            'jam_kembali' => 'nullable|date_format:H:i|after:jam_keluar',
            'keperluan'   => 'required|string|max:1000',
        ]);

        $karyawan = $request->user();

        $izin = Izin::create([
            'karyawan_id' => $karyawan->id,
            'tanggal'     => $request->tanggal,
            'jam_keluar'  => $request->jam_keluar,
            'jam_kembali' => $request->jam_kembali,
            'keperluan'   => $request->keperluan,
            'status'      => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin berhasil dikirim, menunggu persetujuan.',
            'data'    => [
                'id'       => $izin->id,
                'tanggal'  => $izin->tanggal->format('d M Y'),
                'status'   => $izin->status,
            ],
        ], 201);
    }

    /**
     * GET /api/izin?status=pending
     * Riwayat pengajuan izin milik karyawan yang login
     */
    public function riwayat(Request $request): JsonResponse
    {
        $query = $request->user()->izins()->latest('tanggal');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $izin = $query->get()->map(fn ($i) => [
            'id'               => $i->id,
            'tanggal'          => $i->tanggal->format('d M Y'),
            'jam_keluar'       => $i->jam_keluar,
            'jam_kembali'      => $i->jam_kembali,
            'keperluan'        => $i->keperluan,
            'status'           => $i->status,
            'catatan_approval' => $i->catatan_approval,
            'diajukan_at'      => $i->created_at->format('d M Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'  => $izin->count(),
                'records' => $izin,
            ],
        ]);
    }

    /**
     * DELETE /api/izin/{id}
     * Batalkan pengajuan izin — hanya boleh kalau masih pending
     */
    public function batalkan(Request $request, string $id): JsonResponse
    {
        $izin = $request->user()->izins()->where('id', $id)->first();

        if (! $izin) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan izin tidak ditemukan.',
            ], 404);
        }

        if (! $izin->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan izin yang sudah diproses tidak bisa dibatalkan.',
            ], 422);
        }

        $izin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin dibatalkan.',
        ]);
    }
}
