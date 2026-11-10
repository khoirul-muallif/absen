<?php

namespace App\Filament\Resources\Karyawans\Pages;

use App\Exports\KaryawanTemplateExport;
use App\Filament\Resources\Karyawans\KaryawanResource;
use App\Imports\KaryawanImport;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\PolaRotasi;
use App\Support\HasilImportKaryawan;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListKaryawans extends ListRecords
{
    protected static string $resource = KaryawanResource::class;

    protected const FOLDER_IMPORT = 'import-karyawan';

    /** Dibawa antar dua langkah modal: unggah -> pratinjau -> commit. */
    public ?string $berkasImport = null;

    public ?int $instansiImport = null;

    // Deklarasi properti
    public array $pratinjauImport = [];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('unduhTemplateImport')
                ->label('Unduh Template Import')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new KaryawanTemplateExport($this->unitYangSudahAda()),
                    'template-import-karyawan-' . now()->format('Y-m-d') . '.xlsx',
                )),

            $this->actionImport(),
            $this->actionKonfirmasiImport(),
        ];
    }

    /**
     * Langkah 1 — unggah & pratinjau.
     *
     * Berkasnya DISIMPAN, bukan dibaca sekali lalu dibuang: commit membaca
     * ulang dari berkas yang sama, bukan dari hasil dry-run yang disimpan.
     * Kalau hasil dry-run yang dipakai, pengecekan unik jadi bohong begitu
     * ada admin lain yang menambah karyawan di sela-sela pratinjau.
     */
    protected function actionImport(): Action
    {
        return Action::make('importKaryawan')
            ->label('Import dari Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Periksa berkas')
            ->schema([
                Select::make('instansi_id')
                    ->label('Instansi')
                    ->options(fn () => Instansi::query()->pluck('nama', 'id'))
                    ->required()
                    ->searchable()
                    ->helperText('Berlaku untuk SEMUA baris di file. Instansi sengaja tidak ada di template — supaya tidak ada 150 sel yang bisa salah.'),

                FileUpload::make('berkas')
                    ->label('Berkas Excel')
                    ->required()
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->disk('local')
                    ->directory(self::FOLDER_IMPORT)
                    ->helperText('Pakai template dari tombol "Unduh Template Import". Jangan mengubah nama kolom di sheet "Data Karyawan".'),
            ])
            ->action(function (array $data, Action $action) {
                $this->bersihkanBerkasLama();

                $this->berkasImport = $data['berkas'];
                $this->instansiImport = (int) $data['instansi_id'];

                $import = new KaryawanImport($this->instansiImport, dryRun: true);
                Excel::import($import, Storage::disk('local')->path($this->berkasImport));

                // Di actionImport(), ganti blok setelah Excel::import
                $this->pratinjauImport = [
                    'siap'       => count($import->hasil->siap),
                    'ditolak'    => $import->hasil->ditolak,
                    'peringatan' => $import->hasil->peringatan,
                    'ringkasan'  => $import->hasil->ringkasan(),
                ];

                if ($import->hasil->tidakAdaYangBisaDiimpor()) {
                    $this->buangBerkasImport();

                    Notification::make()
                        ->title('Tidak ada baris yang bisa diimpor')
                        ->body($import->hasil->ringkasan())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $this->replaceMountedAction('konfirmasiImportKaryawan');
            });
    }

    /**
     * Langkah 2 — pratinjau & commit.
     *
     * Berkas dibaca ULANG di sini, tidak memakai $this->pratinjauImport
     * untuk menulis. Yang disimpan di langkah 1 cuma untuk ditampilkan.
     */
    protected function actionKonfirmasiImport(): Action
    {
        return Action::make('konfirmasiImportKaryawan')
            // Tidak pernah ditekan langsung — di-mount oleh actionImport()
            // lewat replaceMountedAction(). Terdaftar di getHeaderActions()
            // hanya supaya Filament mengenalinya saat di-mount.
            ->hidden(true)
            ->modalHeading('Pratinjau import karyawan')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(fn (): string => $this->labelTombolImport())
            ->modalCancelActionLabel('Batal')
            ->schema([
                Text::make(fn (): HtmlString => $this->ringkasanPratinjau()),
            ])
            ->action(function () {
                if ($this->berkasImport === null) {
                    Notification::make()
                        ->title('Berkas import sudah tidak ada')
                        ->body('Ulangi dari tombol Import dari Excel.')
                        ->danger()
                        ->send();

                    return;
                }

                $import = new KaryawanImport($this->instansiImport, dryRun: false);
                Excel::import($import, Storage::disk('local')->path($this->berkasImport));

                $hasil = $import->hasil;

                $this->buangBerkasImport();
                $this->pratinjauImport = [];

                Notification::make()
                    ->title(count($hasil->siap) . ' karyawan berhasil diimpor')
                    ->body($this->catatanSetelahImport($hasil))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function labelTombolImport(): string
    {
        $siap = $this->pratinjauImport['siap'] ?? 0;
        $ditolak = count($this->pratinjauImport['ditolak'] ?? []);

        // Angkanya SENGAJA ada di tombol. Impor sebagian diizinkan, jadi
        // admin tidak boleh bisa menekan tombol tanpa sadar ada yang
        // tertinggal.
        return $ditolak > 0
            ? "Impor {$siap} baris, lewati {$ditolak}"
            : "Impor {$siap} baris";
    }

    /**
     * Maksimal 20 baris bermasalah. Admin tidak membaca 150 pesan satu per
     * satu — dia memperbaiki beberapa, unggah ulang, ulangi. Daftar penuh
     * cuma menambah gulir.
     */
    protected function ringkasanPratinjau(): HtmlString
    {
        $p = $this->pratinjauImport;

        if ($p === []) {
            return new HtmlString('<p>Tidak ada pratinjau. Ulangi dari tombol Import dari Excel.</p>');
        }

        $baris = ['<p class="font-medium">' . e($p['ringkasan']) . '</p>'];

        if ($p['ditolak'] !== []) {
            $baris[] = '<p class="mt-4 font-medium text-danger-600">Baris yang dilewati:</p><ul class="mt-1 space-y-1 text-sm">';

            foreach (array_slice($p['ditolak'], 0, 20) as $tolak) {
                $nama = $tolak['nama'] ? ' (' . e($tolak['nama']) . ')' : '';
                $baris[] = '<li><strong>Baris ' . $tolak['baris'] . '</strong>' . $nama . ' — ' . e(implode(' ', $tolak['pesan'])) . '</li>';
            }

            $baris[] = '</ul>';

            if (count($p['ditolak']) > 20) {
                $baris[] = '<p class="mt-1 text-sm">dan ' . (count($p['ditolak']) - 20) . ' baris lainnya.</p>';
            }
        }

        if ($p['peringatan'] !== []) {
            $baris[] = '<p class="mt-4 font-medium text-warning-600">Peringatan (tetap diimpor):</p><ul class="mt-1 space-y-1 text-sm">';

            foreach (array_slice($p['peringatan'], 0, 20) as $ingat) {
                $baris[] = '<li><strong>Baris ' . $ingat['baris'] . '</strong> — ' . e($ingat['pesan']) . '</li>';
            }

            $baris[] = '</ul>';

            if (count($p['peringatan']) > 20) {
                $baris[] = '<p class="mt-1 text-sm">dan ' . (count($p['peringatan']) - 20) . ' lainnya.</p>';
            }
        }

        $baris[] = '<p class="mt-4 text-sm">Semua karyawan mendapat password default yang sama. Setelah import, assign shift atau pola rotasi lewat menu Shift Karyawan — <strong>karyawan tanpa assignment tidak bisa absen</strong>.</p>';

        return new HtmlString(implode('', $baris));
    }

    protected function catatanSetelahImport(HasilImportKaryawan $hasil): string
    {
        $catatan = 'Password default sama untuk semua.';

        if ($hasil->ditolak !== []) {
            $catatan .= ' ' . count($hasil->ditolak) . ' baris dilewati dan perlu diinput manual.';
        }

        return $catatan . ' Jangan lupa assign shift atau pola rotasi — karyawan tanpa assignment tidak bisa absen.';
    }

    protected function buangBerkasImport(): void
    {
        if ($this->berkasImport !== null) {
            Storage::disk('local')->delete($this->berkasImport);
            $this->berkasImport = null;
        }
    }

    /**
     * Admin yang menutup modal pratinjau tanpa konfirmasi meninggalkan
     * berkas yatim. Dibersihkan saat import berikutnya, bukan lewat
     * scheduler — tidak perlu menambah cron untuk fitur yang dipakai
     * beberapa kali seumur hidup aplikasi.
     */
    protected function bersihkanBerkasLama(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::FOLDER_IMPORT)) {
            return;
        }

        foreach ($disk->files(self::FOLDER_IMPORT) as $berkas) {
            if ($disk->lastModified($berkas) < now()->subDay()->timestamp) {
                $disk->delete($berkas);
            }
        }
    }

    protected function unitYangSudahAda(): array
    {
        return collect()
            ->merge(Karyawan::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
            ->merge(PolaRotasi::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
            ->map(fn ($u) => (string) $u)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
