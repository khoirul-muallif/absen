<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Schemas;

use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class KaryawanPolaRotasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment Pola Rotasi')
                    ->description('Assign karyawan rotasi ke pola, dengan tanggal_mulai sebagai anchor posisi siklus (bisa beda antar karyawan biar shift ke-cover / staggered)')
                    ->icon('heroicon-o-calendar-date-range')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship(
                                name: 'karyawan',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn ($query) => $query->where('tipe_jadwal', Karyawan::TIPE_ROTASI),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                            ->rules([
                                Rule::exists('karyawan', 'id')
                                    ->where('tipe_jadwal', Karyawan::TIPE_ROTASI),
                            ])
                            ->validationMessages([
                                'exists' => 'Karyawan yang dipilih harus bertipe jadwal Rotasi.',
                            ])
                            ->helperText('Hanya karyawan tipe "Rotasi" yang muncul di daftar ini — pakai pola siklus, bukan shift tetap. Karyawan tipe "Umum" tidak akan muncul walau dicari; assign lewat menu "Shift Karyawan Umum" di grup Manajemen Shift.'),

                        Select::make('pola_rotasi_id')
                            ->label('Pola Rotasi')
                            // Dibatasi ke instansi DAN unit kerja karyawan.
                            // Sebelumnya tidak dibatasi sama sekali — kejadian
                            // ketiga berturut-turut setelah KaryawanShift (fase
                            // 31) dan PolaRotasi (fase 32).
                            ->relationship(
                                name: 'polaRotasi',
                                titleAttribute: 'nama_pola',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId) {
                                        return $query;
                                    }

                                    $karyawan = Karyawan::find($karyawanId);

                                    if (! $karyawan) {
                                        return $query;
                                    }

                                    return $query
                                        ->where('instansi_id', $karyawan->instansi_id)
                                        ->where('unit_kerja', $karyawan->unit_kerja);
                                },
                            )
                            // Keputusan fase 32 mengizinkan nama pola yang sama
                            // di unit berbeda, jadi nama saja bisa ambigu.
                            ->getOptionLabelFromRecordUsing(fn (PolaRotasi $record): string => sprintf(
                                '%s (%s · siklus %d hari)',
                                $record->nama_pola,
                                $record->unit_kerja,
                                $record->panjangSiklus()
                            ))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Cuma pola milik instansi DAN unit kerja karyawan yang dipilih yang muncul. Pilih karyawan dulu kalau daftarnya kosong.')
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId || ! $value) {
                                        return;
                                    }

                                    $karyawan = Karyawan::find($karyawanId);
                                    $pola = PolaRotasi::find($value);

                                    if (! $karyawan || ! $pola) {
                                        return;
                                    }

                                    if ($pola->instansi_id !== $karyawan->instansi_id) {
                                        $fail('Pola yang dipilih bukan milik instansi karyawan ini.');

                                        return;
                                    }

                                    if ($pola->unit_kerja !== $karyawan->unit_kerja) {
                                        $fail(sprintf(
                                            'Pola ini untuk unit "%s", sedangkan karyawan terdaftar di unit "%s".',
                                            $pola->unit_kerja,
                                            $karyawan->unit_kerja ?? '(kosong)'
                                        ));

                                        return;
                                    }

                                    // Pola tanpa langkah bikin posisiSiklusPada()
                                    // melempar LogicException saat generator jalan.
                                    if ($pola->panjangSiklus() === 0) {
                                        $fail('Pola ini belum punya satu pun langkah siklus, jadi belum bisa dipakai. Lengkapi dulu lewat menu Pola Rotasi.');
                                    }
                                };
                            }),

                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai (Anchor Siklus)')
                            ->required()
                            ->live(onBlur: true)
                            ->displayFormat('d M Y')
                            ->helperText('Posisi hari ke-1 di siklus dimulai dari tanggal ini. Dua karyawan di pola yang sama dengan tanggal mulai berbeda akan berada di posisi siklus berbeda (staggered).')
                            // Sama seperti KaryawanShift di fase 31: dua assignment
                            // beririsan berarti dua anchor berbeda, dan anchor
                            // itulah yang menentukan SELURUH urutan siklus. Jadi
                            // akibatnya lebih parah daripada sekadar salah pilih
                            // shift — seluruh jadwalnya bergeser.
                            ->rule(function (Get $get, ?KaryawanPolaRotasi $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId || ! $value) {
                                        return;
                                    }

                                    $mulai = Carbon::parse($value);
                                    $akhirRaw = $get('tanggal_berakhir');
                                    $akhir = $akhirRaw ? Carbon::parse($akhirRaw) : null;

                                    $beririsan = KaryawanPolaRotasi::with('polaRotasi')
                                        ->where('karyawan_id', $karyawanId)
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->when($akhir, fn ($q) => $q->whereDate('tanggal_mulai', '<=', $akhir))
                                        ->where(function ($q) use ($mulai) {
                                            $q->whereNull('tanggal_berakhir')
                                                ->orWhereDate('tanggal_berakhir', '>=', $mulai);
                                        })
                                        ->first();

                                    if ($beririsan) {
                                        $fail(sprintf(
                                            'Karyawan ini sudah punya assignment pola yang periodenya beririsan (%s, mulai %s%s). Akhiri dulu assignment lama itu sebelum membuat yang baru.',
                                            $beririsan->polaRotasi?->nama_pola ?? 'pola lain',
                                            Carbon::parse($beririsan->tanggal_mulai)->format('d M Y'),
                                            $beririsan->tanggal_berakhir
                                                ? ' s/d '.Carbon::parse($beririsan->tanggal_berakhir)->format('d M Y')
                                                : ', berlaku sampai diganti'
                                        ));
                                    }
                                };
                            }),

                        DatePicker::make('tanggal_berakhir')
                            ->label('Berlaku Sampai')
                            ->live(onBlur: true)
                            ->displayFormat('d M Y')
                            ->helperText('Kosongkan jika berlaku sampai diganti.')
                            ->after('tanggal_mulai'),
                    ]),
            ]);
    }
}
