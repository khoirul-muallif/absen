<?php

namespace App\Filament\Resources\Karyawans\Schemas;

use App\Models\Karyawan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

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
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
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
                            ->preload(),

                        TextInput::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->placeholder('IT, Farmasi, IGD...')
                            ->maxLength(255),

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
                            ->required(),

                        DatePicker::make('tanggal_bergabung')
                            ->label('Tanggal Bergabung')
                            ->displayFormat('d M Y')
                            ->maxDate(now()),
                    ]),

                Section::make('Tipe Penjadwalan')
                    ->description('Menentukan mekanisme jadwal kerja karyawan ini')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Select::make('tipe_jadwal')
                            ->label('Tipe Jadwal')
                            ->options([
                                Karyawan::TIPE_UMUM   => 'Umum (jadwal tetap via assignment shift periode)',
                                Karyawan::TIPE_ROTASI => 'Rotasi (jadwal harian manual, ganti-ganti shift)',
                            ])
                            ->default(Karyawan::TIPE_UMUM)
                            ->required()
                            ->live()
                            ->helperText(fn ($state) => $state === Karyawan::TIPE_ROTASI
                                ? 'Karyawan rotasi WAJIB punya row Jadwal eksplisit tiap hari kerja di menu Jadwal — tidak pakai assignment KaryawanShift. Kalau lupa dibuatkan, RekapHarian akan menandai sebagai anomali, bukan otomatis dianggap libur.'
                                : 'Karyawan umum dijadwalkan lewat assignment Shift periode (menu Karyawan & Shift), bukan Jadwal harian manual.'),
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
                            ->helperText('Nonaktifkan jika karyawan sudah tidak bekerja'),
                    ]),
            ]);
    }
}
