<?php

namespace App\Filament\Resources\Absensis\Schemas;

use App\Models\Absensi;
use App\Models\Shift;
use Carbon\Carbon;
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
                            ->live()
                            ->columnSpanFull(),

                        Select::make('shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'nama_shift')
                            // Wajib hanya kalau ada waktu_masuk: status &
                            // menit_terlambat dihitung dari jam shift, jadi
                            // tanpa shift tidak ada dasar perhitungannya.
                            // Untuk baris non-kehadiran (izin/cuti/libur/alpha)
                            // shift boleh kosong — kolomnya nullable di DB.
                            ->required(fn (Get $get): bool => filled($get('waktu_masuk')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Wajib diisi kalau Waktu Masuk diisi.'),

                        Select::make('qr_instansi_id')
                            ->label('QR Instansi')
                            ->relationship('qrInstansi', 'kode_qr')
                            // TIDAK wajib. Kolomnya nullable di DB, dan baris
                            // hasil sinkronisasi Cuti/Dinas justru mengosongkannya.
                            // Memaksa admin memilih QR untuk hari libur/cuti cuma
                            // bikin data berbohong.
                            ->searchable()
                            ->preload()
                            ->helperText('Kosongkan untuk entri manual tanpa pemindaian QR.'),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->required()
                            ->live()
                            ->displayFormat('d M Y')
                            ->default(today())
                            ->helperText('Satu karyawan hanya boleh punya satu baris absensi per tanggal.')
                            // Unique (karyawan_id, tanggal) sudah ada di level DB,
                            // tapi sebelumnya tidak divalidasi di form — submit
                            // duplikat lolos dan baru gagal sebagai QueryException
                            // 1062 mentah. Pola yang sama seperti KuotaCuti fase 25.
                            ->rule(function (Get $get, ?Absensi $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId || ! $value) {
                                        return;
                                    }

                                    $sudahAda = Absensi::where('karyawan_id', $karyawanId)
                                        ->whereDate('tanggal', Carbon::parse($value))
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->exists();

                                    if ($sudahAda) {
                                        $fail('Karyawan ini sudah punya data absensi di tanggal tersebut. Edit baris yang sudah ada, jangan buat baru.');
                                    }
                                };
                            }),

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
                            ->helperText('Otomatis dihitung ulang jika Waktu Masuk & Shift diisi — baik saat membuat baru maupun saat mengedit. Pilih manual hanya untuk status non-kehadiran (izin/sakit/cuti/dinas/libur/alpha).'),

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
                            ->live()
                            ->helperText('Tanggalnya harus sama dengan field Tanggal di atas.')
                            // Sebelumnya tanggal & waktu_masuk sama sekali tidak
                            // terikat — admin bisa menyimpan tanggal 10 Sep
                            // dengan waktu_masuk 14 Sep. Itu kondisi yang sama
                            // dengan data cacat yang dibersihkan di fase 26
                            // lanjutan: rekap dikelompokkan per `tanggal`,
                            // sedangkan keterlambatan dihitung dari waktu_masuk.
                            //
                            // Sengaja TIDAK diterapkan ke waktu_pulang — shift
                            // malam pulang di dini hari keesokan harinya, jadi
                            // beda tanggal di sana memang wajar.
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $tanggal = $get('tanggal');

                                    if (! $value || ! $tanggal) {
                                        return;
                                    }

                                    if (Carbon::parse($value)->toDateString() !== Carbon::parse($tanggal)->toDateString()) {
                                        $fail('Tanggal pada Waktu Masuk harus sama dengan field Tanggal. Untuk absen yang melewati tengah malam, isi perbedaannya di Waktu Pulang.');
                                    }
                                };
                            }),

                        Placeholder::make('preview_keterlambatan')
                            ->label('Prediksi')
                            ->content(function (Get $get, ?Absensi $record): string {
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

                                $waktu = Carbon::parse($waktuMasuk);
                                $menitTerlambatHariIni = $shift->hitungMenitTerlambat($waktu);
                                $status = $shift->tentukanStatus($waktu);

                                $teks = "Telat hari ini: {$menitTerlambatHariIni} menit. Status karyawan: ".strtoupper($status).'.';

                                if ($shift->mode_toleransi === 'akumulasi_bulanan' && $karyawanId) {
                                    $tanggal = $get('tanggal') ? Carbon::parse($get('tanggal')) : $waktu;

                                    // Record yang sedang diedit dikecualikan —
                                    // kalau tidak, menit_terlambat lamanya ikut
                                    // terjumlah bersama nilai barunya.
                                    $totalSebelumnya = (int) Absensi::where('karyawan_id', $karyawanId)
                                        ->whereYear('tanggal', $tanggal->year)
                                        ->whereMonth('tanggal', $tanggal->month)
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
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
                            ->after('waktu_masuk')
                            ->helperText('Boleh jatuh di tanggal berikutnya untuk shift malam.'),

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
