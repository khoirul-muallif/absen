<?php

namespace App\Filament\Resources\QrInstansis\Schemas;

use App\Models\QrInstansi;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class QrInstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('QR Code Instansi')
                    ->description('QR statis yang ditempel di lokasi instansi untuk scan absensi')
                    ->icon('heroicon-o-qr-code')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        TextInput::make('kode_qr')
                            ->label('Kode QR')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn () => QrInstansi::generateKode())
                            // Begitu QR sudah pernah dipakai absen, berarti
                            // fisiknya sudah dicetak dan ditempel. Mengubah
                            // kodenya membuat semua QR yang terpasang jadi tidak
                            // valid — karyawan tidak bisa absen dan tidak ada
                            // yang tahu penyebabnya.
                            ->disabled(fn (?QrInstansi $record): bool => $record !== null && ! $record->kodeMasihBisaDiubah())
                            ->dehydrated()
                            // Helper text lama: "Kosongkan dan simpan untuk
                            // generate otomatis" — mustahil, field ini required
                            // sehingga mengosongkannya justru gagal validasi.
                            // Kodenya memang sudah ter-generate otomatis sebagai
                            // default, jadi kalimatnya keliru arah.
                            ->helperText(fn (?QrInstansi $record): string => $record !== null && ! $record->kodeMasihBisaDiubah()
                                ? 'Terkunci: QR ini sudah pernah dipakai absen, jadi fisiknya sudah tercetak dan tertempel. Kalau perlu kode baru, buat QR baru lalu nonaktifkan yang ini — riwayat absensinya tetap bisa ditelusuri.'
                                : 'Sudah terisi otomatis. Kode inilah yang di-encode ke gambar QR dan dipindai aplikasi karyawan — ubah hanya sebelum QR dicetak.')
                            ->columnSpanFull(),

                        DateTimePicker::make('expired_at')
                            ->label('Kadaluarsa')
                            ->displayFormat('d M Y H:i')
                            ->live(onBlur: true)
                            ->helperText('Kosongkan untuk QR statis permanen (tidak kadaluarsa)')
                            ->nullable(),

                        // Item todo: "potensi admin gak sadar QR permanen kalau
                        // field dikosongkan begitu saja". Helper text di atas
                        // memang sudah menyebutnya, tapi mudah terlewat karena
                        // keadaan "kosong" tidak terlihat sebagai pilihan —
                        // kelihatannya cuma field yang belum diisi.
                        Placeholder::make('arti_kadaluarsa')
                            ->label('Artinya')
                            ->live()
                            ->content(function (Get $get): string {
                                $expired = $get('expired_at');

                                if (blank($expired)) {
                                    return '♾️ PERMANEN — QR ini berlaku selamanya sampai dinonaktifkan manual. Ini pilihan yang benar untuk QR yang ditempel tetap di pintu masuk.';
                                }

                                $tanggal = Carbon::parse($expired);

                                if ($tanggal->isPast()) {
                                    return '⚠️ Tanggal ini sudah LEWAT — QR langsung tidak bisa dipakai absen begitu disimpan.';
                                }

                                return sprintf(
                                    'QR berhenti berlaku pada %s (%s lagi). Setelah itu pemindaian ditolak walau statusnya masih aktif.',
                                    $tanggal->format('d M Y H:i'),
                                    $tanggal->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('QR Aktif')
                            ->default(true)
                            ->live()
                            ->helperText('QR yang tidak aktif tidak bisa digunakan untuk absen. Ini cara yang benar untuk memensiunkan QR lama — menghapusnya akan ditolak kalau sudah pernah dipakai absen.'),
                    ]),
            ]);
    }
}
