<?php

namespace App\Filament\Resources\Cutis\Schemas;

use App\Models\Dinas;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live()
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
                    ->afterOrEqual('tanggal_mulai')
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $mulai = $get('tanggal_mulai');
                            if (! $mulai || ! $value) {
                                return;
                            }

                            $tanggalMulai = \Carbon\Carbon::parse($mulai);
                            $tanggalSelesai = \Carbon\Carbon::parse($value);

                            if ($tanggalMulai->year !== $tanggalSelesai->year) {
                                $fail('Rentang cuti tidak boleh melintasi pergantian tahun. Buat pengajuan terpisah untuk masing-masing tahun.');
                                return;
                            }

                            $karyawanId = $get('karyawan_id');
                            if (! $karyawanId) {
                                return;
                            }

                            $bentrok = Dinas::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->where('tanggal_mulai', '<=', $tanggalSelesai)
                                ->where('tanggal_selesai', '>=', $tanggalMulai)
                                ->exists();

                            if ($bentrok) {
                                $fail('Karyawan ini sudah tercatat dinas (disetujui) yang bentrok dengan rentang tanggal ini.');
                            }
                        };
                    })
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

                if ($selesai->lt($mulai)) {
                    return; // biarkan validasi afterOrEqual yang menangani, jangan hitung angka salah
                }

                $set('jumlah_hari', $mulai->diffInDays($selesai) + 1);
            }
    }
}
