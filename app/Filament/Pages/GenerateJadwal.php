<?php

namespace App\Filament\Pages;

use App\Models\PolaRotasi;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class GenerateJadwal extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|UnitEnum|null $navigationGroup = 'Presensi';
    protected static ?string $title = 'Generate Jadwal';
    protected string $view = 'filament.pages.generate-jadwal';

    public ?array $dataBulanan = [];
    public ?array $dataRotasi = [];

    public function mount(): void
    {
        $bulanDepan = now()->addMonthNoOverflow();

        $this->formBulanan->fill([
            'bulan' => $bulanDepan->month,
            'tahun' => $bulanDepan->year,
        ]);

        $this->formRotasi->fill([
            'bulan' => $bulanDepan->month,
            'tahun' => $bulanDepan->year,
            'unit' => null,
        ]);
    }

    protected function getForms(): array
    {
        return ['formBulanan', 'formRotasi'];
    }

    public function formBulanan(Schema $schema): Schema
    {
        return $schema
            ->components(self::komponenBulanTahun())
            ->statePath('dataBulanan');
    }

    public function formRotasi(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::komponenBulanTahun(),
                Select::make('unit')
                    ->label('Unit Kerja')
                    ->placeholder('Semua unit')
                    ->options(fn () => PolaRotasi::query()
                        ->distinct()
                        ->orderBy('unit_kerja')
                        ->pluck('unit_kerja', 'unit_kerja')),
            ])
            ->statePath('dataRotasi');
    }

    protected static function komponenBulanTahun(): array
    {
        return [
            Select::make('bulan')
                ->label('Bulan')
                ->options(collect(range(1, 12))
                    ->mapWithKeys(fn (int $b) => [$b => Carbon::create(2000, $b, 1)->translatedFormat('F')]))
                ->native(false)
                ->required(),
            TextInput::make('tahun')
                ->label('Tahun')
                ->numeric()
                ->minValue(2024)
                ->maxValue(2100)
                ->required(),
        ];
    }

    public function generateBulananAction(): Action
    {
        return Action::make('generateBulanan')
            ->label('Buat Jadwal Karyawan Umum')
            ->color('primary')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->requiresConfirmation()
            ->modalHeading('Buat jadwal karyawan umum?')
            ->modalDescription('Jadwal yang sudah ada (manual maupun hasil pembuatan sebelumnya) di bulan ini tidak akan diubah — hanya tanggal yang belum punya jadwal yang akan diisi.')
            ->action(function () {
                $state = $this->formBulanan->getState();

                Artisan::call('jadwal:generate-bulanan', [
                    '--bulan' => $state['bulan'],
                    '--tahun' => $state['tahun'],
                ]);

                $this->notifikasiHasil(
                    Carbon::create($state['tahun'], $state['bulan'], 1),
                    'karyawan umum',
                    Artisan::output()
                );
            });
    }

    public function generateRotasiAction(): Action
    {
        return Action::make('generateRotasi')
            ->label('Buat Jadwal Rotasi')
            ->color('primary')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->requiresConfirmation()
            ->modalHeading('Buat jadwal rotasi?')
            ->modalDescription('Jadwal yang sudah ada (manual maupun hasil pembuatan sebelumnya) di bulan ini tidak akan diubah — hanya tanggal yang belum punya jadwal yang akan diisi.')
            ->action(function () {
                $state = $this->formRotasi->getState();

                $params = [
                    'bulan' => $state['bulan'],
                    'tahun' => $state['tahun'],
                ];
                if (! empty($state['unit'])) {
                    $params['--unit'] = $state['unit'];
                }

                Artisan::call('jadwal:generate-rotasi', $params);

                $unitLabel = $state['unit'] ? " untuk unit {$state['unit']}" : '';
                $this->notifikasiHasil(
                    Carbon::create($state['tahun'], $state['bulan'], 1),
                    "rotasi{$unitLabel}",
                    Artisan::output()
                );
            });
    }

    private function notifikasiHasil(Carbon $bulan, string $label, string $output): void
    {
        $namaBulan = $bulan->translatedFormat('F Y');

        // Kedua command nulis hasilnya lewat $this->info()/$this->warn() dalam
        // bahasa hasil ("Jadwal dibuat/diupdate: X", "Dilewati: Y", atau
        // peringatan kalau tidak ada assignment) — tidak menyebut nama
        // command sama sekali, aman ditampilkan langsung ke admin.
        Notification::make()
            ->title("Jadwal {$label} untuk {$namaBulan} selesai diproses")
            ->body(trim($output) ?: 'Tidak ada perubahan.')
            ->success()
            ->send();
    }
}
