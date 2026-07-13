<?php

namespace App\Filament\Resources\Absensis\Schemas;

use App\Models\Shift;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AbsensiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Absensi')
                    ->description('Data karyawan, shift, dan QR yang digunakan')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship('karyawan', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        Select::make('shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'nama_shift')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Select::make('qr_instansi_id')
                            ->label('QR Instansi')
                            ->relationship('qrInstansi', 'kode_qr')
                            ->required()
                            ->searchable()
                            ->preload(),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->required()
                            ->displayFormat('d M Y')
                            ->default(today()),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'tepat_waktu' => 'Tepat Waktu',
                                'terlambat'   => 'Terlambat',
                                'alpha'       => 'Alpha',
                                'izin'        => 'Izin',
                                'sakit'       => 'Sakit',
                                'cuti'        => 'Cuti',
                                'dinas'       => 'Dinas',
                                'libur'       => 'Libur',
                            ])
                            ->default('alpha')
                            ->required()
                            ->helperText('Otomatis dihitung ulang jika Waktu Masuk & Shift diisi. Pilih manual hanya untuk status non-kehadiran (izin/sakit/cuti/dinas/libur/alpha).'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(2)
                            ->placeholder('Catatan tambahan jika ada...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Data Masuk')
                    ->description('Waktu dan lokasi saat absen masuk')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('waktu_masuk')
                            ->label('Waktu Masuk')
                            ->displayFormat('d M Y H:i')
                            ->seconds(false)
                            ->live(),

                        Placeholder::make('preview_keterlambatan')
                            ->label('Prediksi')
                            ->content(function (Get $get): string {
                                $shiftId = $get('shift_id');
                                $waktuMasuk = $get('waktu_masuk');
                                $karyawanId = $get('karyawan_id');

                                if (! $shiftId || ! $waktuMasuk) {
                                    return 'Isi Shift & Waktu Masuk untuk melihat prediksi status.';
                                }

                                $shift = Shift::find($shiftId);
                                if (! $shift) {
                                    return '-';
                                }

                                $waktu = \Carbon\Carbon::parse($waktuMasuk);
                                $menitTerlambatHariIni = $shift->hitungMenitTerlambat($waktu);
                                $status = $shift->tentukanStatus($waktu);

                                $teks = "Telat hari ini: {$menitTerlambatHariIni} menit. Status karyawan: " . strtoupper($status) . ".";

                                if ($shift->mode_toleransi === 'akumulasi_bulanan' && $karyawanId) {
                                    $totalSebelumnya = \App\Models\Absensi::where('karyawan_id', $karyawanId)
                                        ->whereYear('tanggal', $waktu->year)
                                        ->whereMonth('tanggal', $waktu->month)
                                        ->sum('menit_terlambat');
                                    $totalSetelah = $totalSebelumnya + $menitTerlambatHariIni;
                                    $melebihi = $shift->sudahMelebihiToleransiBulanan($totalSetelah);

                                    $teks .= " [ADMIN] Akumulasi bulan ini: {$totalSetelah}/{$shift->toleransi_menit} menit.";
                                    $teks .= $melebihi ? ' ⚠️ SUDAH MELEBIHI KUOTA — berpengaruh ke KPI.' : ' Masih dalam kuota.';
                                }

                                return $teks;
                            })
                            ->columnSpanFull(),

                        FileUpload::make('foto_masuk')
                            ->label('Foto Masuk')
                            ->image()
                            ->directory('foto-absen/masuk')
                            ->maxSize(2048)
                            ->helperText('Snapshot wajah saat absen masuk'),

                        TextInput::make('latitude_masuk')
                            ->label('Latitude Masuk')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('-7.0333'),

                        TextInput::make('longitude_masuk')
                            ->label('Longitude Masuk')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('110.4167'),
                    ]),

                Section::make('Data Pulang')
                    ->description('Waktu dan lokasi saat absen pulang')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        DateTimePicker::make('waktu_pulang')
                            ->label('Waktu Pulang')
                            ->displayFormat('d M Y H:i')
                            ->seconds(false)
                            ->after('waktu_masuk'),

                        FileUpload::make('foto_pulang')
                            ->label('Foto Pulang')
                            ->image()
                            ->directory('foto-absen/pulang')
                            ->maxSize(2048)
                            ->helperText('Snapshot wajah saat absen pulang'),

                        TextInput::make('latitude_pulang')
                            ->label('Latitude Pulang')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('-7.0333'),

                        TextInput::make('longitude_pulang')
                            ->label('Longitude Pulang')
                            ->numeric()
                            ->step(0.0000001)
                            ->placeholder('110.4167'),
                    ]),
            ]);
    }
}
