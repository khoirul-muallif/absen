<?php

namespace App\Filament\Resources\Cutis\Schemas;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\JenisCuti;
use App\Models\KuotaCuti;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CutiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->description('Karyawan, jenis cuti, dan periode')
                    ->schema([
                        Grid::make(2)->schema([
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
                                ->live()
                                ->required(),
                        ]),

                        Placeholder::make('info_kuota')
                            ->label('Info kuota')
                            ->live()
                            ->content(function (Get $get, ?Cuti $record) {
                                $karyawanId = $get('karyawan_id');
                                $jenisCutiId = $get('jenis_cuti_id');
                                $tanggalMulai = $get('tanggal_mulai');

                                if (! $karyawanId || ! $jenisCutiId) {
                                    return 'Pilih karyawan & jenis cuti dulu.';
                                }

                                $jenisCuti = JenisCuti::find($jenisCutiId);
                                if (! $jenisCuti?->potong_kuota) {
                                    return 'Jenis cuti ini tidak memotong kuota.';
                                }

                                $tahun = $tanggalMulai ? \Carbon\Carbon::parse($tanggalMulai)->year : now()->year;

                                $kuota = KuotaCuti::untuk($karyawanId, $jenisCutiId, $tahun);

                               if (! $kuota) {
                                    $jumlahHariPending = Cuti::hariPendingUntuk($karyawanId, $jenisCutiId, $tahun);

                                    return new HtmlString(
                                        "Belum ada data kuota untuk tahun {$tahun}, jadi dipakai kuota default "
                                        ."jenis cuti ini: <b>{$jenisCuti->default_kuota} hari</b>. Row kuota akan "
                                        .'dibuat otomatis saat pengajuan ini disetujui, dan kuotanya tetap diperiksa — '
                                        .'tidak ada pengecualian lagi.'
                                        .($jumlahHariPending > 0 ? " Pending lain: <b>{$jumlahHariPending}</b> hari." : '')
                                    );
                                }

                                // Record sendiri dikecualikan supaya jumlah_hari-nya
                                // tidak terhitung dua kali saat form edit.
                                $pending = Cuti::hariPendingUntuk(
                                    $karyawanId,
                                    $jenisCutiId,
                                    $tahun,
                                    $record?->id
                                );

                                $efektif = $kuota->sisa - $pending;

                                $ringkas = "Kuota {$tahun}: <b>{$kuota->kuota}</b> · Terpakai: <b>{$kuota->terpakai}</b>"
                                    ." · Sisa: <b>{$kuota->sisa}</b>";

                                if ($pending > 0) {
                                    $ringkas .= " · Pending lain: <b>{$pending}</b> · Efektif: <b>{$efektif}</b>";
                                }

                                return new HtmlString($ringkas);
                            }),

                        Grid::make(3)->schema([
                            DatePicker::make('tanggal_mulai')
                                ->required()
                                ->live()
                                ->native(false)
                                ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::hitungJumlahHari($state, $get('tanggal_selesai'), $set)),
                            DatePicker::make('tanggal_selesai')
                                ->required()
                                ->live()
                                ->native(false)
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
                                        $jenisCutiId = $get('jenis_cuti_id');
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
                                            return;
                                        }

                                        if ($jenisCutiId) {
                                            $jenisCuti = JenisCuti::find($jenisCutiId);
                                            if ($jenisCuti?->potong_kuota) {
                                                $jumlahHari = $tanggalMulai->diffInDays($tanggalSelesai) + 1;

                                                // null = belum ada row KuotaCuti sama sekali. Sejak fase 42, baris tanpa
                                                // row dianggap punya sisa = default_kuota jenis cuti (bukan "tidak
                                                // terbatas"), karena row akan dibuat otomatis saat approve dan tetap
                                                // diperiksa (Cuti::afterApprove() -> KuotaCuti::pastikanUntuk()).
                                                $sisa = KuotaCuti::sisaUntuk(
                                                    $karyawanId,
                                                    $jenisCutiId,
                                                    $tanggalMulai->year
                                                ) ?? $jenisCuti->default_kuota;

                                                if ($jumlahHari > $sisa) {
                                                    $fail("Jumlah hari ({$jumlahHari}) melebihi sisa kuota tahun {$tanggalMulai->year} (sisa: {$sisa}).");
                                                }
                                            }
                                        }
                                    };
                                })
                                ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::hitungJumlahHari($get('tanggal_mulai'), $state, $set)),
                            TextInput::make('jumlah_hari')
                                ->required()
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->helperText('Otomatis terhitung'),
                        ]),
                    ]),

                Section::make('Detail')
                    ->schema([
                        Textarea::make('alasan')
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('lampiran')
                            ->directory('lampiran-cuti')
                            ->required(function (Get $get) {
                                $jenisCutiId = $get('jenis_cuti_id');
                                if (! $jenisCutiId) {
                                    return false;
                                }
                                return JenisCuti::find($jenisCutiId)?->perlu_lampiran ?? false;
                            })
                            ->helperText(function (Get $get) {
                                $jenisCutiId = $get('jenis_cuti_id');
                                $perluLampiran = $jenisCutiId
                                    ? JenisCuti::find($jenisCutiId)?->perlu_lampiran
                                    : null;

                                return match (true) {
                                    $perluLampiran === true => 'Wajib untuk jenis cuti ini.',
                                    $perluLampiran === false => 'Tidak wajib untuk jenis cuti ini.',
                                    default => 'Wajib untuk jenis cuti yang butuh surat keterangan.',
                                };
                            }),
                    ]),
            ]);
    }

    protected static function hitungJumlahHari(
        ?string $tanggalMulai,
        ?string $tanggalSelesai,
        Set $set
    ): void {
        if ($tanggalMulai && $tanggalSelesai) {
            $mulai = \Carbon\Carbon::parse($tanggalMulai);
            $selesai = \Carbon\Carbon::parse($tanggalSelesai);

            if ($selesai->lt($mulai)) {
                return;
            }

            $set('jumlah_hari', $mulai->diffInDays($selesai) + 1);
        }
    }
}
