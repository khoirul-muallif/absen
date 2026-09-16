<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Models\Shift;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Shift')
                    ->description('Jadwal jam kerja untuk shift ini')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('nama_shift')
                            ->label('Nama Shift')
                            ->placeholder('Pagi, Siang, Malam...')
                            ->required()
                            ->live(onBlur: true)
                            ->maxLength(255)
                            // Nama boleh sama — tabel `shift` tidak punya kolom
                            // unit_kerja, jadi "Pagi" IGD (07:00) dan "Pagi"
                            // Rawat Jalan (08:00) memang harus jadi dua baris.
                            // Yang ditandai di sini cuma duplikat PERSIS (nama
                            // DAN jam sama), yang hampir pasti salah input.
                            // Peringatan, bukan penolakan.
                            ->helperText(function (Get $get, ?Shift $record) {
                                $nama = $get('nama_shift');
                                $instansiId = $get('instansi_id');
                                $jamMasuk = $get('jam_masuk');
                                $jamPulang = $get('jam_pulang');

                                if (! $nama || ! $instansiId || ! $jamMasuk || ! $jamPulang) {
                                    return 'Boleh sama dengan shift lain kalau jamnya berbeda (mis. "Pagi" IGD 07:00 dan "Pagi" Rawat Jalan 08:00).';
                                }

                                $kembar = Shift::where('instansi_id', $instansiId)
                                    ->where('nama_shift', $nama)
                                    ->whereTime('jam_masuk', $jamMasuk)
                                    ->whereTime('jam_pulang', $jamPulang)
                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                    ->exists();

                                if ($kembar) {
                                    return '⚠️ Sudah ada shift dengan nama DAN jam yang sama persis di instansi ini. Kemungkinan besar duplikat — cek dulu sebelum menyimpan.';
                                }

                                return 'Boleh sama dengan shift lain kalau jamnya berbeda (mis. "Pagi" IGD 07:00 dan "Pagi" Rawat Jalan 08:00).';
                            }),

                        TextInput::make('toleransi_menit')
                            ->label('Toleransi Keterlambatan')
                            ->required()
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(120)
                            ->suffix('menit')
                            // Helper text lama berbunyi "Karyawan masih dianggap
                            // tepat waktu dalam batas ini" — itu SALAH.
                            // tentukanStatus() menandai 'terlambat' begitu lewat
                            // 0 menit dan tidak pernah melihat toleransi_menit
                            // sama sekali (dikunci oleh dataset di ShiftTest).
                            // Form-nya justru menyebarkan kesalahpahaman yang
                            // ingin dicegah.
                            ->helperText('TIDAK memengaruhi status harian — karyawan tetap tercatat "terlambat" begitu lewat 1 menit dari jam masuk. Angka ini hanya dipakai kalau Mode Toleransi di sebelah diatur ke Akumulasi Bulanan.'),

                        Select::make('mode_toleransi')
                            ->label('Mode Toleransi')
                            ->options([
                                'harian' => 'Per Hari (toleransi diabaikan)',
                                'akumulasi_bulanan' => 'Akumulasi Bulanan (toleransi dipakai)',
                            ])
                            ->default('harian')
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): string => $get('mode_toleransi') === 'akumulasi_bulanan'
                                ? 'Keterlambatan dijumlahkan sebulan. Begitu totalnya melewati angka Toleransi di sebelah, karyawan ditandai melanggar untuk keperluan KPI. Status harian tetap "terlambat" seperti biasa.'
                                : 'Toleransi tidak dipakai sama sekali. Status harian tetap "terlambat" begitu lewat jam masuk, dan tidak ada penanda pelanggaran bulanan.'),
                    ]),

                Section::make('Jam Kerja')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('jam_masuk')
                            ->label('Jam Masuk')
                            ->required()
                            ->live(onBlur: true)
                            ->seconds(false),

                        TimePicker::make('jam_pulang')
                            ->label('Jam Pulang')
                            ->required()
                            ->live(onBlur: true)
                            ->seconds(false)
                            // Shift malam (pulang di dini hari keesokan harinya)
                            // itu sah, jadi aturannya BUKAN "pulang harus setelah
                            // masuk". Yang tidak masuk akal cuma durasi nol —
                            // keputusan yang sama sudah diambil untuk Lembur di
                            // fase 25.
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $jamMasuk = $get('jam_masuk');

                                    if (! $value || ! $jamMasuk) {
                                        return;
                                    }

                                    if (substr((string) $value, 0, 5) === substr((string) $jamMasuk, 0, 5)) {
                                        $fail('Jam pulang tidak boleh sama dengan jam masuk (durasi shift jadi nol).');
                                    }
                                };
                            })
                            ->helperText('Boleh lebih awal dari jam masuk untuk shift malam (mis. 22:00 → 07:00).'),
                    ]),

                Section::make('Pola Hari Kerja')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        CheckboxList::make('hari_kerja')
                            ->label('Hari kerja')
                            ->options([
                                1 => 'Senin',
                                2 => 'Selasa',
                                3 => 'Rabu',
                                4 => 'Kamis',
                                5 => 'Jumat',
                                6 => 'Sabtu',
                                0 => 'Minggu',
                            ])
                            ->default([1, 2, 3, 4, 5])
                            ->columns(4)
                            ->helperText('Kosongkan semua jika shift berlaku tiap hari (misal shift jaga 24 jam). Dipakai generator jadwal bulanan untuk melewati hari libur mingguan, dan oleh rekap harian supaya hari non-kerja tidak dihitung alpha.'),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Shift Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika shift ini sudah tidak digunakan. Data absensi & jadwal yang sudah terlanjur memakai shift ini tidak ikut berubah.'),
                    ]),
            ]);
    }
}
