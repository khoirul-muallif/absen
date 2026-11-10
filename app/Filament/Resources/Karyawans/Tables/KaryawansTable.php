<?php

namespace App\Filament\Resources\Karyawans\Tables;

use App\Models\Karyawan;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\KaryawanPolaRotasi;
use App\Models\KaryawanShift;
use App\Models\PolaRotasi;
use App\Models\Shift;
use App\Support\PerencanaAssignment;
use App\Support\RencanaAssignment;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class KaryawansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto_profil')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=K&background=1D9E75&color=fff'),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                // Field yang paling banyak mencabangkan perilaku sistem (fase 13)
                // sebelumnya sama sekali tidak muncul di tabel — bukan kolom,
                // bukan filter. Admin tidak punya cara melihat siapa umum dan
                // siapa rotasi selain membuka satu per satu.
                TextColumn::make('tipe_jadwal')
                    ->label('Tipe Jadwal')
                    ->badge()
                    ->color(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'warning' : 'primary')
                    ->formatStateUsing(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'Rotasi' : 'Umum')
                    ->tooltip(fn (string $state): string => $state === Karyawan::TIPE_ROTASI
                        ? 'Dijadwalkan dari pola siklus lewat menu Shift Karyawan Rotasi.'
                        : 'Dijadwalkan lewat penugasan shift periode di menu Shift Karyawan Umum.')
                    ->sortable(),

                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status_pegawai')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tetap'     => 'success',
                        'kontrak'   => 'warning',
                        'orientasi' => 'info',
                        'magang'    => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                        default     => $state,
                    }),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin'    => 'danger',
                        'karyawan' => 'primary',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->tooltip('Belum mengatur akses apa pun — approval selalu lewat akun admin Filament terpisah.')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_telepon')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tanggal_bergabung')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                SelectFilter::make('tipe_jadwal')
                    ->label('Tipe Jadwal')
                    ->options([
                        Karyawan::TIPE_UMUM   => 'Umum',
                        Karyawan::TIPE_ROTASI => 'Rotasi',
                    ]),

                SelectFilter::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->options(fn () => Karyawan::query()
                        ->whereNotNull('unit_kerja')
                        ->distinct()
                        ->orderBy('unit_kerja')
                        ->pluck('unit_kerja', 'unit_kerja')),

                SelectFilter::make('status_pegawai')
                    ->label('Status Pegawai')
                    ->options([
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                    ]),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin'    => 'Admin',
                        'karyawan' => 'Karyawan',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('resetPassword')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset password karyawan')
                        ->modalDescription(fn ($record) => "Password {$record->nama} akan dikembalikan ke password default. "
                            . 'Sampaikan langsung ke yang bersangkutan, dan minta dia segera menggantinya lewat menu Ubah Password di aplikasi.')
                        ->modalSubmitActionLabel('Ya, reset')
                        ->action(function ($record) {
                            // Kolom password punya cast 'hashed', jadi string mentah di sini
                            // otomatis di-hash. JANGAN Hash::make() manual — ter-hash dua kali
                            // dan karyawan tidak akan bisa login.
                            $record->update(['password' => 'password']);

                            Notification::make()
                                ->title('Password berhasil direset')
                                ->body("Password {$record->nama} sekarang kembali ke default.")
                                ->success()
                                ->send();
                        }),

                    // SELURUH FK ke karyawan_id memakai ON DELETE CASCADE
                    // (SCHEMA.md), jadi satu klik Hapus melenyapkan absensi,
                    // cuti, izin, lembur, dinas, jadwal, kuota, dan penugasan
                    // orang itu — permanen, tanpa jejak, tanpa peringatan apa
                    // pun sebelumnya. Untuk karyawan yang berhenti bekerja,
                    // yang benar adalah menonaktifkan.
                    DeleteAction::make()
                        ->visible(fn (Karyawan $record): bool => ! $record->punyaRiwayat())
                        ->modalDescription('Karyawan ini belum punya riwayat absensi maupun pengajuan, jadi aman dihapus.'),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Karyawan $record): bool => $record->punyaRiwayat())
                        ->requiresConfirmation()
                        ->modalHeading('Karyawan ini tidak bisa dihapus')
                        ->modalDescription('Karyawan ini sudah punya riwayat (absensi, pengajuan, jadwal, atau penugasan shift). Menghapusnya akan ikut melenyapkan SEMUA data itu secara permanen. Untuk karyawan yang sudah berhenti bekerja, nonaktifkan saja lewat tombol Ubah — datanya tetap tersimpan untuk rekap dan audit.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignShift')
                        ->label('Assign Shift (karyawan umum)')
                        ->icon('heroicon-o-clock')
                        ->color('primary')
                        ->modalWidth(Width::TwoExtraLarge)
                        ->schema([
                            Select::make('shift_id')
                                ->label('Shift')
                                ->options(fn () => Shift::query()
                                    ->where('is_active', true)
                                    ->pluck('nama_shift', 'id'))
                                ->required()
                                ->live(),

                            DatePicker::make('tanggal_berlaku')
                                ->label('Berlaku mulai')
                                ->default(today())
                                ->required()
                                ->live(onBlur: true)
                                ->helperText('Karyawan yang tanggal bergabungnya lebih lambat akan memakai tanggal bergabungnya sendiri.'),

                            Text::make(fn (Get $get, $livewire): HtmlString => static::pratinjauUmum($get, $livewire))
                                ->columnSpanFull(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $rencana = PerencanaAssignment::umum(
                                $records,
                                Shift::find($data['shift_id']),
                                Carbon::parse($data['tanggal_berlaku']),
                            );

                            if (! $rencana->adaYangBisaDitulis()) {
                                static::beritahuGagal($rencana);

                                return;
                            }

                            DB::transaction(function () use ($rencana, $data) {
                                foreach ($rencana->siap as $baris) {
                                    KaryawanShift::create([
                                        'karyawan_id'      => $baris['karyawan']->id,
                                        'shift_id'         => $data['shift_id'],
                                        'tanggal_berlaku'  => $baris['anchor'],
                                        'tanggal_berakhir' => null,
                                    ]);
                                }
                            });

                            static::beritahuBerhasil($rencana, 'shift');
                        }),

                    BulkAction::make('assignPolaRotasi')
                        ->label('Assign Pola Rotasi (karyawan rotasi)')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->schema([
                            Select::make('pola_rotasi_id')
                                ->label('Pola Rotasi')
                                ->options(fn () => PolaRotasi::query()
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn (PolaRotasi $p) => [
                                        $p->id => "{$p->nama_pola} ({$p->unit_kerja})",
                                    ]))
                                ->required()
                                ->live()
                                ->helperText('Unit kerja karyawan harus SAMA PERSIS dengan unit pola, termasuk huruf besar-kecilnya.'),

                            DatePicker::make('tanggal_mulai')
                                ->label('Siklus mulai')
                                ->default(today())
                                ->required()
                                ->live(onBlur: true)
                                ->helperText('Anchor tiap karyawan dimundurkan dari tanggal ini supaya mereka tersebar di seluruh siklus.'),

                            Text::make(fn (Get $get, $livewire): HtmlString => static::pratinjauRotasi($get, $livewire))
                                ->columnSpanFull(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $rencana = PerencanaAssignment::rotasi(
                                $records,
                                PolaRotasi::find($data['pola_rotasi_id']),
                                Carbon::parse($data['tanggal_mulai']),
                            );

                            if (! $rencana->adaYangBisaDitulis()) {
                                static::beritahuGagal($rencana);

                                return;
                            }

                            DB::transaction(function () use ($rencana, $data) {
                                foreach ($rencana->siap as $baris) {
                                    KaryawanPolaRotasi::create([
                                        'karyawan_id'      => $baris['karyawan']->id,
                                        'pola_rotasi_id'   => $data['pola_rotasi_id'],
                                        'tanggal_mulai'    => $baris['anchor'],
                                        'tanggal_berakhir' => null,
                                    ]);
                                }
                            });

                            static::beritahuBerhasil($rencana, 'pola rotasi', ingatkanGenerate: true);
                        }),

                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            $punyaRiwayat = $records->filter(fn (Karyawan $karyawan): bool => $karyawan->punyaRiwayat());

                            if ($punyaRiwayat->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian karyawan tidak bisa dihapus')
                                    ->body('Sudah punya riwayat absensi/pengajuan: '.$punyaRiwayat->pluck('nama')->join(', ').'. Tidak ada yang dihapus — nonaktifkan saja kalau sudah berhenti bekerja.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Pratinjau dihitung ulang tiap render dari pilihan yang sedang aktif —
     * tidak ada state yang disimpan, jadi tidak ada yang bisa basi.
     */
    protected static function pratinjauUmum(Get $get, $livewire): HtmlString
    {
        $shift = $get('shift_id') ? Shift::find($get('shift_id')) : null;

        if (! $shift || ! $get('tanggal_berlaku')) {
            return new HtmlString('<p class="text-sm">Pilih shift dan tanggal untuk melihat pratinjau.</p>');
        }

        $rencana = PerencanaAssignment::umum(
            static::karyawanTerpilih($livewire),
            $shift,
            Carbon::parse($get('tanggal_berlaku')),
        );

        $html = ['<p class="font-medium">' . count($rencana->siap) . ' karyawan akan di-assign.</p>'];
        $html[] = static::daftarDitolak($rencana);

        return new HtmlString(implode('', $html));
    }

    protected static function pratinjauRotasi(Get $get, $livewire): HtmlString
    {
        $pola = $get('pola_rotasi_id') ? PolaRotasi::find($get('pola_rotasi_id')) : null;

        if (! $pola || ! $get('tanggal_mulai')) {
            return new HtmlString('<p class="text-sm">Pilih pola dan tanggal untuk melihat pratinjau.</p>');
        }

        $rencana = PerencanaAssignment::rotasi(
            static::karyawanTerpilih($livewire),
            $pola,
            Carbon::parse($get('tanggal_mulai')),
        );

        $html = ['<p class="font-medium">' . count($rencana->siap) . ' karyawan akan di-assign ke siklus ' . $pola->panjangSiklus() . ' langkah.</p>'];

        // Distribusi posisi adalah yang sebenarnya ingin dilihat admin:
        // kalau semua orang menumpuk di satu posisi, shift malam kosong.
        if ($rencana->distribusiPosisi() !== []) {
            $bagian = [];

            foreach ($rencana->distribusiPosisi() as $posisi => $jumlah) {
                $bagian[] = "langkah {$posisi}: {$jumlah} orang";
            }

            $html[] = '<p class="mt-2 text-sm">Sebaran: ' . e(implode(', ', $bagian)) . '</p>';
        }

        if ($rencana->siap !== []) {
            $html[] = '<p class="mt-3 font-medium">Anchor tiap karyawan:</p><ul class="mt-1 space-y-1 text-sm">';

            foreach (array_slice($rencana->siap, 0, 15) as $baris) {
                $html[] = '<li>' . e($baris['karyawan']->nama) . ' — mulai ' . e($baris['anchor'])
                    . ' (langkah ' . $baris['posisi'] . ')</li>';
            }

            $html[] = '</ul>';

            if (count($rencana->siap) > 15) {
                $html[] = '<p class="mt-1 text-sm">dan ' . (count($rencana->siap) - 15) . ' lainnya.</p>';
            }
        }

        $html[] = static::daftarDitolak($rencana);

        return new HtmlString(implode('', $html));
    }

    protected static function daftarDitolak(RencanaAssignment $rencana): string
    {
        if ($rencana->ditolak === []) {
            return '';
        }

        $html = ['<p class="mt-4 font-medium text-danger-600">' . count($rencana->ditolak) . ' dilewati:</p><ul class="mt-1 space-y-1 text-sm">'];

        foreach (array_slice($rencana->ditolak, 0, 15) as $tolak) {
            $html[] = '<li><strong>' . e($tolak['nama']) . '</strong> — ' . e($tolak['alasan']) . '</li>';
        }

        $html[] = '</ul>';

        if (count($rencana->ditolak) > 15) {
            $html[] = '<p class="mt-1 text-sm">dan ' . (count($rencana->ditolak) - 15) . ' lainnya.</p>';
        }

        return implode('', $html);
    }

    /** Baris yang dicentang admin, dibaca dari state tabel Livewire. */
    protected static function karyawanTerpilih($livewire)
    {
        return collect($livewire->getSelectedTableRecords());
    }

    protected static function beritahuGagal(RencanaAssignment $rencana): void
    {
        Notification::make()
            ->title('Tidak ada karyawan yang bisa di-assign')
            ->body($rencana->ditolak[0]['alasan'] ?? 'Periksa tipe jadwal dan unit kerjanya.')
            ->danger()
            ->persistent()
            ->send();
    }

    protected static function beritahuBerhasil(RencanaAssignment $rencana, string $jenis, bool $ingatkanGenerate = false): void
    {
        $catatan = $rencana->ditolak !== []
            ? count($rencana->ditolak) . ' dilewati, lihat alasannya di pratinjau.'
            : '';

        if ($ingatkanGenerate) {
            // Langkah 3 dari tiga prasyarat deploy: tanpa generate, karyawan
            // rotasi tetap tidak bisa absen walaupun polanya sudah di-assign.
            $catatan .= ' Jalankan jadwal:generate-rotasi supaya jadwalnya terbentuk — tanpa itu mereka belum bisa absen.';
        }

        Notification::make()
            ->title(count($rencana->siap) . " karyawan di-assign {$jenis}")
            ->body(trim($catatan))
            ->success()
            ->persistent()
            ->send();
    }
}
