<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\KaryawanShift;
use App\Models\QrInstansi;
use App\Notifications\AbsenTerlambat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    /**
     * GET /api/absensi/status
     */
    public function status(Request $request): JsonResponse
    {
        $karyawan = $request->user();
        $absensi  = $karyawan->absensi()->whereDate('tanggal', today())->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'sudah_masuk'  => $absensi && $absensi->waktu_masuk !== null,
                'sudah_pulang' => $absensi && $absensi->waktu_pulang !== null,
                'absensi'      => $absensi ? [
                    'status'       => $absensi->status,
                    'waktu_masuk'  => $absensi->waktu_masuk?->format('H:i'),
                    'waktu_pulang' => $absensi->waktu_pulang?->format('H:i'),
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/absensi/masuk
     */
    public function masuk(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'   => 'required',
            'longitude'  => 'required',
            'kode_qr'    => 'required|string',
            'foto_masuk' => 'required|image|max:2048',
        ]);

        $karyawan = $request->user()->load('instansi');

        // 1. Cek sudah absen masuk hari ini
        if ($karyawan->absensi()->whereDate('tanggal', today())->whereNotNull('waktu_masuk')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absen masuk hari ini.',
            ], 422);
        }

        // 2. Validasi GPS
        $instansi = $karyawan->instansi;
        $lat      = (float) $request->input('latitude');
        $lng      = (float) $request->input('longitude');

        if (! $instansi->dalamRadius($lat, $lng)) {
            $jarak = round($instansi->hitungJarak($lat, $lng));
            return response()->json([
                'success' => false,
                'message' => "Anda berada {$jarak}m dari lokasi instansi. Harus dalam radius {$instansi->radius_meter}m.",
                'data'    => ['jarak_meter' => $jarak],
            ], 422);
        }

        // 3. Validasi QR
        $qr = QrInstansi::where('kode_qr', $request->kode_qr)
            ->where('instansi_id', $instansi->id)
            ->first();

        if (! $qr || ! $qr->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        // 4. Ambil shift aktif
        $karyawanShift = KaryawanShift::with('shift')
            ->where('karyawan_id', $karyawan->id)
            ->where('tanggal_berlaku', '<=', today())
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', today()))
            ->latest('tanggal_berlaku')
            ->first();

        if (! $karyawanShift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada shift aktif untuk hari ini. Hubungi admin.',
            ], 422);
        }

        // 5. Simpan foto & tentukan status
        $fotoPath   = $request->file('foto_masuk')->store('foto-absen/masuk', 'public');
        $waktuMasuk = now();
        $status     = $karyawanShift->shift->tentukanStatus($waktuMasuk);

        // 6. Simpan absensi
        $absensi = Absensi::create([
            'karyawan_id'     => $karyawan->id,
            'shift_id'        => $karyawanShift->shift_id,
            'qr_instansi_id'  => $qr->id,
            'tanggal'         => today(),
            'waktu_masuk'     => $waktuMasuk,
            'latitude_masuk'  => $lat,
            'longitude_masuk' => $lng,
            'foto_masuk'      => $fotoPath,
            'status'          => $status,
        ]);

        // 7. Kirim notifikasi jika terlambat
        $menitTerlambat = $absensi->menitTerlambat();
        if ($status === 'terlambat' && $menitTerlambat > 0) {
            $karyawan->notify(new AbsenTerlambat($absensi->load('shift'), $menitTerlambat));
        }

        return response()->json([
            'success' => true,
            'message' => 'Absen masuk berhasil.',
            'data'    => [
                'waktu_masuk'    => $waktuMasuk->format('H:i'),
                'status'         => $status,
                'shift'          => $karyawanShift->shift->nama_shift,
                'terlambat'      => $menitTerlambat > 0 ? $menitTerlambat . ' menit' : null,
                'ada_notifikasi' => $status === 'terlambat',
            ],
        ]);
    }

    /**
     * POST /api/absensi/pulang
     */
    public function pulang(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'    => 'required',
            'longitude'   => 'required',
            'foto_pulang' => 'required|image|max:2048',
        ]);

        $karyawan = $request->user()->load('instansi');

        $absensi = $karyawan->absensi()
            ->whereDate('tanggal', today())
            ->whereNotNull('waktu_masuk')
            ->first();

        if (! $absensi) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum melakukan absen masuk hari ini.',
            ], 422);
        }

        if ($absensi->waktu_pulang !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absen pulang hari ini.',
            ], 422);
        }

        $instansi = $karyawan->instansi;
        $lat      = (float) $request->input('latitude');
        $lng      = (float) $request->input('longitude');

        if (! $instansi->dalamRadius($lat, $lng)) {
            $jarak = round($instansi->hitungJarak($lat, $lng));
            return response()->json([
                'success' => false,
                'message' => "Anda berada {$jarak}m dari lokasi instansi.",
                'data'    => ['jarak_meter' => $jarak],
            ], 422);
        }

        $fotoPath    = $request->file('foto_pulang')->store('foto-absen/pulang', 'public');
        $waktuPulang = now();

        $absensi->update([
            'waktu_pulang'     => $waktuPulang,
            'latitude_pulang'  => $lat,
            'longitude_pulang' => $lng,
            'foto_pulang'      => $fotoPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absen pulang berhasil.',
            'data'    => [
                'waktu_masuk'  => $absensi->waktu_masuk->format('H:i'),
                'waktu_pulang' => $waktuPulang->format('H:i'),
                'durasi'       => $absensi->fresh()->durasiMenit() . ' menit',
                'status'       => $absensi->status,
            ],
        ]);
    }

    /**
     * GET /api/absensi/riwayat?bulan=7&tahun=2026
     */
    public function riwayat(Request $request): JsonResponse
    {
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $absensi = $request->user()
            ->absensi()
            ->with('shift')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderBy('tanggal', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'tanggal'      => $a->tanggal->format('d M Y'),
                'hari'         => $a->tanggal->locale('id')->isoFormat('dddd'),
                'shift'        => $a->shift->nama_shift,
                'jam_masuk'    => $a->shift->jam_masuk,
                'waktu_masuk'  => $a->waktu_masuk?->format('H:i') ?? '-',
                'waktu_pulang' => $a->waktu_pulang?->format('H:i') ?? '-',
                'durasi'       => $a->durasiMenit() ? $a->durasiMenit() . ' menit' : '-',
                'terlambat'    => $a->menitTerlambat() > 0 ? $a->menitTerlambat() . ' menit' : null,
                'status'       => $a->status,
                'keterangan'   => $a->keterangan,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'   => $bulan,
                'tahun'   => $tahun,
                'total'   => $absensi->count(),
                'records' => $absensi,
            ],
        ]);
    }

    /**
     * GET /api/absensi/rekap
     */
    public function rekap(Request $request): JsonResponse
    {
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $absensi = $request->user()
            ->absensi()
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->get();

        $totalHadir     = $absensi->whereIn('status', ['tepat_waktu', 'terlambat'])->count();
        $totalTepatWaktu = $absensi->where('status', 'tepat_waktu')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'                  => \Carbon\Carbon::create($tahun, $bulan)->locale('id')->isoFormat('MMMM YYYY'),
                'tepat_waktu'            => $totalTepatWaktu,
                'terlambat'              => $absensi->where('status', 'terlambat')->count(),
                'alpha'                  => $absensi->where('status', 'alpha')->count(),
                'izin'                   => $absensi->where('status', 'izin')->count(),
                'sakit'                  => $absensi->where('status', 'sakit')->count(),
                'cuti'                   => $absensi->where('status', 'cuti')->count(),
                'dinas'                  => $absensi->where('status', 'dinas')->count(),
                'libur'                  => $absensi->where('status', 'libur')->count(),
                'total_hadir'            => $totalHadir,
                'persentase_tepat_waktu' => $totalHadir > 0
                    ? round($totalTepatWaktu / $totalHadir * 100)
                    : 0,
            ],
        ]);
    }

    /**
     * GET /api/instansi/qr/{kode}
     */
    public function validasiQr(Request $request, string $kode): JsonResponse
    {
        $karyawan = $request->user()->load('instansi');

        $qr = QrInstansi::where('kode_qr', $kode)
            ->where('instansi_id', $karyawan->instansi_id)
            ->first();

        if (! $qr) {
            return response()->json([
                'success' => false,
                'message' => 'QR code tidak dikenali atau bukan milik instansi Anda.',
            ], 404);
        }

        if (! $qr->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code sudah tidak aktif atau kadaluarsa.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code valid.',
            'data'    => [
                'instansi' => $karyawan->instansi->nama,
                'kode_qr'  => $qr->kode_qr,
            ],
        ]);
    }
}
