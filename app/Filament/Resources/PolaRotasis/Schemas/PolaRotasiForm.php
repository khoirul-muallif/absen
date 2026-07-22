<?php

namespace App\Filament\Resources\PolaRotasis\Schemas;

use App\Models\Shift;
use App\Models\Instansi;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PolaRotasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pola')
                    ->description('Template pola rotasi yang bisa di-assign ke banyak karyawan')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->columns(2)
                    ->schema([
                         Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama') // sesuaikan 'nama' kalau kolom nama di tabel instansi beda
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => Instansi::query()->value('id'))
                            ->columnSpanFull(),
                        TextInput::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('nama_pola')
                            ->label('Nama Pola')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Contoh: "Rotasi ICU 3 Shift 4 Hari"'),

                        Toggle::make('berlaku_saat_libur_nasional')
                            ->label('Tetap Berlaku Saat Libur Nasional')
                            ->default(true)
                            ->helperText('Aktifkan untuk unit 24 jam (IGD/ICU). Kalau nonaktif, libur nasional otomatis override jadi libur apapun posisi siklusnya.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),

                Section::make('Langkah Siklus')
                    ->description('Urutan hari menentukan posisi siklus. Panjang siklus = jumlah langkah di bawah.')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        Repeater::make('langkah')
                            ->label('')
                            ->schema([
                                Toggle::make('libur')
                                    ->label('Hari Libur')
                                    ->live()
                                    ->default(false),

                                Select::make('shift_id')
                                    ->label('Shift')
                                    ->options(fn () => Shift::query()->pluck('nama_shift', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => ! $get('libur'))
                                    ->visible(fn (Get $get) => ! $get('libur')),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Hari ke Siklus')
                            ->reorderableWithButtons()
                            ->minItems(1)
                            ->itemLabel(fn (array $state): ?string => ($state['libur'] ?? false)
                                ? 'Libur'
                                : (isset($state['shift_id']) ? Shift::find($state['shift_id'])?->nama_shift : 'Belum dipilih'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
