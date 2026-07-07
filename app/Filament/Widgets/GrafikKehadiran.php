<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use Filament\Widgets\ChartWidget;

class GrafikKehadiran extends ChartWidget
{
    protected ?string $heading = 'Kehadiran 30 Hari Terakhir';
    protected ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $hari       = collect();
        $tepatWaktu = collect();
        $terlambat  = collect();
        $alpha      = collect();

        for ($i = 29; $i >= 0; $i--) {
            $tanggal = today()->subDays($i);
            $absensi = Absensi::whereDate('tanggal', $tanggal)->get();

            $hari->push($tanggal->format('d/m'));
            $tepatWaktu->push($absensi->where('status', 'tepat_waktu')->count());
            $terlambat->push($absensi->where('status', 'terlambat')->count());
            $alpha->push($absensi->where('status', 'alpha')->count());
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Tepat Waktu',
                    'data'            => $tepatWaktu->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor'     => 'rgb(16, 185, 129)',
                    'borderWidth'     => 2,
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
                [
                    'label'           => 'Terlambat',
                    'data'            => $terlambat->toArray(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'borderColor'     => 'rgb(245, 158, 11)',
                    'borderWidth'     => 2,
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
                [
                    'label'           => 'Alpha',
                    'data'            => $alpha->toArray(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor'     => 'rgb(239, 68, 68)',
                    'borderWidth'     => 2,
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $hari->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
