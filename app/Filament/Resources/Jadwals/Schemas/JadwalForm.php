<?php

namespace App\Filament\Resources\Jadwals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class JadwalForm
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

                DatePicker::make('tanggal')
                    ->required()
                    ->unique(
                        table: 'jadwals',
                        column: 'tanggal',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('karyawan_id', $get('karyawan_id')),
                    )
                    ->validationMessages([
                        'unique' => 'Karyawan ini sudah punya jadwal di tanggal tersebut.',
                    ])
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $karyawanId = $get('karyawan_id');
                            if (! $karyawanId || ! $value) {
                                return;
                            }

                            $tanggal = \Carbon\Carbon::parse($value);

                            $bentrok = \App\Models\Cuti::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggal)
                                ->whereDate('tanggal_selesai', '>=', $tanggal)
                                ->exists()
                                || \App\Models\Dinas::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggal)
                                ->whereDate('tanggal_selesai', '>=', $tanggal)
                                ->exists();

                            if ($bentrok) {
                                $fail('Karyawan ini sedang cuti/dinas (disetujui) pada tanggal tersebut.');
                            }
                        };
                    }),

                Select::make('jenis')
                    ->options([
                        'reguler' => 'Reguler',
                        'piket' => 'Piket',
                        'libur' => 'Libur',
                    ])
                    ->default('reguler')
                    ->live()
                    ->required(),
                Select::make('shift_id')
                    ->relationship('shift', 'nama_shift')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get) => $get('jenis') !== 'libur')
                    ->visible(fn (Get $get) => $get('jenis') !== 'libur'),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
