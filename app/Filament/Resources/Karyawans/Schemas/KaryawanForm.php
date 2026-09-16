<?php

namespace App\Filament\Resources\Karyawans\Schemas;

use App\Models\Karyawan;
use App\Models\PolaRotasi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class KaryawanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Karyawan')
                    ->description('Informasi identitas karyawan')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->placeholder('Khoirul Muallif, A.Md.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('nip')
                            ->label('NIP')
                            ->placeholder('NIP-00001')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('nomor_telepon')
                            ->label('Nomor Telepon')
                            ->placeholder('+62812xxxxxxxx')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            // Hash::make() dihapus — model Karyawan sudah punya
                            // cast 'password' => 'hashed'. Dua-duanya tidak bikin
                            // double hash (cast mengecek Hash::isHashed() dulu),
                            // tapi menyisakan dua tempat yang seolah-olah
                            // bertanggung jawab atas hal yang sama.
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Kosongkan jika tidak ingin mengubah password'),
                    ]),

                Section::make('Jabatan & Unit')
                    ->description('Posisi dan unit kerja karyawan')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        TextInput::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->placeholder('IT, Farmasi, IGD...')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // Sejak fase 33, assignment pola rotasi DITOLAK kalau
                            // unit_kerja karyawan tidak sama persis dengan unit
                            // pola. Jadi kolom teks bebas ini jadi kunci keras —
                            // satu typo bikin karyawan rotasi tidak bisa
                            // di-assign pola apa pun. PolaRotasiForm sudah dapat
                            // datalist di fase 32; di sini menyusul.
                            ->datalist(fn () => collect()
                                ->merge(Karyawan::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
                                ->merge(PolaRotasi::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
                                ->unique()
                                ->sort()
                                ->values()
                                ->all())
                            // Karyawan rotasi tanpa unit_kerja tidak akan pernah
                            // cocok dengan pola mana pun, jadi tidak bisa
                            // dijadwalkan sama sekali.
                            ->required(fn (Get $get): bool => $get('tipe_jadwal') === Karyawan::TIPE_ROTASI)
                            ->helperText(fn (Get $get): string => $get('tipe_jadwal') === Karyawan::TIPE_ROTASI
                                ? 'Wajib untuk karyawan rotasi, dan harus sama persis dengan unit pada Pola Rotasi — pola cuma bisa di-assign kalau unitnya cocok.'
                                : 'Pilih dari daftar kalau unitnya sudah ada, supaya penulisannya seragam.'),

                        TextInput::make('jabatan')
                            ->label('Jabatan')
                            ->placeholder('Pelaksana, Kepala Unit...')
                            ->maxLength(255),

                        Select::make('status_pegawai')
                            ->label('Status Pegawai')
                            ->options([
                                'tetap'     => 'Tetap',
                                'kontrak'   => 'Kontrak',
                                'orientasi' => 'Orientasi',
                                'magang'    => 'Magang',
                            ])
                            ->default('orientasi')
                            ->required(),

                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'admin'    => 'Admin',
                                'karyawan' => 'Karyawan',
                            ])
                            ->default('karyawan')
                            ->required()
                            ->helperText('Saat ini murni label tampilan — belum mengatur akses/permission apa pun (termasuk approval, yang selalu lewat akun admin Filament terpisah). Disiapkan untuk kemungkinan kebutuhan hak akses berbeda di masa depan.'),

                        DatePicker::make('tanggal_bergabung')
                            ->label('Tanggal Bergabung')
                            ->displayFormat('d M Y')
                            ->maxDate(now()),
                    ]),

                Section::make('Tipe Penjadwalan')
                    ->description('Menentukan mekanisme jadwal kerja karyawan ini')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Placeholder::make('peringatan_assignment')
                            ->label('⚠️ Tipe jadwal terkunci')
                            ->visible(fn (?Karyawan $record): bool => $record !== null && $record->punyaAssignmentJadwal())
                            ->content(fn (?Karyawan $record): string => $record?->isUmum()
                                ? 'Karyawan ini masih punya penugasan shift periode. Lepas dulu penugasannya di menu "Shift Karyawan Umum" sebelum mengubah tipe jadwal — kalau tidak, penugasan itu jadi data anomali yang tidak dipakai siapa pun.'
                                : 'Karyawan ini masih punya assignment pola rotasi. Lepas dulu assignment-nya di menu "Shift Karyawan Rotasi" sebelum mengubah tipe jadwal.')
                            ->columnSpanFull(),

                        Select::make('tipe_jadwal')
                            ->label('Tipe Jadwal')
                            ->options([
                                Karyawan::TIPE_UMUM   => 'Umum (jadwal tetap via assignment shift periode)',
                                Karyawan::TIPE_ROTASI => 'Rotasi (jadwal harian dari pola siklus)',
                            ])
                            ->default(Karyawan::TIPE_UMUM)
                            ->required()
                            ->live()
                            // Mengubah tipe saat assignment masih ada meninggalkan
                            // baris yang menurut guard fase 18 seharusnya mustahil
                            // — karyawan rotasi yang punya KaryawanShift, atau
                            // sebaliknya. Anomali itu selama ini baru terdeteksi
                            // belakangan oleh command karyawan:cek-tipe-jadwal
                            // (fase 13); lebih masuk akal dicegah di sumbernya.
                            ->rule(function (?Karyawan $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if ($record === null || $value === $record->tipe_jadwal) {
                                        return;
                                    }

                                    if ($record->punyaAssignmentJadwal()) {
                                        $fail($record->isUmum()
                                            ? 'Karyawan ini masih punya penugasan shift periode. Lepas dulu di menu "Shift Karyawan Umum" sebelum mengubah tipe jadwal.'
                                            : 'Karyawan ini masih punya assignment pola rotasi. Lepas dulu di menu "Shift Karyawan Rotasi" sebelum mengubah tipe jadwal.');
                                    }
                                };
                            })
                            ->helperText(fn ($state) => $state === Karyawan::TIPE_ROTASI
                                ? 'Karyawan rotasi dijadwalkan dari pola siklus lewat menu "Shift Karyawan Rotasi" (grup Manajemen Rotasi) — tidak pakai penugasan shift periode. Kalau lupa di-assign, RekapHarian menandainya sebagai anomali "jadwal_hilang", bukan otomatis dianggap libur.'
                                : 'Karyawan umum dijadwalkan lewat penugasan shift periode di menu "Shift Karyawan Umum" (grup Manajemen Shift), bukan pola siklus.'),
                    ]),

                Section::make('Foto')
                    ->description('Foto profil dan foto wajah untuk face recognition')
                    ->icon('heroicon-o-camera')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('foto_profil')
                            ->label('Foto Profil')
                            ->image()
                            ->imageEditor()
                            ->directory('foto-profil')
                            ->maxSize(2048)
                            ->helperText('Maks. 2MB, format JPG/PNG'),

                        FileUpload::make('foto_wajah')
                            ->label('Foto Wajah (Referensi)')
                            ->image()
                            ->directory('foto-wajah')
                            ->maxSize(2048)
                            ->helperText('Dipakai untuk verifikasi face recognition saat absen'),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Karyawan Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika karyawan sudah tidak bekerja. Ini cara yang benar untuk karyawan yang berhenti — menghapus akun akan ikut melenyapkan seluruh riwayat absensi & pengajuannya.'),
                    ]),
            ]);
    }
}
