<?php

namespace App\Imports;

use App\Models\Karyawan;
use App\Models\PolaRotasi;
use App\Support\HasilImportKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import karyawan massal (fase 82).
 *
 * ToCollection, bukan ToModel — validasi antar-baris (duplikat di dalam
 * file itu sendiri, dan username yang "dipesan" selama proses) harus
 * selesai SEBELUM apa pun ditulis. ToModel memproses baris satu per satu
 * tanpa tahu baris lain, jadi tidak bisa menangkap keduanya.
 *
 * Dua mode dalam satu kelas:
 * - dry-run: kumpulkan laporan, tidak menulis apa pun
 * - commit: seluruhnya dalam satu DB::transaction()
 *
 * Satu kelas, bukan dua, supaya aturan validasinya mustahil menyimpang —
 * kalau dry-run bilang 150 baris siap, commit harus menerima 150 baris
 * yang sama persis.
 */
class KaryawanImport implements ToCollection, WithHeadingRow
{
    public const PASSWORD_DEFAULT = 'password';

    protected const KOLOM = [
        'nip', 'nama', 'username', 'email', 'nomor_telepon',
        'status_pegawai', 'tipe_jadwal', 'unit_kerja', 'jabatan',
        'tanggal_bergabung',
    ];

    protected const STATUS_PEGAWAI = ['tetap', 'kontrak', 'orientasi', 'magang'];

    public HasilImportKaryawan $hasil;

    /** NIP/username/email yang sudah dipakai baris sebelumnya dalam file ini. */
    protected array $nipDipesan = [];

    protected array $usernameDipesan = [];

    protected array $emailDipesan = [];

    protected array $unitDikenal = [];

    public function __construct(
        protected int $instansiId,
        protected bool $dryRun = true,
    ) {
        $this->hasil = new HasilImportKaryawan();
    }

        protected const BULAN_ID = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
        'jul' => 7, 'agu' => 8, 'ags' => 8, 'sep' => 9, 'okt' => 10,
        'nov' => 11, 'des' => 12,
    ];

    /**
     * Admin RS menulis tanggal dengan cara yang wajar baginya — "12 Desember
     * 2024", bukan "2024-12-15". Menolak itu dan menyuruh dia mengetik ulang
     * 150 tanggal dalam format asing membuat fitur ini kalah berguna
     * dibanding input manual.
     *
     * PENTING: format berangka diperlakukan HARI DULU (12/01/2024 = 12
     * Januari), bukan bulan dulu. Konteksnya Indonesia. Ini asumsi yang
     * ditulis eksplisit, bukan dibiarkan diam — ambiguitasnya nyata dan
     * salah tebak menghasilkan tanggal yang terlihat masuk akal.
     *
     * Null = tidak bisa dibaca. Kosong ditangani pemanggil.
     */
    protected function bacaTanggal(string $nilai): ?Carbon
    {
        $nilai = trim($nilai);

        // Serial number Excel — muncul kalau admin menghapus format TEXT
        // dan Excel mengubah selnya jadi tanggal sungguhan.
        if (is_numeric($nilai) && (float) $nilai > 1 && (float) $nilai < 100000) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $nilai)
                );
            } catch (\Throwable) {
                return null;
            }
        }

        // Nama bulan Indonesia: "12 Desember 2024", "12 des 2024"
        if (preg_match('/^(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})$/', $nilai, $cocok)) {
            $bulan = self::BULAN_ID[strtolower($cocok[2])] ?? null;

            if ($bulan === null) {
                return null;
            }

            return $this->buatTanggal((int) $cocok[3], $bulan, (int) $cocok[1]);
        }

        // Berangka: 12/01/2024, 12-01-2024, 12.01.2024 — HARI DULU
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $nilai, $cocok)) {
            return $this->buatTanggal((int) $cocok[3], (int) $cocok[2], (int) $cocok[1]);
        }

        // ISO: 2024-12-12
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $nilai, $cocok)) {
            return $this->buatTanggal((int) $cocok[1], (int) $cocok[2], (int) $cocok[3]);
        }

        return null;
    }

    /** Menolak tanggal yang tidak ada (31 Februari) alih-alih menggesernya. */
    protected function buatTanggal(int $tahun, int $bulan, int $hari): ?Carbon
    {
        if (! checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return Carbon::create($tahun, $bulan, $hari)->startOfDay();
    }

    /** @see collection() */
    protected bool $sudahDiproses = false;

    public function collection(Collection $rows): void
    {
        // maatwebsite memanggil collection() SEKALI PER SHEET, dan template
        // punya sheet "Petunjuk". Tanpa penjaga ini, simpan() jalan dua kali
        // dan 5 baris yang sama di-insert ulang — duplicate NIP, dengan
        // batch pertama SUDAH ter-commit karena transaksinya terpisah.
        //
        // Sheet pertama adalah sheet data; itu asumsi yang dipegang di sini.
        if ($this->sudahDiproses) {
            return;
        }

        $this->sudahDiproses = true;

        $this->unitDikenal = $this->unitYangSudahAda();

        foreach ($rows as $index => $row) {
            // +2: baris 1 adalah header, dan index mulai dari 0. Angka ini
            // dipakai di laporan, jadi harus cocok dengan nomor baris yang
            // dilihat admin di Excel.
            $nomorBaris = $index + 2;
            $data = $this->bersihkan($row->toArray());

            if ($this->barisKosong($data)) {
                continue;
            }

            $pesan = $this->periksa($data, $nomorBaris);

            if ($pesan !== []) {
                $this->hasil->tambahDitolak($nomorBaris, $data['nama'] ?? null, $pesan);
                continue;
            }

            $siap = $this->siapkan($data);

            $this->pesan($siap);
            $this->hasil->tambahSiap($nomorBaris, $siap);
        }

        if (! $this->dryRun && ! $this->hasil->tidakAdaYangBisaDiimpor()) {
            $this->simpan();
        }
    }

    /**
     * Semua nilai jadi string yang sudah di-trim, kosong jadi null.
     * Enum di-lowercase: dropdown template memang membatasi pilihan, TAPI
     * paste melewati data validation tanpa peringatan apa pun — jadi
     * "Tetap" hasil tempel dari file lain tetap harus diterima.
     */
    protected function bersihkan(array $row): array
    {
        $data = [];

        foreach (self::KOLOM as $kolom) {
            $nilai = $row[$kolom] ?? null;
            $nilai = is_string($nilai) ? trim($nilai) : $nilai;

            $data[$kolom] = ($nilai === null || $nilai === '') ? null : (string) $nilai;
        }

        foreach (['status_pegawai', 'tipe_jadwal'] as $kolom) {
            if ($data[$kolom] !== null) {
                $data[$kolom] = strtolower($data[$kolom]);
            }
        }

        return $data;
    }

    protected function barisKosong(array $data): bool
    {
        return collect($data)->every(fn ($nilai) => $nilai === null);
    }

    /** @return array<string> daftar pesan kesalahan; kosong berarti lolos */
    protected function periksa(array $data, int $nomorBaris): array
    {
        $validator = Validator::make($data, [
            'nip'               => ['required', 'string', 'max:50'],
            'nama'              => ['required', 'string', 'max:255'],
            'username'          => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:255'],
            'nomor_telepon'     => ['nullable', 'string', 'max:20'],
            'status_pegawai'    => ['required', 'in:' . implode(',', self::STATUS_PEGAWAI)],
            'tipe_jadwal'       => ['required', 'in:' . Karyawan::TIPE_UMUM . ',' . Karyawan::TIPE_ROTASI],
            'unit_kerja'        => ['nullable', 'string', 'max:255'],
            'jabatan'           => ['nullable', 'string', 'max:255'],
            // tanggal_bergabung TIDAK divalidasi Validator — formatnya
            // ditangani bacaTanggal(), yang menerima beberapa bentuk.
            'tanggal_bergabung' => ['nullable', 'string'],
        ], [], [
            'nip'               => 'NIP',
            'status_pegawai'    => 'status pegawai',
            'tipe_jadwal'       => 'tipe jadwal',
            'unit_kerja'        => 'unit kerja',
        ]);

        $pesan = $validator->errors()->all();

        if ($data['tanggal_bergabung'] !== null) {
            $tanggal = $this->bacaTanggal($data['tanggal_bergabung']);

            if ($tanggal === null) {
                $pesan[] = "Tanggal bergabung \"{$data['tanggal_bergabung']}\" tidak bisa dibaca. Contoh yang diterima: 2024-12-12, 12/12/2024, atau 12 Desember 2024.";
            } elseif ($tanggal->isFuture()) {
                $pesan[] = 'Tanggal bergabung tidak boleh di masa depan.';
            }
        }
        // Aturan yang disalin dari KaryawanForm — karyawan rotasi tanpa
        // unit_kerja tidak akan pernah cocok dengan pola mana pun, jadi
        // tidak bisa dijadwalkan sama sekali.
        if ($data['tipe_jadwal'] === Karyawan::TIPE_ROTASI && $data['unit_kerja'] === null) {
            $pesan[] = 'Unit kerja wajib diisi untuk karyawan rotasi.';
        }

        $pesan = array_merge($pesan, $this->periksaKeunikan($data));

        if ($pesan === [] && $data['unit_kerja'] !== null && ! in_array($data['unit_kerja'], $this->unitDikenal, true)) {
            // Tidak memblokir — unit baru itu sah. Tapi satu typo di import
            // tersalin ke puluhan baris sekaligus, dan karyawan rotasi yang
            // unitnya salah ketik tidak bisa di-assign pola apa pun (fase 33).
            $this->hasil->tambahPeringatan(
                $nomorBaris,
                "Unit kerja \"{$data['unit_kerja']}\" belum pernah dipakai. Pastikan bukan salah ketik.",
            );
        }

        return $pesan;
    }

    /**
     * Keunikan diperiksa terhadap DB DAN terhadap baris sebelumnya di file
     * ini. Nilai kosong dilewati: dua baris sama-sama tanpa email bukan
     * duplikat — ini yang gampang terlewat karena pengecekannya biasanya
     * ditulis sekali untuk ketiga kolom sekaligus.
     */
    protected function periksaKeunikan(array $data): array
    {
        $pesan = [];

        if ($data['nip'] !== null) {
            if (in_array($data['nip'], $this->nipDipesan, true)) {
                $pesan[] = "NIP \"{$data['nip']}\" kembar dengan baris lain di file ini.";
            } elseif (Karyawan::where('nip', $data['nip'])->exists()) {
                $pesan[] = "NIP \"{$data['nip']}\" sudah dipakai karyawan lain.";
            }
        }

        if ($data['username'] !== null) {
            if (in_array($data['username'], $this->usernameDipesan, true)) {
                $pesan[] = "Username \"{$data['username']}\" kembar dengan baris lain di file ini.";
            } elseif (Karyawan::where('username', $data['username'])->exists()) {
                $pesan[] = "Username \"{$data['username']}\" sudah dipakai karyawan lain.";
            }
        }

        if ($data['email'] !== null) {
            if (in_array($data['email'], $this->emailDipesan, true)) {
                $pesan[] = "Email \"{$data['email']}\" kembar dengan baris lain di file ini.";
            } elseif (Karyawan::where('email', $data['email'])->exists()) {
                $pesan[] = "Email \"{$data['email']}\" sudah dipakai karyawan lain.";
            }
        }

        return $pesan;
    }

    protected function siapkan(array $data): array
    {
        $username = $data['username'] ?? $this->buatUsername($data['nama']);

        return [
            'instansi_id'       => $this->instansiId,
            'nip'               => $data['nip'],
            'nama'              => $data['nama'],
            'username'          => $username,
            'email'             => $data['email'],
            'nomor_telepon'     => $data['nomor_telepon'],
            'password'          => self::PASSWORD_DEFAULT,
            'status_pegawai'    => $data['status_pegawai'],
            'role'              => 'karyawan',
            'tipe_jadwal'       => $data['tipe_jadwal'],
            'unit_kerja'        => $data['unit_kerja'],
            'jabatan'           => $data['jabatan'],
            'tanggal_bergabung' => $data['tanggal_bergabung']
                ? $this->bacaTanggal($data['tanggal_bergabung'])?->toDateString()
                : null,
            'is_active'         => true,
        ];
    }

    /**
     * Karyawan::generateUsername() cuma mengecek DB, jadi dalam satu import
     * dua "Agus Pranowo" sama-sama dapat "aguspranowo" dan yang kedua baru
     * meledak di INSERT — SQL mentah, bukan laporan per-baris.
     *
     * generateUsername() SENGAJA tidak diubah: dia sumber aturan tunggal
     * sejak fase 79 dan dipakai KaryawanForm; menambah parameter di situ
     * mengubah arti pemanggilan yang sudah ada. Penyesuaiannya di sini.
     */
    protected function buatUsername(string $nama): string
    {
        $kandidat = Karyawan::generateUsername($nama);

        if (! in_array($kandidat, $this->usernameDipesan, true)) {
            return $kandidat;
        }

        $dasar = rtrim($kandidat, '0123456789');
        $dasar = $dasar !== '' ? $dasar : 'karyawan';
        $urutan = 2;

        do {
            $baru = substr($dasar, 0, 30 - strlen((string) $urutan)) . $urutan;
            $urutan++;
        } while (
            in_array($baru, $this->usernameDipesan, true)
            || Karyawan::where('username', $baru)->exists()
        );

        return $baru;
    }

    protected function pesan(array $siap): void
    {
        $this->nipDipesan[] = $siap['nip'];
        $this->usernameDipesan[] = $siap['username'];

        if ($siap['email'] !== null) {
            $this->emailDipesan[] = $siap['email'];
        }
    }

    /**
     * Atomic. Gagal di baris 120 setelah 119 karyawan terlanjur masuk itu
     * keadaan yang sulit dibereskan admin — dan dia tidak punya terminal.
     */
    protected function simpan(): void
    {
        DB::transaction(function () {
            foreach ($this->hasil->siap as $baris) {
                Karyawan::create($baris['data']);
            }
        });

        $this->hasil->tersimpan = true;
    }

    /** Sumber yang sama dengan datalist di KaryawanForm. */
    protected function unitYangSudahAda(): array
    {
        return collect()
            ->merge(Karyawan::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
            ->merge(PolaRotasi::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
            ->map(fn ($u) => (string) $u)
            ->unique()
            ->values()
            ->all();
    }
}
