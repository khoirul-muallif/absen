<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use App\Models\Karyawan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $hariIni     = today();
        $bulanIni    = now()->month;
        $tahunIni    = now()->year;

        // Karyawan aktif
        $totalKaryawan = Karyawan::where('is_active', true)->count();

        // Absensi hari ini
        $absensiHariIni = Absensi::whereDate('tanggal', $hariIni)->get();
        $sudahAbsen     = $absensiHariIni->whereNotNull('waktu_masuk')->count();
        $tepatWaktu     = $absensiHariIni->where('status', 'tepat_waktu')->count();
        $terlambat      = $absensiHariIni->where('status', 'terlambat')->count();
        $belumAbsen     = $totalKaryawan - $sudahAbsen;

        // Absensi bulan ini
        $absenBulanIni  = Absensi::whereYear('tanggal', $tahunIni)
            ->whereMonth('tanggal', $bulanIni)
            ->get();
        $totalTerlambatBulan = $absenBulanIni->where('status', 'terlambat')->count();
        $totalAlphaBulan     = $absenBulanIni->where('status', 'alpha')->count();

        // Persentase kehadiran hari ini
        $persenHadir = $totalKaryawan > 0
            ? round($sudahAbsen / $totalKaryawan * 100)
            : 0;

        return [
            Stat::make('Karyawan Hadir Hari Ini', "{$sudahAbsen} / {$totalKaryawan}")
                ->description("{$persenHadir}% dari total karyawan aktif")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($persenHadir >= 80 ? 'success' : ($persenHadir >= 50 ? 'warning' : 'danger'))
                ->chart([
                    $sudahAbsen,
                    $tepatWaktu,
                    $terlambat,
                    $belumAbsen,
                ]),

            Stat::make('Tepat Waktu Hari Ini', $tepatWaktu)
                ->description("Terlambat: {$terlambat} orang")
                ->descriptionIcon('heroicon-m-clock')
                ->color($terlambat === 0 ? 'success' : 'warning'),

            Stat::make('Belum Absen', $belumAbsen)
                ->description('Karyawan belum melakukan absen masuk')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($belumAbsen === 0 ? 'success' : 'danger'),

            Stat::make('Terlambat Bulan Ini', $totalTerlambatBulan)
                ->description("Alpha bulan ini: {$totalAlphaBulan} kali")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($totalTerlambatBulan === 0 ? 'success' : 'warning'),
        ];
    }
}
