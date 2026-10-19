<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\TukarJadwal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    /**
     * GET /api/jadwal/saya
     * Jadwal milik karyawan yang login dalam rentang tanggal — sumber
     * picker jadwal_id di form Tukar Jadwal (mode tukar maupun pindah).
     */
    public function saya(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $karyawan = $request->user();

        $jadwals = Jadwal::where('karyawan_id', $karyawan->id)
            ->whereBetween('tanggal', [$request->tanggal_mulai, $request->tanggal_selesai])
            ->with('shift')
            ->orderBy('tanggal')
            ->get();

        $jadwalIdSedangDiajukan = self::jadwalIdSedangDiajukan($jadwals->pluck('id'));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $jadwals->count(),
                'records' => $jadwals->map(
                    fn ($j) => self::format($j, $jadwalIdSedangDiajukan)
                ),
            ],
        ]);
    }

    /**
     * GET /api/jadwal/rekan?tanggal=YYYY-MM-DD
     * Jadwal rekan satu unit_kerja (dan satu instansi) pada tanggal
     * tertentu — sumber picker jadwal_tujuan_id di form Tukar Jadwal mode
     * tukar.
     *
     * Scope SENGAJA dibatasi ke unit_kerja + instansi yang sama (keputusan
     * RS, lihat todo.md) — bukan seluruh instansi, supaya satu karyawan
     * tidak bisa melihat jadwal semua karyawan lewat endpoint ini.
     */
    public function rekan(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        $karyawan = $request->user();

        $jadwals = Jadwal::whereHas('karyawan', function ($q) use ($karyawan) {
                $q->where('instansi_id', $karyawan->instansi_id)
                    ->where('unit_kerja', $karyawan->unit_kerja)
                    ->where('id', '!=', $karyawan->id)
                    ->where('is_active', true);
            })
            ->whereDate('tanggal', $request->tanggal)
            ->with(['shift', 'karyawan'])
            ->orderBy('karyawan_id')
            ->get();

        $jadwalIdSedangDiajukan = self::jadwalIdSedangDiajukan($jadwals->pluck('id'));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $jadwals->count(),
                'records' => $jadwals->map(
                    fn ($j) => self::format($j, $jadwalIdSedangDiajukan, sertakanKaryawan: true)
                ),
            ],
        ]);
    }

    /**
     * ID Jadwal yang sedang terikat pengajuan TukarJadwal aktif
     * (menunggu_rekan/menunggu_admin), baik sebagai jadwal_id maupun
     * jadwal_tujuan_id. Dihitung sekali per request (bukan per-record)
     * supaya tidak N+1 query ke tabel tukar_jadwals.
     */
    private static function jadwalIdSedangDiajukan($jadwalIds): array
    {
        return TukarJadwal::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
            ->where(fn ($q) => $q->whereIn('jadwal_id', $jadwalIds)
                ->orWhereIn('jadwal_tujuan_id', $jadwalIds))
            ->get(['jadwal_id', 'jadwal_tujuan_id'])
            ->flatMap(fn ($t) => [$t->jadwal_id, $t->jadwal_tujuan_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function format(Jadwal $j, array $jadwalIdSedangDiajukan, bool $sertakanKaryawan = false): array
    {
        $data = [
            'jadwal_id' => $j->id,
            'tanggal' => $j->tanggal->format('d M Y'),
            'jenis' => $j->jenis,
            'shift' => $j->shift?->nama_shift,
            'bisa_diajukan' => ! in_array($j->id, $jadwalIdSedangDiajukan, true),
        ];

        if ($sertakanKaryawan) {
            $data['karyawan_id'] = $j->karyawan_id;
            $data['karyawan_nama'] = $j->karyawan->nama;
        }

        return $data;
    }
}
