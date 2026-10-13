<?php

namespace App\Filament\Pages;

use App\Exports\LaporanBulananExport;
use App\Models\Instansi;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class RekapBulananPresensi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|UnitEnum|null $navigationGroup = 'Presensi';
    protected static ?string $title = 'Rekap Bulanan';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.rekap-bulanan-presensi';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'bulan' => now()->month,
            'tahun' => now()->year,
            'instansi_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bulan')
                    ->label('Bulan')
                    ->options([
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                    ])
                    ->native(false)
                    ->required()
                    ->live(),
                Select::make('tahun')
                    ->label('Tahun')
                    ->options(collect(range(now()->year, now()->year - 3))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->native(false)
                    ->required()
                    ->live(),
                Select::make('instansi_id')
                    ->label('Instansi')
                    ->placeholder('Semua Instansi')
                    ->options(fn () => Instansi::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->native(false)
                    ->live(),
            ])
            ->statePath('data');
    }

    public function getRows(): Collection
    {
        $state = $this->form->getState();

        if (! ($state['bulan'] ?? null) || ! ($state['tahun'] ?? null)) {
            return collect();
        }

        return LaporanBulananExport::agregasi(
            (int) $state['bulan'],
            (int) $state['tahun'],
            $state['instansi_id'] ?? null,
        );
    }

    public function exportExcel()
    {
        $state = $this->form->getState();

        return Excel::download(
            new LaporanBulananExport((int) $state['bulan'], (int) $state['tahun'], $state['instansi_id'] ?? null),
            "rekap-bulanan-{$state['tahun']}-{$state['bulan']}.xlsx"
        );
    }

    public function exportPdf()
    {
        $state = $this->form->getState();
        $instansi = ($state['instansi_id'] ?? null) ? Instansi::find($state['instansi_id']) : null;

        $pdf = Pdf::loadView('pdf.rekap-bulanan', [
            'rows' => $this->getRows(),
            'bulan' => (int) $state['bulan'],
            'tahun' => (int) $state['tahun'],
            'instansi' => $instansi,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "rekap-bulanan-{$state['tahun']}-{$state['bulan']}.pdf"
        );
    }
}
