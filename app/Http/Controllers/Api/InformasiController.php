<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Informasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformasiController extends Controller
{
    private const PER_HALAMAN = 10;

    /**
     * GET /api/informasi [?q=kata+kunci] [?page=2]
     */
    public function index(Request $request): JsonResponse
    {
        $karyawan = $request->user();
        $q = trim((string) $request->query('q', ''));

        $informasi = Informasi::query()
            ->where('instansi_id', $karyawan->instansi_id)
            ->aktif()
            // Pencarian di JUDUL saja (keputusan 9 Nov 2026) — mencari di isi
            // bikin hasilnya penuh pengumuman yang kata kuncinya cuma lewat di
            // tengah paragraf, tanpa petunjuk kenapa baris itu muncul.
            ->when($q !== '', fn ($query) => $query->where('judul', 'like', "%{$q}%"))
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('id')
            ->paginate(self::PER_HALAMAN);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $informasi->total(),
                'halaman_sekarang' => $informasi->currentPage(),
                'total_halaman' => $informasi->lastPage(),
                'records' => collect($informasi->items())->map(fn ($i) => [
                    'id' => $i->id,
                    'judul' => $i->judul,
                    'ringkasan' => $i->ringkasan,
                    'tanggal_terbit' => $i->tanggal_terbit->format('d M Y'),
                ]),
            ],
        ]);
    }

    /**
     * GET /api/informasi/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $karyawan = $request->user();

        $informasi = Informasi::where('instansi_id', $karyawan->instansi_id)
            ->aktif()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $informasi->id,
                'judul' => $informasi->judul,
                'isi' => $informasi->isi,
                'tanggal_terbit' => $informasi->tanggal_terbit->format('d M Y'),
            ],
        ]);
    }
}
