<?php

namespace App\Filament\Resources\KaryawanShifts\Schemas;

use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class KaryawanShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan Shift')
                    ->description('Tentukan shift yang berlaku untuk karyawan ini')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship(
                                name: 'karyawan',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn ($query) => $query->where('tipe_jadwal', Karyawan::TIPE_UMUM),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                            ->rules([
                                Rule::exists('karyawan', 'id')
                                    ->where('tipe_jadwal', Karyawan::TIPE_UMUM),
                            ])
                            ->validationMessages([
                                'exists' => 'Karyawan yang dipilih harus bertipe jadwal Umum.',
                            ])
                            ->helperText('Hanya karyawan tipe "Umum" yang muncul di daftar ini — shift tetap per periode. Karyawan tipe "Rotasi" tidak akan muncul walau dicari; assign lewat menu "Shift Karyawan Rotasi" di grup Manajemen Rotasi.'),

                        Select::make('shift_id')
                            ->label('Shift')
                            // Shift dibatasi ke instansi karyawan yang dipilih.
                            // Sebelumnya tidak ada pembatasan sama sekali, jadi
                            // karyawan bisa di-assign shift milik instansi lain
                            // tanpa ada yang menolak — sisi shift terlewat waktu
                            // guard karyawan_id dipasang di fase 18.
                            ->relationship(
                                name: 'shift',
                                titleAttribute: 'nama_shift',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId) {
                                        return $query;
                                    }

                                    $instansiId = Karyawan::whereKey($karyawanId)->value('instansi_id');

                                    return $query->where('instansi_id', $instansiId);
                                },
                            )
                            // Label memakai labelLengkap(): dua shift bernama
                            // sama dengan jam berbeda itu data yang sah (fase 30).
                            ->getOptionLabelFromRecordUsing(fn (Shift $record): string => $record->labelLengkap())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Cuma shift milik instansi karyawan yang dipilih yang muncul. Pilih karyawan dulu kalau daftarnya kosong.')
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId || ! $value) {
                                        return;
                                    }

                                    $instansiKaryawan = Karyawan::whereKey($karyawanId)->value('instansi_id');
                                    $instansiShift = Shift::whereKey($value)->value('instansi_id');

                                    if ($instansiKaryawan && $instansiShift && $instansiKaryawan !== $instansiShift) {
                                        $fail('Shift yang dipilih bukan milik instansi karyawan ini.');
                                    }
                                };
                            }),

                        DatePicker::make('tanggal_berlaku')
                            ->label('Berlaku Mulai')
                            ->required()
                            ->live(onBlur: true)
                            ->displayFormat('d M Y')
                            ->helperText('Shift ini aktif mulai tanggal ini')
                            // KEPUTUSAN: satu karyawan umum tidak boleh punya dua
                            // assignment yang periodenya beririsan. Tabel
                            // karyawan_shift cuma punya index (bukan unique), dan
                            // form sebelumnya tidak mengecek apa pun — padahal
                            // AbsensiController::masuk() memilih assignment lewat
                            // latest('tanggal_berlaku')->first(). Dengan dua
                            // assignment ber-tanggal_berlaku sama, shift mana yang
                            // dipakai jadi tidak deterministik antar request.
                            //
                            // Transisi berurutan (berakhir 31 Jul, berlaku mulai
                            // 1 Agu) TIDAK dianggap beririsan.
                            ->rule(function (Get $get, ?KaryawanShift $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $karyawanId = $get('karyawan_id');

                                    if (! $karyawanId || ! $value) {
                                        return;
                                    }

                                    $mulai = Carbon::parse($value);
                                    $akhirRaw = $get('tanggal_berakhir');
                                    $akhir = $akhirRaw ? Carbon::parse($akhirRaw) : null;

                                    $beririsan = KaryawanShift::where('karyawan_id', $karyawanId)
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        // Assignment lama mulai sebelum/pas periode
                                        // baru berakhir. Kalau periode baru
                                        // open-ended, syarat ini selalu terpenuhi.
                                        ->when($akhir, fn ($q) => $q->whereDate('tanggal_berlaku', '<=', $akhir))
                                        // Assignment lama berakhir setelah/pas
                                        // periode baru mulai — atau tidak pernah
                                        // berakhir sama sekali.
                                        ->where(function ($q) use ($mulai) {
                                            $q->whereNull('tanggal_berakhir')
                                                ->orWhereDate('tanggal_berakhir', '>=', $mulai);
                                        })
                                        ->first();

                                    if ($beririsan) {
                                        $sampai = $beririsan->tanggal_berakhir
                                            ? Carbon::parse($beririsan->tanggal_berakhir)->format('d M Y')
                                            : 'sampai diganti';

                                        $fail(sprintf(
                                            'Karyawan ini sudah punya penugasan shift yang periodenya beririsan (%s, mulai %s %s). Akhiri dulu penugasan lama itu sebelum membuat yang baru.',
                                            $beririsan->shift?->nama_shift ?? 'shift lain',
                                            Carbon::parse($beririsan->tanggal_berlaku)->format('d M Y'),
                                            $beririsan->tanggal_berakhir ? "s/d {$sampai}" : '(berlaku sampai diganti)'
                                        ));
                                    }
                                };
                            }),

                        DatePicker::make('tanggal_berakhir')
                            ->label('Berlaku Sampai')
                            ->live(onBlur: true)
                            ->displayFormat('d M Y')
                            ->helperText('Kosongkan jika berlaku sampai diganti. Generator jadwal bulanan hanya memakai penugasan yang periodenya mencakup bulan yang digenerate.')
                            ->after('tanggal_berlaku'),
                    ]),
            ]);
    }
}
