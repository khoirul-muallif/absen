<?php

namespace App\Filament\Resources\JenisCutis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class JenisCutiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->placeholder('Contoh: Cuti Tahunan, Cuti Sakit, Cuti Melahirkan'),
                TextInput::make('default_kuota')
                    ->label('Default kuota (hari)')
                    ->required()
                    ->numeric()
                    ->default(12)
                    ->helperText('Jumlah hari kuota yang otomatis diberikan ke karyawan baru'),
                Toggle::make('is_tahunan')
                    ->label('Kuota tahunan')
                    ->helperText('Aktif jika kuota di-reset tiap tahun (misal Cuti Tahunan)')
                    ->default(true)
                    ->required(),
                Toggle::make('potong_kuota')
                    ->label('Memotong kuota')
                    ->helperText('Aktif jika pengajuan jenis ini mengurangi sisa kuota karyawan')
                    ->default(true)
                    ->required(),
                Toggle::make('perlu_lampiran')
                    ->label('Wajib lampiran')
                    ->helperText('Aktif jika pengajuan jenis ini wajib menyertakan surat/dokumen pendukung')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktifkan untuk menyembunyikan dari pilihan tanpa menghapus datanya')
                    ->default(true)
                    ->required(),
            ]);
    }
}
