<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use Filament\Widgets\ChartWidget;

class RekapBulanIni extends ChartWidget
{
    protected ?string $heading = 'Rekap Status Bulan Ini';
    protected ?string $maxHeight = '280px';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $absensi = Absensi::whereYear('tanggal', now()->year)
            ->whereMonth('tanggal', now()->month)
            ->get();

        return [
            'datasets' => [
                [
                    'data' => [
                        $absensi->where('status', 'tepat_waktu')->count(),
                        $absensi->where('status', 'terlambat')->count(),
                        $absensi->where('status', 'alpha')->count(),
                        $absensi->where('status', 'izin')->count(),
                        $absensi->where('status', 'sakit')->count(),
                        $absensi->where('status', 'cuti')->count(),
                        $absensi->where('status', 'dinas')->count(),
                        $absensi->where('status', 'libur')->count(),
                    ],
                    'backgroundColor' => [
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(59, 130, 246)',
                        'rgb(168, 85, 247)',
                        'rgb(20, 184, 166)',
                        'rgb(249, 115, 22)',
                        'rgb(156, 163, 175)',
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => [
                'Tepat Waktu', 'Terlambat', 'Alpha',
                'Izin', 'Sakit', 'Cuti', 'Dinas', 'Libur',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
