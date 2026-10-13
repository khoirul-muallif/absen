<?php

namespace App\Filament\Pages;

use App\Exports\LaporanHarianExport;
use App\Models\Absensi;
use App\Models\Shift;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class LaporanPresensi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Presensi';
    protected static ?string $title = 'Laporan Presensi';
    protected string $view = 'filament.pages.laporan-presensi';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal' => today()->toDateString(),
            'shift_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->native(false)
                    ->required()
                    ->live(),
                Select::make('shift_id')
                    ->label('Shift')
                    ->placeholder('Semua Shift')
                    ->options(fn () => Shift::query()
                        ->orderBy('nama_shift')
                        ->get()
                        ->mapWithKeys(fn (Shift $s) => [$s->id => $s->labelLengkap()]))
                    ->native(false)
                    ->live(),
            ])
            ->statePath('data');
    }

    /**
     * Dipanggil langsung dari Blade buat preview di layar -- query yang sama
     * persis dipakai Export, supaya preview dan file yang di-download tidak
     * pernah berbeda.
     */
    public function getRows(): Collection
    {
        $state = $this->form->getState();

        if (! $state['tanggal'] ?? null) {
            return collect();
        }

        $query = Absensi::whereDate('tanggal', $state['tanggal'])
            ->with(['karyawan', 'shift']);

        if ($state['shift_id'] ?? null) {
            $query->where('shift_id', $state['shift_id']);
        }

        return $query->get()->sortBy(fn (Absensi $a) => $a->karyawan->nama)->values();
    }

    public function exportExcel()
    {
        $state = $this->form->getState();
        $tanggal = Carbon::parse($state['tanggal']);

        return Excel::download(
            new LaporanHarianExport($tanggal, $state['shift_id'] ?? null),
            'laporan-harian-'.$tanggal->format('Y-m-d').'.xlsx'
        );
    }
}
