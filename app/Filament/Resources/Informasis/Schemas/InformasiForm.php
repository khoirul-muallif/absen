<?php

namespace App\Filament\Resources\Informasis\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InformasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Pengumuman hanya terlihat oleh karyawan instansi ini.'),

                TextInput::make('judul')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Judul inilah yang dicari karyawan lewat kolom pencarian di aplikasi.'),

                Textarea::make('isi')
                    ->label('Isi pengumuman')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull()
                    ->helperText('Teks biasa. 150 karakter pertama tampil sebagai cuplikan di daftar pengumuman karyawan.'),

                DatePicker::make('tanggal_terbit')
                    ->label('Tanggal terbit')
                    ->required()
                    ->default(now())
                    ->helperText('Tanggal yang tampil di kartu pengumuman, dan yang menentukan urutannya.'),

                Toggle::make('is_aktif')
                    ->label('Tampilkan ke karyawan')
                    ->default(true)
                    ->helperText('Matikan untuk menyembunyikan tanpa menghapus.'),

                // created_by TIDAK ada di form: diisi otomatis dari admin yang
                // sedang login (lihat CreateInformasi::mutateFormDataBeforeCreate).
            ]);
    }
}
