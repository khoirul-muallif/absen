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
     * Ajukan izin baru.
     *
     * KEPUTUSAN RS (8 Okt 2026): izin keluar sementara itu darurat/
     * insidental, tidak realistis menunggu approval admin dulu. Sekarang
     * LANGSUNG approved saat diajukan, tidak lewat status pending.
     * approved_by sengaja NULL (bukan admin yang approve), approved_at =
     * waktu pengajuan.
     *
     * Admin tetap bisa menolak (reject) izin yang sudah auto-approved ini
     * secara retroaktif lewat Filament kalau ternyata tidak sah — itulah
     * peran approval yang masih tersisa di sini.
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
            'status'      => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin tercatat.',
            'data'    => [
                'id'      => $izin->id,
                'tanggal' => $izin->tanggal->format('d M Y'),
                'status'  => $izin->status,
            ],
        ], 201);
    }

    /**
     * PATCH /api/izin/{id}/jam-kembali
     * Karyawan mengisi jam_kembali sendiri setelah balik dari izin.
     *
     * Cuma bisa diisi SEKALI lewat endpoint ini (jam_kembali harus masih
     * null) — supaya "siapa & kapan mengisi" jelas dari updated_at tanpa
     * perlu kolom/tabel log terpisah. Koreksi setelah terisi harus lewat
     * admin (Filament).
     */
    public function isiJamKembali(Request $request, string $id): JsonResponse
    {
        $izin = $request->user()->izins()->where('id', $id)->first();

        if (! $izin) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan izin tidak ditemukan.',
            ], 404);
        }

        if ($izin->jam_kembali !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Jam kembali sudah terisi. Hubungi admin kalau perlu dikoreksi.',
            ], 422);
        }

        $request->validate([
            'jam_kembali' => 'required|date_format:H:i',
        ]);

        if ($request->jam_kembali <= $izin->jam_keluar) {
            return response()->json([
                'success' => false,
                'message' => 'Jam kembali harus setelah jam keluar.',
            ], 422);
        }

        $izin->update(['jam_kembali' => $request->jam_kembali]);

        return response()->json([
            'success' => true,
            'message' => 'Jam kembali berhasil dicatat.',
            'data'    => [
                'id'          => $izin->id,
                'jam_kembali' => $izin->jam_kembali,
            ],
        ]);
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
                'total'   => $izin->count(),
                'records' => $izin,
            ],
        ]);
    }

    /**
     * DELETE /api/izin/{id}
     * Batalkan pengajuan izin.
     *
     * Sejak auto-approve, izin TIDAK PERNAH berstatus pending dari jalur
     * API — guard lama (isPending()) akan membuat endpoint ini jadi tidak
     * pernah bisa dipakai. Diganti: boleh dibatalkan selama jam_kembali
     * belum terisi (izin masih "berjalan"; aman karena Izin sengaja tidak
     * sync ke Absensi/kuota — tidak ada efek samping yang perlu di-undo).
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

        if ($izin->jam_kembali !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Izin yang sudah ada jam kembalinya tidak bisa dibatalkan sendiri. Hubungi admin.',
            ], 422);
        }

        $izin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin dibatalkan.',
        ]);
    }
}
