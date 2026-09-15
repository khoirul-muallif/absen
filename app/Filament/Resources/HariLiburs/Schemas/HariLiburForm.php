<?php

namespace App\Filament\Resources\HariLiburs\Schemas;

use App\Models\Instansi;
use App\Models\Jadwal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class HariLiburForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->relationship('instansi', 'nama')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default(fn () => Instansi::first()?->id)
                    ->required(),

                DatePicker::make('tanggal')
                    ->required()
                    ->live(onBlur: true)
                    // Constraint DB-nya unique(instansi_id, tanggal), tapi rule
                    // lama cuma mengecek kolom `tanggal` saja tanpa scope
                    // instansi. Efeknya KEBALIKAN dari kasus KuotaCuti/Absensi:
                    // form jadi LEBIH KETAT dari DB, sehingga instansi kedua
                    // tidak bisa mendaftarkan tanggal yang sudah dipakai
                    // instansi pertama — data yang sah ditolak. Pola yang benar
                    // sudah ada di JadwalForm.
                    ->unique(
                        table: 'hari_liburs',
                        column: 'tanggal',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('instansi_id', $get('instansi_id')),
                    )
                    ->validationMessages([
                        'unique' => 'Instansi ini sudah punya hari libur di tanggal tersebut.',
                    ]),

                // Menambahkan hari libur TIDAK menyentuh Jadwal yang sudah
                // terlanjur digenerate. Alurnya wajar dan gampang terjadi:
                // generate bulanan dijalankan dulu, baru admin sadar ada cuti
                // bersama. Tanpa peringatan ini, baris Jadwal tetap 'reguler'
                // dan RekapHarian tetap menandai alpha bagi yang tidak absen.
                Placeholder::make('peringatan_jadwal')
                    ->label('Dampak ke jadwal yang sudah ada')
                    ->live()
                    ->content(function (Get $get): HtmlString|string {
                        $tanggal = $get('tanggal');
                        $instansiId = $get('instansi_id');

                        if (! $tanggal || ! $instansiId) {
                            return 'Pilih instansi & tanggal dulu.';
                        }

                        $jumlah = Jadwal::whereDate('tanggal', $tanggal)
                            ->where('jenis', '!=', 'libur')
                            ->whereHas('karyawan', fn ($q) => $q->where('instansi_id', $instansiId))
                            ->count();

                        if ($jumlah === 0) {
                            return 'Belum ada jadwal kerja di tanggal ini — aman.';
                        }

                        return new HtmlString(
                            "⚠️ Sudah ada <b>{$jumlah}</b> jadwal kerja (bukan libur) di tanggal ini. "
                            .'Menyimpan hari libur <b>tidak</b> mengubahnya otomatis. Jalankan ulang '
                            .'<code>jadwal:generate-bulanan</code> / <code>jadwal:generate-rotasi --overwrite-generate</code>, '
                            .'atau ubah baris Jadwal-nya manual. Baris ber-<i>sumber</i> manual tetap tidak akan tertimpa.'
                        );
                    })
                    ->columnSpanFull(),

                TextInput::make('nama')
                    ->required()
                    ->placeholder('Contoh: Idul Fitri 1447 H, Hari Kemerdekaan'),

                Textarea::make('keterangan')
                    ->columnSpanFull(),

                Toggle::make('is_cuti_bersama')
                    ->label('Cuti bersama')
                    // Helper text lama ("bukan hari libur resmi") menyiratkan ada
                    // perbedaan perlakuan, padahal keduanya diperlakukan IDENTIK
                    // di seluruh sistem — termasuk oleh GenerateJadwalBulanan,
                    // GenerateJadwalRotasi, dan RekapHarian.
                    //
                    // Kebijakan RS: saat cuti bersama karyawan TETAP MASUK; yang
                    // ingin libur harus mengajukan cuti seperti hari biasa.
                    // Artinya mendaftarkan cuti bersama di sini justru bikin
                    // generator menandai semua karyawan umum libur — lihat
                    // peringatan di bawah. Penyesuaian generator belum
                    // dikerjakan, lihat todo.md.
                    ->helperText(new HtmlString(
                        '<b>Penanda saja — belum memengaruhi apa pun.</b> Kebijakan RS: saat cuti bersama '
                        .'karyawan tetap masuk, yang ingin libur mengajukan cuti biasa. Tapi sistem masih '
                        .'memperlakukan baris ini <b>sama seperti libur nasional</b>: generator jadwal akan '
                        .'menandai karyawan umum libur, dan rekap harian tidak menghitungnya alpha. '
                        .'Sampai itu diperbaiki, sebaiknya cuti bersama <b>jangan</b> didaftarkan di sini.'
                    )),
            ]);
    }
}
