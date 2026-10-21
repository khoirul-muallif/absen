<?php

namespace App\Filament\Resources\TukarJadwals\Schemas;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Jadwal;
use App\Models\TukarJadwal;
use App\Models\Karyawan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->isPindahSendiri() ? 'pindah' : 'tukar');
                        }
                    }),

                Select::make('karyawan_pengaju_filter')
                    ->label('Karyawan pengaju')
                    ->options(fn () => self::opsiKaryawanPengaju())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->required()
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state($record->karyawan_pengaju_id);
                        }
                    })
                    ->afterStateUpdated(function ($set) {
                        $set('tanggal_pengajuan', null);
                        $set('jadwal_id', null);
                        $set('karyawan_tujuan_filter', null);
                        $set('tanggal_tujuan_pengajuan', null);
                        $set('jadwal_tujuan_id', null);
                    }),

                // ── Tanggal Jadwal pengaju ───────────────────────────────────
                // Diganti dari Select (daftar panjang semua tanggal) jadi
                // DatePicker kalender — hanya tanggal yang benar-benar punya
                // Jadwal (dan bukan cuti/dinas) yang bisa diklik. jadwal_id
                // (Hidden) yang sebenarnya dikirim ke server, diisi otomatis
                // lewat afterStateUpdated begitu tanggal dipilih.
                DatePicker::make('tanggal_pengajuan')
                    ->label('Tanggal Jadwal yang mau diajukan')
                    ->native(false)
                    ->live()
                    ->dehydrated(false)
                    ->disabled(fn (Get $get) => ! $get('karyawan_pengaju_filter'))
                    ->helperText(fn (Get $get) => ! $get('karyawan_pengaju_filter')
                        ? 'Pilih karyawan pengaju dulu'
                        : 'Hanya tanggal yang sudah punya Jadwal yang bisa dipilih.')
                    ->minDate(fn (Get $get) => self::tanggalPalingAwal($get('karyawan_pengaju_filter')))
                    ->maxDate(fn (Get $get) => self::tanggalPalingAkhir($get('karyawan_pengaju_filter')))
                    ->disabledDates(fn (Get $get) => self::tanggalDisabled($get('karyawan_pengaju_filter')))
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->jadwal) {
                            $component->state($record->jadwal->tanggal->format('Y-m-d'));
                        }
                    })
                    ->afterStateUpdated(function (Get $get, $set, $state) {
                        $tersedia = self::tanggalJadwalMap($get('karyawan_pengaju_filter'));
                        $set('jadwal_id', $state && isset($tersedia[$state]) ? $tersedia[$state]['id'] : null);
                    }),

                Placeholder::make('shift_pengajuan_info')
                    ->label('Shift pada tanggal ini')
                    ->content(function (Get $get) {
                        $tersedia = self::tanggalJadwalMap($get('karyawan_pengaju_filter'));
                        $tanggal = $get('tanggal_pengajuan');

                        return ($tanggal && isset($tersedia[$tanggal])) ? $tersedia[$tanggal]['label'] : '-';
                    })
                    ->visible(fn (Get $get) => (bool) $get('tanggal_pengajuan')),

                Hidden::make('jadwal_id')
                    ->required()
                    ->rule(function ($record) {
                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                            if (! $value) return;

                            $konflik = TukarJadwal::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
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
                    ->options(fn (Get $get) => self::opsiKaryawanTujuan($get('karyawan_pengaju_filter')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah')
                    ->required(fn (Get $get) => $get('mode') !== 'pindah')
                    ->disabled(fn (Get $get) => ! $get('karyawan_pengaju_filter'))
                    ->helperText(fn (Get $get) => ! $get('karyawan_pengaju_filter') ? 'Pilih karyawan pengaju dulu' : null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->jadwalTujuan) {
                            $component->state($record->jadwalTujuan->karyawan_id);
                        }
                    })
                    ->afterStateUpdated(function ($set) {
                        $set('tanggal_tujuan_pengajuan', null);
                        $set('jadwal_tujuan_id', null);
                    })
                    ->rule(function (Get $get, $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $jadwalIdAsal = $get('jadwal_id');
                            $jadwalIdTujuan = $get('jadwal_tujuan_id');
                            if (! $jadwalIdAsal || ! $jadwalIdTujuan) return;

                            // Cek 1: rebutan pending dengan pengajuan lain
                            $konflikPending = TukarJadwal::whereIn('status', ['menunggu_rekan', 'menunggu_admin'])
                                ->where(fn ($q) => $q->where('jadwal_id', $jadwalIdTujuan)->orWhere('jadwal_tujuan_id', $jadwalIdTujuan))
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($konflikPending) {
                                $fail('Jadwal rekan ini sudah dipakai di pengajuan tukar/pindah lain yang masih pending.');
                                return;
                            }

                            // Cek 2: konflik jadwal existing (sudah ada sebelumnya)
                            $jadwalAsal   = Jadwal::find($jadwalIdAsal);
                            $jadwalTujuan = Jadwal::find($jadwalIdTujuan);
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
                                return;
                            }

                            // Cek 3: salah satu pihak sedang cuti/dinas approved di
                            // tanggal yang terlibat (baik tanggal asalnya sendiri
                            // maupun tanggal barunya hasil tukar)
                            if (TukarJadwal::karyawanCutiDinasApproved($jadwalAsal->karyawan_id, $jadwalAsal->tanggal)
                                || TukarJadwal::karyawanCutiDinasApproved($jadwalAsal->karyawan_id, $jadwalTujuan->tanggal)
                                || TukarJadwal::karyawanCutiDinasApproved($jadwalTujuan->karyawan_id, $jadwalTujuan->tanggal)
                                || TukarJadwal::karyawanCutiDinasApproved($jadwalTujuan->karyawan_id, $jadwalAsal->tanggal)
                            ) {
                                $fail('Tidak bisa ditukar: salah satu karyawan sedang cuti/dinas (disetujui) di tanggal yang terlibat.');
                            }
                        };
                    }),

                // ── Tanggal Jadwal tujuan (rekan) ────────────────────────────
                DatePicker::make('tanggal_tujuan_pengajuan')
                    ->label('Tanggal Jadwal tujuan (rekan)')
                    ->native(false)
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah')
                    ->disabled(fn (Get $get) => ! $get('karyawan_tujuan_filter'))
                    ->helperText(fn (Get $get) => ! $get('karyawan_tujuan_filter')
                        ? 'Pilih karyawan rekan dulu'
                        : 'Hanya tanggal yang sudah punya Jadwal yang bisa dipilih.')
                    ->minDate(fn (Get $get) => self::tanggalPalingAwal($get('karyawan_tujuan_filter')))
                    ->maxDate(fn (Get $get) => self::tanggalPalingAkhir($get('karyawan_tujuan_filter')))
                    ->disabledDates(fn (Get $get) => self::tanggalDisabled($get('karyawan_tujuan_filter')))
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->jadwalTujuan) {
                            $component->state($record->jadwalTujuan->tanggal->format('Y-m-d'));
                        }
                    })
                    ->afterStateUpdated(function (Get $get, $set, $state) {
                        $tersedia = self::tanggalJadwalMap($get('karyawan_tujuan_filter'));
                        $set('jadwal_tujuan_id', $state && isset($tersedia[$state]) ? $tersedia[$state]['id'] : null);
                    }),

                Placeholder::make('shift_tujuan_info')
                    ->label('Shift pada tanggal ini')
                    ->content(function (Get $get) {
                        $tersedia = self::tanggalJadwalMap($get('karyawan_tujuan_filter'));
                        $tanggal = $get('tanggal_tujuan_pengajuan');

                        return ($tanggal && isset($tersedia[$tanggal])) ? $tersedia[$tanggal]['label'] : '-';
                    })
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah' && (bool) $get('tanggal_tujuan_pengajuan')),

                Hidden::make('jadwal_tujuan_id')
                    ->different('jadwal_id')
                    ->visible(fn (Get $get) => $get('mode') !== 'pindah')
                    ->required(fn (Get $get) => $get('mode') !== 'pindah')
                    ->dehydrated(fn (Get $get) => $get('mode') !== 'pindah'),

                DatePicker::make('tanggal_baru')
                    ->label('Pindah ke tanggal')
                    ->native(false)
                    ->live()
                    ->visible(fn (Get $get) => $get('mode') === 'pindah')
                    ->required(fn (Get $get) => $get('mode') === 'pindah')
                    ->dehydrated(fn (Get $get) => $get('mode') === 'pindah')
                    ->disabledDates(function (Get $get) {
                        $semua = self::tanggalJadwalMap($get('karyawan_pengaju_filter'));

                        return collect($semua)
                            ->reject(fn ($info) => $info['label'] === 'Libur')
                            ->keys()
                            ->toArray();
                    })
                    ->helperText('Hanya tanggal yang sudah berstatus Libur milik karyawan ini yang bisa dipilih (untuk karyawan umum: tanggal kosong juga bisa).')
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

                            $existing = Jadwal::where('karyawan_id', $jadwal->karyawan_id)
                                ->where('tanggal', $tanggalBaru->toDateString())
                                ->where('id', '!=', $jadwal->id)
                                ->first();

                            if ($existing && $existing->jenis !== Jadwal::JENIS_LIBUR) {
                                $fail('Karyawan ini sudah punya jadwal (bukan libur) di tanggal tersebut.');
                                return;
                            }

                            if (TukarJadwal::karyawanCutiDinasApproved($jadwal->karyawan_id, $tanggalBaru)) {
                                $fail('Karyawan sedang cuti/dinas (disetujui) pada tanggal tersebut.');
                            }
                        };
                    }),

                Textarea::make('alasan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tanggal -> ['id' => jadwal_id, 'label' => nama shift/"Libur"] untuk satu
     * karyawan. Sumber kebenaran tunggal dipakai DatePicker, Placeholder shift
     * info, minDate/maxDate, dan disabledDates — supaya tidak ada versi query
     * yang menyimpang satu sama lain.
     */
    protected static function tanggalJadwalMap(?int $karyawanId): array
    {
        if (! $karyawanId) {
            return [];
        }

        return Jadwal::with('shift')
            ->where('karyawan_id', $karyawanId)
            // Baris hasil sinkronisasi Cuti/Dinas approved dikunci dari modul
            // lain sejak fase 21/28 (JadwalForm) — TukarJadwal harus konsisten.
            //
            // Jadwal berjenis 'libur' SENGAJA TETAP ditampilkan — boleh jadi
            // objek tukar/pindah (keputusan RS, fase 50).
            ->whereNotIn('jenis', [Jadwal::JENIS_CUTI, Jadwal::JENIS_DINAS])
            ->orderBy('tanggal')
            ->get()
            ->mapWithKeys(fn (Jadwal $jadwal) => [
                $jadwal->tanggal->format('Y-m-d') => [
                    'id' => $jadwal->id,
                    'label' => $jadwal->shift ? $jadwal->shift->nama_shift : 'Libur',
                ],
            ])
            ->toArray();
    }

    protected static function tanggalPalingAwal(?int $karyawanId): ?string
    {
        $tersedia = array_keys(self::tanggalJadwalMap($karyawanId));

        return $tersedia ? min($tersedia) : null;
    }

    protected static function tanggalPalingAkhir(?int $karyawanId): ?string
    {
        $tersedia = array_keys(self::tanggalJadwalMap($karyawanId));

        return $tersedia ? max($tersedia) : null;
    }

    /**
     * Semua tanggal DI DALAM rentang [min, max] milik karyawan ini yang TIDAK
     * punya Jadwal (atau Jadwal-nya cuti/dinas) — ini yang di-disable di
     * kalender, supaya cuma tanggal yang benar-benar punya Jadwal valid yang
     * bisa diklik.
     */
    protected static function tanggalDisabled(?int $karyawanId): array
    {
        $tersedia = self::tanggalJadwalMap($karyawanId);
        if (empty($tersedia)) {
            return [];
        }

        $tanggalTersedia = array_keys($tersedia);
        $cursor = \Carbon\Carbon::parse(min($tanggalTersedia));
        $akhir = \Carbon\Carbon::parse(max($tanggalTersedia));

        $disabled = [];
        while ($cursor->lte($akhir)) {
            $key = $cursor->format('Y-m-d');
            if (! isset($tersedia[$key])) {
                $disabled[] = $key;
            }
            $cursor->addDay();
        }

        return $disabled;
    }

    protected static function labelKaryawan(Karyawan $k): string
    {
        $unit = $k->unit_kerja ?: '-';
        $tipe = $k->isRotasi() ? 'rotasi' : 'umum';

        return "{$k->nama} - {$unit} ({$tipe})";
    }

    protected static function opsiKaryawanPengaju(): array
    {
        return Karyawan::where('is_active', true)
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Karyawan $k) => [$k->id => self::labelKaryawan($k)])
            ->toArray();
    }

    /**
     * Daftar rekan tujuan difilter berdasarkan tipe_jadwal pengaju: karyawan
     * umum cuma bisa ditawari rekan umum lain (unit_kerja tidak relevan buat
     * umum — assignment-nya lewat KaryawanShift, bukan pola per unit). Karyawan
     * rotasi cuma ditawari rekan rotasi DI UNIT YANG SAMA — menukar/meminjam
     * shift dari pola unit lain tidak masuk akal secara operasional.
     */
    protected static function opsiKaryawanTujuan(?int $karyawanPengajuId): array
    {
        if (! $karyawanPengajuId) {
            return [];
        }

        $pengaju = Karyawan::find($karyawanPengajuId);
        if (! $pengaju) {
            return [];
        }

        $query = Karyawan::where('is_active', true)
            ->where('id', '!=', $pengaju->id);

        if ($pengaju->isRotasi()) {
            $query->where('tipe_jadwal', Karyawan::TIPE_ROTASI)
                ->where('unit_kerja', $pengaju->unit_kerja);
        } else {
            $query->where('tipe_jadwal', Karyawan::TIPE_UMUM);
        }

        return $query->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Karyawan $k) => [$k->id => self::labelKaryawan($k)])
            ->toArray();
    }
}
