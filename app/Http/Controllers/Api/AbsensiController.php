<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\KaryawanShift;
use App\Models\QrInstansi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
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

    public function masuk(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'   => 'required',
            'longitude'  => 'required',
            'kode_qr'    => 'required|string',
            'foto_masuk' => 'required|image|max:2048',
        ]);

        $karyawan = $request->user()->load('instansi');

        if ($karyawan->absensi()->whereDate('tanggal', today())->whereNotNull('waktu_masuk')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absen masuk hari ini.',
            ], 422);
        }

        $instansi = $karyawan->instansi;
        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');

        if (! $instansi->dalamRadius($lat, $lng)) {
            $jarak = round($instansi->hitungJarak($lat, $lng));
            return response()->json([
                'success' => false,
                'message' => "Anda berada {$jarak}m dari lokasi instansi. Harus dalam radius {$instansi->radius_meter}m.",
                'data'    => ['jarak_meter' => $jarak],
            ], 422);
        }

        $qr = QrInstansi::where('kode_qr', $request->kode_qr)
            ->where('instansi_id', $instansi->id)
            ->first();

        if (! $qr || ! $qr->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

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

        $fotoPath   = $request->file('foto_masuk')->store('foto-absen/masuk', 'public');
        $waktuMasuk = now();
        $status     = $karyawanShift->shift->tentukanStatus($waktuMasuk);

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

        return response()->json([
            'success' => true,
            'message' => 'Absen masuk berhasil.',
            'data'    => [
                'waktu_masuk' => $waktuMasuk->format('H:i'),
                'status'      => $status,
                'shift'       => $karyawanShift->shift->nama_shift,
                'terlambat'   => $absensi->menitTerlambat() > 0
                    ? $absensi->menitTerlambat() . ' menit'
                    : null,
            ],
        ]);
    }

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
        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');

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

    public function riwayat(Request $request): JsonResponse
    {
        $bulan = $request->query('bulan', now()->month);
        $tahun = $request->query('tahun', now()->year);

        $absensi = $request->user()
            ->absensi()
            ->with('shift')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderBy('tanggal', 'desc')
            ->get()
            ->map(fn ($a) => [
                'tanggal'      => $a->tanggal->format('d M Y'),
                'hari'         => $a->tanggal->locale('id')->isoFormat('dddd'),
                'shift'        => $a->shift->nama_shift,
                'waktu_masuk'  => $a->waktu_masuk?->format('H:i') ?? '-',
                'waktu_pulang' => $a->waktu_pulang?->format('H:i') ?? '-',
                'status'       => $a->status,
                'keterangan'   => $a->keterangan,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'   => $bulan,
                'tahun'   => $tahun,
                'records' => $absensi,
            ],
        ]);
    }

    public function rekap(Request $request): JsonResponse
    {
        $absensi = $request->user()
            ->absensi()
            ->whereYear('tanggal', now()->year)
            ->whereMonth('tanggal', now()->month)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'                  => now()->locale('id')->isoFormat('MMMM YYYY'),
                'tepat_waktu'            => $absensi->where('status', 'tepat_waktu')->count(),
                'terlambat'              => $absensi->where('status', 'terlambat')->count(),
                'alpha'                  => $absensi->where('status', 'alpha')->count(),
                'izin'                   => $absensi->where('status', 'izin')->count(),
                'sakit'                  => $absensi->where('status', 'sakit')->count(),
                'cuti'                   => $absensi->where('status', 'cuti')->count(),
                'total_hadir'            => $absensi->whereIn('status', ['tepat_waktu', 'terlambat'])->count(),
                'persentase_tepat_waktu' => $absensi->count() > 0
                    ? round($absensi->where('status', 'tepat_waktu')->count() / $absensi->count() * 100)
                    : 0,
            ],
        ]);
    }

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
