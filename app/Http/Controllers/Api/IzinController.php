<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            'tanggal'             => 'required|date|after_or_equal:today',
            'jam_keluar'          => 'required|date_format:H:i',
            // WAJIB sejak 24 Nov 2026. Ini perkiraan yang diisi sebelum
            // pergi, bukan catatan setelah kembali — jadi selalu bisa
            // diisi. Karyawan yang benar-benar tidak tahu kapan kembali
            // diarahkan menghubungi admin, yang bisa mengisi lewat
            // Filament tanpa batasan ini.
            'jam_kembali_rencana' => 'required|date_format:H:i|after:jam_keluar',
            'keperluan'           => 'required|string|max:1000',
        ]);

        $karyawan = $request->user();

        $izin = Izin::create([
            'karyawan_id'         => $karyawan->id,
            'tanggal'             => $request->tanggal,
            'jam_keluar'          => $request->jam_keluar,
            'jam_kembali_rencana' => $request->jam_kembali_rencana,
            'keperluan'           => $request->keperluan,
            'status'              => 'approved',
            'approved_by'         => null,
            'approved_at'         => now(),
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
     * Karyawan menandai dirinya sudah kembali. TANPA BODY.
     *
     * Waktunya dari now() di server, bukan dari client: kalau jamnya
     * dikirim client, jam kembali bisa dikarang dan seluruh gunanya
     * sebagai catatan hilang. Konsekuensinya karyawan yang lupa menekan
     * sampai besok tercatat kembali besok — terlihat salah, tapi jujur.
     *
     * Cuma bisa diisi SEKALI (jam_kembali_aktual harus masih null) —
     * supaya "siapa & kapan mengisi" jelas dari updated_at tanpa kolom
     * log terpisah. Koreksi harus lewat admin (Filament).
     *
     * TIDAK divalidasi harus setelah jam_keluar: waktunya dari server,
     * jadi satu-satunya cara lebih awal adalah jam server yang salah —
     * dan menolaknya berarti karyawan tidak bisa mencatat apa pun.
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

        if ($izin->jam_kembali_aktual !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah tercatat kembali. Hubungi admin kalau perlu dikoreksi.',
            ], 422);
        }

        $izin->update(['jam_kembali_aktual' => now()->format('H:i:s')]);

        return response()->json([
            'success' => true,
            'message' => 'Selamat datang kembali. Jam kembali tercatat.',
            'data'    => [
                'id'                  => $izin->id,
                'jam_kembali_aktual'  => $izin->jamKembaliAktualString(),
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
            'jam_keluar'       => $i->jamKeluarString(),
            'jam_kembali_rencana' => $i->jamKembaliRencanaString(),
            'jam_kembali_aktual'  => $i->jamKembaliAktualString(),
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

        if ($izin->jam_kembali_aktual !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Izin yang sudah tercatat kembali tidak bisa dibatalkan sendiri. Hubungi admin.',
            ], 422);
        }

        $izin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin dibatalkan.',
        ]);
    }
}
