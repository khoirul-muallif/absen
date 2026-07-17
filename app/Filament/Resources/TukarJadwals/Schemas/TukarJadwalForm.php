<?php

namespace App\Filament\Resources\TukarJadwals\Schemas;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Jadwal;
use App\Models\TukarJadwal;
use App\Models\Karyawan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TukarJadwalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('mode')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'tukar' => 'Tukar dengan rekan kerja',
                        'pindah' => 'Pindah tanggal sendiri (tanpa rekan)',
                    ])
                    ->default('tukar')
                    ->live()
                    ->dehydrated(false)
                    ->required()
                    ->columnSpanFull()
                    // FIX #1: sinkronkan mode dengan data asli saat edit
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->isPindahSendiri() ? 'pindah' : 'tukar');
                        }
                    }),

                Select::make('karyawan_pengaju_filter')
                    ->label('Karyawan pengaju')
                    ->options(fn () => Karyawan::pluck('nama', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->required()
                    // sinkronkan filter pengaju saat edit
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->karyawan_pengaju_id);
                        }
                    })
                    ->afterStateUpdated(fn ($set) => $set('jadwal_id', null)),

                Select::make('jadwal_id')
                    ->label('Jadwal yang mau diajukan')
                    ->options(fn (Get $get) => self::opsiJadwal($get('karyawan_pengaju_filter')))
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get) => ! $get('karyawan_pengaju_filter'))
                    ->helperText(fn (Get $get) => ! $get('karyawan_pengaju_filter') ? 'Pilih karyawan pengaju dulu' : null)
                    ->rule(function ($record) {
                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                            if (! $value) return;

                            $konflik = TukarJadwal::where('status', 'pending')
                                ->where(fn ($q) => $q->where('jadwal_id', $value)->orWhere('jadwal_tujuan_id', $value))
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($konflik) {
                                $fail('Jadwal ini sudah dipakai di pengajuan tukar/pindah lain yang masih pending.');
                            }
                        };
                    }),
                Select::make('karyawan_tujuan_filter')
                    ->label('Karyawan rekan tujuan')
                    // FIX #2: jangan pakai ->relationship() yang minjam relasi pengaju.
                    // Pakai options manual + hydrate manual dari jadwalTujuan->karyawan.
                    ->options(fn () => Karyawan::pluck('nama', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah')
                    ->required(fn (Get $get) => $get('mode') !== 'pindah')
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->jadwalTujuan) {
                            $component->state($record->jadwalTujuan->karyawan_id);
                        }
                    })
                    ->afterStateUpdated(fn ($set) => $set('jadwal_tujuan_id', null)),

                Select::make('jadwal_tujuan_id')
                    ->label('Jadwal tujuan (rekan)')
                    ->options(fn (Get $get) => self::opsiJadwal($get('karyawan_tujuan_filter')))
                    ->searchable()
                    ->different('jadwal_id')
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah')
                    ->required(fn (Get $get) => $get('mode') !== 'pindah')
                    ->dehydrated(fn (Get $get) => $get('mode') !== 'pindah')
                    ->disabled(fn (Get $get) => ! $get('karyawan_tujuan_filter'))
                    ->helperText(fn (Get $get) => ! $get('karyawan_tujuan_filter') ? 'Pilih karyawan rekan dulu' : null)
                    ->rule(function (Get $get, $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $jadwalIdAsal = $get('jadwal_id');
                            if (! $jadwalIdAsal || ! $value) return;

                            // Cek 1: rebutan pending dengan pengajuan lain
                            $konflikPending = TukarJadwal::where('status', 'pending')
                                ->where(fn ($q) => $q->where('jadwal_id', $value)->orWhere('jadwal_tujuan_id', $value))
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($konflikPending) {
                                $fail('Jadwal rekan ini sudah dipakai di pengajuan tukar/pindah lain yang masih pending.');
                                return;
                            }

                            // Cek 2: konflik jadwal existing (sudah ada sebelumnya)
                            $jadwalAsal   = Jadwal::find($jadwalIdAsal);
                            $jadwalTujuan = Jadwal::find($value);
                            if (! $jadwalAsal || ! $jadwalTujuan) return;

                            $konflikAsal = Jadwal::where('karyawan_id', $jadwalAsal->karyawan_id)
                                ->where('tanggal', $jadwalTujuan->tanggal)
                                ->where('id', '!=', $jadwalAsal->id)
                                ->exists();

                            $konflikTujuan = Jadwal::where('karyawan_id', $jadwalTujuan->karyawan_id)
                                ->where('tanggal', $jadwalAsal->tanggal)
                                ->where('id', '!=', $jadwalTujuan->id)
                                ->exists();

                            if ($konflikAsal || $konflikTujuan) {
                                $fail('Tidak bisa ditukar: salah satu karyawan sudah punya jadwal sendiri di tanggal pasangannya.');
                            }
                        };
                    }),

                DatePicker::make('tanggal_baru')
                    ->label('Pindah ke tanggal')
                    ->visible(fn (Get $get) => $get('mode') === 'pindah')
                    ->required(fn (Get $get) => $get('mode') === 'pindah')
                    ->dehydrated(fn (Get $get) => $get('mode') === 'pindah')
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $jadwalId = $get('jadwal_id');
                            if (! $jadwalId || ! $value) {
                                return;
                            }

                            $jadwal = Jadwal::find($jadwalId);
                            if (! $jadwal) {
                                return;
                            }

                            $tanggalBaru = \Carbon\Carbon::parse($value);

                            $sudahAdaJadwal = Jadwal::where('karyawan_id', $jadwal->karyawan_id)
                                ->where('tanggal', $tanggalBaru->toDateString())
                                ->where('id', '!=', $jadwal->id)
                                ->exists();

                            if ($sudahAdaJadwal) {
                                $fail('Karyawan ini sudah punya jadwal di tanggal tersebut.');
                                return;
                            }

                            $bentrok = Cuti::where('karyawan_id', $jadwal->karyawan_id)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggalBaru)
                                ->whereDate('tanggal_selesai', '>=', $tanggalBaru)
                                ->exists()
                                || Dinas::where('karyawan_id', $jadwal->karyawan_id)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggalBaru)
                                ->whereDate('tanggal_selesai', '>=', $tanggalBaru)
                                ->exists();

                            if ($bentrok) {
                                $fail('Karyawan sedang cuti/dinas (disetujui) pada tanggal tersebut.');
                            }
                        };
                    }),

                Textarea::make('alasan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    protected static function opsiJadwal(?int $karyawanId): array
    {
        if (! $karyawanId) {
            return [];
        }

        return Jadwal::with('shift')
            ->where('karyawan_id', $karyawanId)
            ->orderBy('tanggal')
            ->get()
            ->mapWithKeys(fn (Jadwal $jadwal) => [
                $jadwal->id => "{$jadwal->tanggal->format('d M Y')} ({$jadwal->shift->nama_shift})",
            ])
            ->toArray();
    }
}
