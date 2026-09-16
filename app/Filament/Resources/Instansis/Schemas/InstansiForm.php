<?php

namespace App\Filament\Resources\Instansis\Schemas;

use App\Models\Instansi;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Instansi')
                    ->description('Data utama instansi / rumah sakit')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Instansi')
                            ->placeholder('RSU Banyumanik 2')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('kode_instansi')
                            ->label('Kode Instansi')
                            ->placeholder('RSUB2')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            // Helper text lama berbunyi "tidak bisa diubah
                            // setelah dipakai QR" — dua-duanya keliru. Tidak ada
                            // apa pun yang mencegah perubahan, DAN kode ini tidak
                            // dipakai pemindaian QR sama sekali (endpoint
                            // /api/instansi/qr/{kode} mencari QrInstansi.kode_qr,
                            // kolom terpisah). Yang benar: kode ini dikirim ke
                            // aplikasi mobile lewat /api/auth/me sebagai identitas.
                            ->disabled(fn (?Instansi $record): bool => $record !== null && ! $record->kodeMasihBisaDiubah())
                            ->dehydrated()
                            ->helperText(fn (?Instansi $record): string => $record !== null && ! $record->kodeMasihBisaDiubah()
                                ? 'Terkunci: instansi ini sudah punya karyawan atau QR. Kode dikirim ke aplikasi mobile sebagai identitas instansi, jadi mengubahnya berisiko bikin data yang tersimpan di sisi klien tidak lagi cocok.'
                                : 'Kode unik instansi, dikirim ke aplikasi mobile lewat endpoint profil. Akan terkunci otomatis begitu sudah ada karyawan atau QR.'),

                        TextInput::make('telepon')
                            ->label('Nomor Telepon')
                            ->placeholder('024-xxxxxxx')
                            ->tel()
                            ->maxLength(20),

                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->placeholder('Jl. ..., Semarang')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Lokasi & Validasi GPS')
                    ->description('Koordinat pusat instansi untuk validasi kehadiran karyawan')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->placeholder('-7.0333')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            // Tanpa batas nilai, latitude & longitude yang
                            // tertukar tersimpan tanpa keluhan — dan akibatnya
                            // SEMUA absen ditolak "di luar radius" tanpa petunjuk
                            // kenapa. Rentang lintang cuma -90..90, jadi nilai
                            // bujur (mis. 110) pasti salah tempat.
                            ->minValue(-90)
                            ->maxValue(90)
                            ->live(onBlur: true)
                            ->helperText('Antara -90 dan 90. Untuk Indonesia biasanya negatif (mis. -7.0333).'),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->placeholder('110.4167')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->minValue(-180)
                            ->maxValue(180)
                            ->live(onBlur: true)
                            ->helperText('Antara -180 dan 180. Untuk Indonesia biasanya 95–141 (mis. 110.4167).'),

                        TextInput::make('radius_meter')
                            ->label('Radius Validasi (meter)')
                            ->required()
                            ->numeric()
                            ->default(100)
                            ->minValue(10)
                            ->maxValue(5000)
                            ->suffix('m')
                            ->helperText('Karyawan harus berada dalam radius ini untuk absen'),

                        // Batas -90..90 saja tidak menangkap semua kekeliruan:
                        // lintang 11 dan bujur 7 dua-duanya "sah" tapi jatuh di
                        // Afrika. Peringatan lunak ini menangkap titik yang jauh
                        // di luar Indonesia tanpa menolak submit — bisa saja ada
                        // instansi di luar negeri suatu saat.
                        Placeholder::make('cek_koordinat')
                            ->label('Cek koordinat')
                            ->live()
                            ->content(function (Get $get): string {
                                $lat = $get('latitude');
                                $lng = $get('longitude');

                                if (! is_numeric($lat) || ! is_numeric($lng)) {
                                    return 'Isi latitude & longitude untuk dicek.';
                                }

                                $lat = (float) $lat;
                                $lng = (float) $lng;

                                $diIndonesia = $lat >= -11 && $lat <= 6 && $lng >= 95 && $lng <= 141;

                                if ($diIndonesia) {
                                    return "Titik ini berada di wilayah Indonesia ({$lat}, {$lng}). Cocokkan lagi dengan peta untuk memastikan posisinya tepat di gedung.";
                                }

                                $mungkinTertukar = $lng >= -11 && $lng <= 6 && $lat >= 95 && $lat <= 141;

                                return $mungkinTertukar
                                    ? "⚠️ Titik ini di luar Indonesia, dan nilainya cocok kalau latitude & longitude DITUKAR. Kemungkinan besar tertukar — latitude seharusnya {$lng}, longitude {$lat}."
                                    : "⚠️ Titik ({$lat}, {$lng}) berada di luar wilayah Indonesia. Pastikan ini memang benar sebelum menyimpan — koordinat yang keliru bikin semua absen ditolak \"di luar radius\".";
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Instansi Aktif')
                            ->helperText('Nonaktifkan jika instansi tidak lagi digunakan. Ini cara yang benar untuk instansi yang berhenti beroperasi — menghapusnya akan ikut melenyapkan seluruh karyawan, shift, QR, hari libur, pola rotasi, dan semua riwayat absensinya.')
                            ->default(true),
                    ]),
            ]);
    }
}
