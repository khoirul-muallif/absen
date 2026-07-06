<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $karyawan = Karyawan::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $karyawan || ! Hash::check($request->password, $karyawan->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $karyawan->tokens()->delete();
        $token = $karyawan->createToken('absensi-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'token'    => $token,
                'karyawan' => [
                    'id'             => $karyawan->id,
                    'nama'           => $karyawan->nama,
                    'nip'            => $karyawan->nip,
                    'email'          => $karyawan->email,
                    'jabatan'        => $karyawan->jabatan,
                    'unit_kerja'     => $karyawan->unit_kerja,
                    'status_pegawai' => $karyawan->status_pegawai,
                    'role'           => $karyawan->role,
                    'foto_profil'    => $karyawan->foto_profil
                        ? asset('storage/' . $karyawan->foto_profil)
                        : null,
                    'instansi' => [
                        'id'   => $karyawan->instansi->id,
                        'nama' => $karyawan->instansi->nama,
                    ],
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $karyawan = $request->user()->load('instansi');

        // Shift aktif hari ini via KaryawanShift model
        $shiftAktif = KaryawanShift::with('shift')
            ->where('karyawan_id', $karyawan->id)
            ->where('tanggal_berlaku', '<=', today())
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', today()))
            ->latest('tanggal_berlaku')
            ->first();

        // Absensi hari ini
        $absensiHariIni = $karyawan->absensi()
            ->whereDate('tanggal', today())
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $karyawan->id,
                'nama'             => $karyawan->nama,
                'nip'              => $karyawan->nip,
                'email'            => $karyawan->email,
                'jabatan'          => $karyawan->jabatan,
                'unit_kerja'       => $karyawan->unit_kerja,
                'status_pegawai'   => $karyawan->status_pegawai,
                'tanggal_bergabung'=> $karyawan->tanggal_bergabung?->format('d M Y'),
                'foto_profil'      => $karyawan->foto_profil
                    ? asset('storage/' . $karyawan->foto_profil)
                    : null,
                'instansi' => [
                    'id'            => $karyawan->instansi->id,
                    'nama'          => $karyawan->instansi->nama,
                    'kode_instansi' => $karyawan->instansi->kode_instansi,
                ],
                'shift_aktif' => $shiftAktif ? [
                    'nama_shift' => $shiftAktif->shift->nama_shift,
                    'jam_masuk'  => $shiftAktif->shift->jam_masuk,
                    'jam_pulang' => $shiftAktif->shift->jam_pulang,
                ] : null,
                'absensi_hari_ini' => $absensiHariIni ? [
                    'status'       => $absensiHariIni->status,
                    'waktu_masuk'  => $absensiHariIni->waktu_masuk?->format('H:i'),
                    'waktu_pulang' => $absensiHariIni->waktu_pulang?->format('H:i'),
                ] : null,
            ],
        ]);
    }
}
