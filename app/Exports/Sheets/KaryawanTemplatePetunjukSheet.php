<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KaryawanTemplatePetunjukSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    public function __construct(protected array $unitKerjaTersedia = [])
    {
    }

    public function title(): string
    {
        return 'Petunjuk';
    }

    public function headings(): array
    {
        return ['Kolom', 'Wajib?', 'Penjelasan', 'Contoh'];
    }

    public function array(): array
    {
        $daftarUnit = $this->unitKerjaTersedia === []
            ? 'Belum ada unit terdaftar.'
            : 'Unit yang sudah ada: ' . implode(', ', $this->unitKerjaTersedia);

        return [
            ['nip', 'Wajib', 'Nomor induk pegawai. Harus unik — tidak boleh sama dengan karyawan yang sudah ada, dan tidak boleh kembar di dalam file ini.', 'NIP-00001'],
            ['nama', 'Wajib', 'Nama lengkap beserta gelar. Gelar otomatis dibuang saat membuat username.', 'Khoirul Muallif, A.Md.'],
            ['username', 'Boleh kosong', 'Dipakai karyawan untuk login. Kalau dikosongkan, dibuatkan otomatis dari nama (huruf kecil tanpa spasi dan gelar). Kalau bentrok, ditambahi angka urut.', 'khoirulmuallif'],
            ['email', 'Boleh kosong', 'Kosongkan kalau karyawan tidak punya email. Login memakai username, jadi mengosongkan ini TIDAK membuat karyawan terkunci. Kalau diisi, harus unik.', 'khoirul@rs.test'],
            ['nomor_telepon', 'Boleh kosong', 'Diawali +62 atau 0. Kolom ini diformat sebagai teks supaya nol di depan tidak hilang.', '081234567890'],
            ['status_pegawai', 'Wajib', 'Pilih dari dropdown. Nilai yang diterima: tetap, kontrak, orientasi, magang. Huruf kecil semua.', 'tetap'],
            ['tipe_jadwal', 'Wajib', 'Pilih dari dropdown. umum = jadwal tetap lewat penugasan shift. rotasi = jadwal harian dari pola siklus. TIDAK ditebak dari unit atau jabatan — harus diisi sadar.', 'umum'],
            ['unit_kerja', 'Wajib untuk rotasi', 'Harus sama PERSIS dengan unit pada Pola Rotasi, kalau tidak karyawan rotasi tidak bisa di-assign pola apa pun. Unit baru boleh, tapi akan ditandai peringatan saat pratinjau. ' . $daftarUnit, 'IGD'],
            ['jabatan', 'Boleh kosong', 'Posisi karyawan di unitnya.', 'Pelaksana'],
            ['tanggal_bergabung', 'Sebaiknya diisi', 'Diterima: 2024-12-12, 12/12/2024, atau 12 Desember 2024. Format berangka dibaca HARI dulu — 12/01/2024 berarti 12 Januari. Dipakai untuk menghitung masa kerja (hak cuti tahunan).', '12 Desember 2024'],
            ['', '', '', ''],
            ['CATATAN', '', 'Instansi TIDAK ada di template. Dipilih sekali di layar import, berlaku untuk semua baris.', ''],
            ['', '', 'Password tidak ada di template. Semua karyawan hasil import mendapat password default yang sama, dan admin bisa meresetnya per orang lewat tombol Reset Password.', ''],
            ['', '', 'Shift dan pola rotasi TIDAK diimpor. Setelah import selesai, assign lewat menu Shift Karyawan Umum / Shift Karyawan Rotasi. Karyawan tanpa assignment tidak bisa absen.', ''],
            ['', '', 'Jangan mengubah nama kolom di sheet "Data Karyawan" — importer mencocokkan berdasarkan nama itu.', ''],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 18, 'C' => 95, 'D' => 24];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('C1:C20')->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $sheet->getStyle('B2:B11')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
