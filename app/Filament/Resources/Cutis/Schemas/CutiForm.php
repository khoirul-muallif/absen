<?php

namespace App\Filament\Resources\Cutis\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CutiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('jenis_cuti_id')
                    ->relationship('jenisCuti', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('tanggal_mulai')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::hitungJumlahHari($state, $get('tanggal_selesai'), $set)),
                DatePicker::make('tanggal_selesai')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::hitungJumlahHari($get('tanggal_mulai'), $state, $set)),
                TextInput::make('jumlah_hari')
                    ->required()
                    ->numeric()
                    ->helperText('Otomatis terhitung dari tanggal mulai & selesai'),
                Textarea::make('alasan')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('lampiran')
                    ->directory('lampiran-cuti')
                    ->helperText('Wajib untuk jenis cuti yang butuh surat keterangan'),
            ]);
    }

    protected static function hitungJumlahHari(
            ?string $tanggalMulai,
            ?string $tanggalSelesai,
            \Filament\Schemas\Components\Utilities\Set $set
        ): void {
            if ($tanggalMulai && $tanggalSelesai) {
                $mulai = \Carbon\Carbon::parse($tanggalMulai);
                $selesai = \Carbon\Carbon::parse($tanggalSelesai);
                $set('jumlah_hari', $mulai->diffInDays($selesai) + 1);
            }
    }
}
