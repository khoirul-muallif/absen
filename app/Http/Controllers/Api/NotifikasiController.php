<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * GET /api/notifikasi
     * List semua notifikasi karyawan (belum dibaca + sudah dibaca)
     */
    public function index(Request $request): JsonResponse
    {
        $karyawan      = $request->user();
        $perPage       = $request->query('per_page', 20);
        $hanyaBelumBaca = $request->boolean('belum_baca', false);

        $query = $karyawan->notifications();

        if ($hanyaBelumBaca) {
            $query = $karyawan->unreadNotifications();
        }

        $notifikasi = $query->latest()
            ->paginate($perPage)
            ->through(fn ($n) => [
                'id'         => $n->id,
                'judul'      => $n->data['judul'] ?? '-',
                'pesan'      => $n->data['pesan'] ?? '-',
                'tipe'       => $n->data['tipe'] ?? 'info',
                'sudah_baca' => $n->read_at !== null,
                'dibaca_at'  => $n->read_at?->diffForHumans(),
                'dibuat_at'  => $n->created_at->format('d M Y H:i'),
                'data'       => $n->data,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'total_belum_baca' => $karyawan->unreadNotifications()->count(),
                'notifikasi'       => $notifikasi,
            ],
        ]);
    }

    /**
     * POST /api/notifikasi/{id}/baca
     * Tandai satu notifikasi sebagai sudah dibaca
     */
    public function baca(Request $request, string $id): JsonResponse
    {
        $notifikasi = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (! $notifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $notifikasi->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
        ]);
    }

    /**
     * POST /api/notifikasi/baca-semua
     * Tandai semua notifikasi sebagai sudah dibaca
     */
    public function bacaSemua(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
        ]);
    }

    /**
     * DELETE /api/notifikasi/{id}
     * Hapus satu notifikasi
     */
    public function hapus(Request $request, string $id): JsonResponse
    {
        $notifikasi = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (! $notifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $notifikasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi dihapus.',
        ]);
    }

    /**
     * GET /api/notifikasi/jumlah
     * Hanya return jumlah notifikasi belum dibaca (untuk badge di UI)
     */
    public function jumlah(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'belum_baca' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }
}
