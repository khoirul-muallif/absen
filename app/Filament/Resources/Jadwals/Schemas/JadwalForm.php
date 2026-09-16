<?php

namespace App\Filament\Resources\Jadwals\Schemas;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
                Placeholder::make('peringatan_sinkronisasi')
                    ->label('⚠️ Baris ini dibuat otomatis')
                    ->visible(fn (?Jadwal $record): bool => self::dariSinkronisasi($record))
                    ->content(fn (?Jadwal $record): string => 'Jadwal ini hasil sinkronisasi dari pengajuan '
                        .($record?->jenis === 'cuti' ? 'Cuti' : 'Dinas')
                        .' yang sudah disetujui. Karyawan, tanggal, jenis, dan shift dikunci — '
                        .'mengubahnya akan bertentangan dengan pengajuan yang masih approved, dan tertimpa '
                        .'lagi kalau sinkronisasi dijalankan ulang. Kalau datanya keliru, perbaiki lewat modul '
                        .($record?->jenis === 'cuti' ? 'Cuti' : 'Dinas').'. Kolom Keterangan tetap bisa diisi.')
                    ->columnSpanFull(),

                Select::make('karyawan_id')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (?Jadwal $record): bool => self::dariSinkronisasi($record))
                    ->required(),

                DatePicker::make('tanggal')
                    ->required()
                    ->live(onBlur: true) // supaya helperText di bawah re-evaluate saat tanggal diisi
                    ->disabled(fn (?Jadwal $record): bool => self::dariSinkronisasi($record))
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

                            $bentrok = Cuti::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggal)
                                ->whereDate('tanggal_selesai', '>=', $tanggal)
                                ->exists()
                                || Dinas::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->whereDate('tanggal_mulai', '<=', $tanggal)
                                ->whereDate('tanggal_selesai', '>=', $tanggal)
                                ->exists();

                            if ($bentrok) {
                                $fail('Karyawan ini sedang cuti/dinas (disetujui) pada tanggal tersebut.');
                            }
                        };
                    })
                    ->helperText(function (Get $get) {
                        $karyawanId = $get('karyawan_id');
                        $tanggal    = $get('tanggal');
                        $jenis      = $get('jenis');

                        // jenis 'libur' udah sadar sendiri, gak perlu warning
                        if (! $karyawanId || ! $tanggal || $jenis === 'libur') {
                            return null;
                        }

                        $karyawan = Karyawan::find($karyawanId);
                        if (! $karyawan || ! $karyawan->instansi_id) {
                            return null;
                        }

                        $namaLibur = HariLibur::where('instansi_id', $karyawan->instansi_id)
                            ->whereDate('tanggal', $tanggal)
                            ->value('nama');

                        if ($namaLibur) {
                            return "⚠️ Tanggal ini terdaftar sebagai hari libur nasional/cuti bersama ({$namaLibur}). Pastikan jenis jadwal sudah sesuai — pakai \"Piket\" kalau tetap bertugas, atau ganti ke \"Libur\" kalau memang tidak masuk.";
                        }

                        return null;
                    }),

                Select::make('jenis')
                    // Opsi cuti/dinas cuma muncul untuk baris yang MEMANG sudah
                    // berjenis itu (hasil sinkronisasi), supaya admin tidak bisa
                    // mengarang jadwal cuti/dinas dari form — baris jenis itu
                    // lahir dari approval, bukan diketik manual.
                    ->options(fn (?Jadwal $record): array => self::dariSinkronisasi($record)
                        ? [
                            'reguler' => 'Reguler',
                            'piket'   => 'Piket',
                            'libur'   => 'Libur',
                            'cuti'    => 'Cuti',
                            'dinas'   => 'Dinas',
                        ]
                        : [
                            'reguler' => 'Reguler',
                            'piket'   => 'Piket',
                            'libur'   => 'Libur',
                        ])
                    ->default('reguler')
                    ->live()
                    // Guard dinilai dari $record (nilai tersimpan), BUKAN dari
                    // state hidup. Versi lama memakai $state: begitu admin
                    // memilih "Cuti" di form create, field langsung mengunci
                    // dirinya sendiri dan admin terjebak tidak bisa membatalkan.
                    ->disabled(fn (?Jadwal $record): bool => self::dariSinkronisasi($record))
                    ->required(),

                Select::make('shift_id')
                    ->relationship('shift', 'nama_shift')
                    // Lihat catatan yang sama di AbsensiForm: dua shift bernama
                    // sama dengan jam berbeda itu data yang sah, jadi labelnya
                    // yang dibuat membedakan (fase 30).
                    ->getOptionLabelFromRecordUsing(fn (Shift $record): string => $record->labelLengkap())
                    ->searchable()
                    ->preload()
                    // Wajib hanya untuk jenis yang memang butuh shift.
                    // Sebelumnya required untuk SEMUA jenis kecuali 'libur' —
                    // akibatnya baris hasil sinkronisasi (jenis cuti/dinas,
                    // shift_id null) tidak bisa disimpan sama sekali lewat Edit:
                    // form menuntut shift diisi, padahal mengisinya justru
                    // merusak data.
                    ->required(fn (Get $get): bool => in_array($get('jenis'), ['reguler', 'piket'], true))
                    ->visible(fn (Get $get): bool => in_array($get('jenis'), ['reguler', 'piket'], true))
                    ->disabled(fn (?Jadwal $record): bool => self::dariSinkronisasi($record)),

                Select::make('sumber')
                    ->options([
                        'generate' => 'Generate (boleh ditimpa generator)',
                        'manual'   => 'Manual (dilindungi dari generate ulang)',
                    ])
                    // Baris yang dibuat lewat form ini TIDAK pernah berasal dari
                    // generator, jadi default-nya manual. Default DB ('generate')
                    // salah untuk jalur Filament: akibatnya jadwal yang diinput
                    // admin sendiri ikut terhapus oleh
                    // `jadwal:generate-rotasi --overwrite-generate`.
                    ->default('manual')
                    ->required()
                    ->disabled(fn (?Jadwal $record): bool => self::dariSinkronisasi($record))
                    ->helperText('Menentukan apakah baris ini boleh ditimpa saat jadwal dibuat ulang secara massal. '
                        .'"Generate" boleh ditimpa, "Manual" dilindungi dan tetap bertahan. '
                        .'Mengedit baris TIDAK mengubah pilihan ini otomatis — ubah sendiri ke "Manual" '
                        .'kalau suntinganmu perlu dilindungi.'),

                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Apakah baris ini lahir dari sinkronisasi Cuti/Dinas approved?
     *
     * Dinilai dari $record (nilai yang tersimpan), bukan dari state form yang
     * sedang berjalan — supaya memilih "Cuti" di form create tidak ikut memicu
     * penguncian. Baris jenis cuti/dinas dibuat oleh
     * HasApprovalWorkflow::sinkronisasiJadwalDanAbsensi(); mengubahnya di sini
     * akan bertentangan dengan pengajuan yang masih approved dan tertimpa lagi
     * saat sinkronisasi dijalankan ulang. Pola yang sama seperti guard
     * AbsensiForm (fase 27 batch B).
     */
    protected static function dariSinkronisasi(?Jadwal $record): bool
    {
        return $record !== null && in_array($record->jenis, ['cuti', 'dinas'], true);
    }
}
