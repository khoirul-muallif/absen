<?php

namespace App\Filament\Resources\QrInstansis\Schemas;

use App\Models\QrInstansi;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QrInstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('QR Code Instansi')
                    ->description('QR statis yang ditempel di lokasi instansi untuk scan absensi')
                    ->icon('heroicon-o-qr-code')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        TextInput::make('kode_qr')
                            ->label('Kode QR')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn () => QrInstansi::generateKode())
                            ->helperText('Kode unik yang di-encode ke dalam QR. Kosongkan dan simpan untuk generate otomatis.')
                            ->columnSpanFull(),

                        DateTimePicker::make('expired_at')
                            ->label('Kadaluarsa')
                            ->displayFormat('d M Y H:i')
                            ->helperText('Kosongkan untuk QR statis permanen (tidak kadaluarsa)')
                            ->nullable(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('QR Aktif')
                            ->default(true)
                            ->helperText('QR yang tidak aktif tidak bisa digunakan untuk absen'),
                    ]),
            ]);
    }
}
