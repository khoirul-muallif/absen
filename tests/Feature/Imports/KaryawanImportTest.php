<?php

// File baru — fase 82 (import Excel) dirilis tanpa test sama sekali, padahal
// ini fitur paling berkonsekuensi di panel: satu klik membuat 150 karyawan.
//
// collection() dipanggil langsung dengan Collection buatan, bukan lewat file
// .xlsx. Yang perlu dikunci adalah aturan validasinya; membaca file biner di
// tiap test bikin suite lambat dan fixture-nya tidak bisa dibaca saat review.

use App\Imports\KaryawanImport;
use App\Models\Instansi;
use App\Models\Karyawan;
use Illuminate\Support\Collection;
use App\Exports\KaryawanTemplateExport;
use App\Models\PolaRotasi;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
});

/** Satu baris template dengan nilai wajib terisi; timpa seperlunya. */
function barisImport(array $ubah = []): Collection
{
    return collect(array_merge([
        'nip'               => 'NIP-001',
        'nama'              => 'Budi Santoso',
        'username'          => null,
        'email'             => null,
        'nomor_telepon'     => null,
        'status_pegawai'    => 'tetap',
        'tipe_jadwal'       => 'umum',
        'unit_kerja'        => null,
        'jabatan'           => null,
        'tanggal_bergabung' => null,
    ], $ubah));
}

function jalankanImport(int $instansiId, array $baris, bool $dryRun = true): KaryawanImport
{
    $import = new KaryawanImport($instansiId, dryRun: $dryRun);
    $import->collection(collect($baris));

    return $import;
}

// ── Dry-run tidak menulis ────────────────────────────────────────────────

it('dry-run tidak menulis apa pun ke database', function () {
    $import = jalankanImport($this->instansi->id, [barisImport()]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and(Karyawan::count())->toBe(0);
});

it('commit menulis baris yang siap', function () {
    jalankanImport($this->instansi->id, [barisImport()], dryRun: false);

    expect(Karyawan::where('nip', 'NIP-001')->exists())->toBeTrue();
});

// ── Duplikat DI DALAM file itu sendiri ───────────────────────────────────
//
// Ini alasan utama importer memakai ToCollection, bukan ToModel: baris yang
// bentrok dengan baris lain di file yang sama cuma bisa ketahuan kalau semua
// baris terlihat sekaligus. Tanpa ini, baris kedua baru meledak di INSERT
// dengan SQL mentah, bukan laporan per-baris.

it('menolak NIP yang kembar di dalam file yang sama', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-001', 'nama' => 'Budi']),
        barisImport(['nip' => 'NIP-001', 'nama' => 'Siti']),
    ]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->ditolak)->toHaveCount(1)
        ->and($import->hasil->ditolak[0]['baris'])->toBe(3)
        ->and($import->hasil->ditolak[0]['pesan'][0])->toContain('kembar');
});

it('menolak email yang kembar di dalam file yang sama', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-001', 'email' => 'sama@rs.test']),
        barisImport(['nip' => 'NIP-002', 'email' => 'sama@rs.test']),
    ]);

    expect($import->hasil->ditolak)->toHaveCount(1);
});

it('TIDAK menganggap dua baris tanpa email sebagai duplikat', function () {
    // Gampang terlewat karena pengecekan ketiga kolom biasanya ditulis
    // sekaligus. Kalau salah, 110 karyawan tanpa email ditolak semua.
    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-001', 'email' => null]),
        barisImport(['nip' => 'NIP-002', 'email' => null]),
    ]);

    expect($import->hasil->siap)->toHaveCount(2)
        ->and($import->hasil->ditolak)->toBeEmpty();
});

it('menolak NIP yang sudah ada di database', function () {
    Karyawan::factory()->create(['nip' => 'NIP-001']);

    $import = jalankanImport($this->instansi->id, [barisImport(['nip' => 'NIP-001'])]);

    expect($import->hasil->ditolak)->toHaveCount(1)
        ->and($import->hasil->ditolak[0]['pesan'][0])->toContain('sudah dipakai');
});

// ── Username batch ───────────────────────────────────────────────────────

it('memberi username berbeda untuk dua nama yang sama dalam satu file', function () {
    // generateUsername() cuma mengecek DB, jadi tanpa daftar "dipesan" di
    // importer keduanya dapat "agussantoso" dan yang kedua meledak di INSERT.
    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-001', 'nama' => 'Agus Santoso']),
        barisImport(['nip' => 'NIP-002', 'nama' => 'Agus Santoso']),
    ], dryRun: false);

    expect($import->hasil->siap)->toHaveCount(2)
        ->and(Karyawan::pluck('username')->unique())->toHaveCount(2);
});

// ── Aturan yang disalin dari KaryawanForm ────────────────────────────────

it('mewajibkan unit_kerja untuk karyawan rotasi', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => null]),
    ]);

    expect($import->hasil->ditolak[0]['pesan'])
        ->toContain('Unit kerja wajib diisi untuk karyawan rotasi.');
});

it('tidak mewajibkan unit_kerja untuk karyawan umum', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'umum', 'unit_kerja' => null]),
    ]);

    expect($import->hasil->siap)->toHaveCount(1);
});

it('menerima enum huruf besar', function () {
    // Dropdown template membatasi pilihan, TAPI paste melewati data
    // validation tanpa peringatan apa pun.
    $import = jalankanImport($this->instansi->id, [
        barisImport(['status_pegawai' => 'Tetap', 'tipe_jadwal' => 'Umum']),
    ]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->siap[0]['data']['status_pegawai'])->toBe('tetap');
});

it('menolak status_pegawai yang tidak dikenal', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['status_pegawai' => 'honorer']),
    ]);

    expect($import->hasil->ditolak)->toHaveCount(1);
});

it('memperingatkan unit kerja baru tanpa memblokir', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => 'Unit Yang Belum Ada']),
    ]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->peringatan)->toHaveCount(1);
});

// ── Parser tanggal ───────────────────────────────────────────────────────

it('membaca beberapa format tanggal bergabung', function (string $masukan, string $harapan) {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tanggal_bergabung' => $masukan]),
    ]);

    expect($import->hasil->siap[0]['data']['tanggal_bergabung'])->toBe($harapan);
})->with([
    ['2024-12-12', '2024-12-12'],
    ['12 Desember 2024', '2024-12-12'],
    ['12 desember 2024', '2024-12-12'],
    ['12 des 2024', '2024-12-12'],
    // Format berangka dibaca HARI dulu — konteksnya Indonesia.
    ['12/01/2024', '2024-01-12'],
    ['01-12-2024', '2024-12-01'],
]);

it('menolak tanggal yang tidak bisa dibaca', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tanggal_bergabung' => 'kapan-kapan']),
    ]);

    expect($import->hasil->ditolak[0]['pesan'][0])->toContain('tidak bisa dibaca');
});

it('menolak tanggal yang tidak ada alih-alih menggesernya', function () {
    // Carbon::create(2024, 2, 31) diam-diam jadi 2 Maret kalau tidak dicek.
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tanggal_bergabung' => '31 Februari 2024']),
    ]);

    expect($import->hasil->ditolak)->toHaveCount(1);
});

it('menolak tanggal bergabung di masa depan', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tanggal_bergabung' => now()->addYear()->format('Y-m-d')]),
    ]);

    expect($import->hasil->ditolak[0]['pesan'][0])->toContain('masa depan');
});

// ── Penjaga dua-sheet ────────────────────────────────────────────────────

it('hanya memetakan sheet data, bukan sheet lain', function () {
    // Pengganti test lama yang menguji penjaga $sudahDiproses. Penjaga
    // itu berarti "sheet pertama adalah sheet data" — asumsi yang gagal
    // kalau admin mengirim file dengan urutan sheet terbalik.
    //
    // Sekarang maatwebsite yang memilih sheet lewat nama, jadi
    // collection() tidak akan pernah dipanggil untuk "Petunjuk" dan
    // tidak perlu penjaga sama sekali.
    $import = new KaryawanImport($this->instansi->id, dryRun: false);

    expect($import->sheets())
        ->toHaveKey(KaryawanTemplateExport::SHEET_DATA)
        ->toHaveCount(1);
});

// ── Baris kosong & nilai default ─────────────────────────────────────────

it('melewati baris yang seluruhnya kosong', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(),
        collect(array_fill_keys([
            'nip', 'nama', 'username', 'email', 'nomor_telepon',
            'status_pegawai', 'tipe_jadwal', 'unit_kerja', 'jabatan',
            'tanggal_bergabung',
        ], null)),
    ]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->ditolak)->toBeEmpty();
});

it('mengisi instansi, role, is_active dan password default', function () {
    jalankanImport($this->instansi->id, [barisImport()], dryRun: false);

    $karyawan = Karyawan::where('nip', 'NIP-001')->first();

    expect($karyawan->instansi_id)->toBe($this->instansi->id)
        ->and($karyawan->role)->toBe('karyawan')
        ->and($karyawan->is_active)->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check(
            KaryawanImport::PASSWORD_DEFAULT,
            $karyawan->password,
        ))->toBeTrue();
});

it('nomor baris di laporan cocok dengan nomor baris Excel', function () {
    // Baris 1 adalah header, jadi baris data pertama adalah baris 2.
    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-001']),
        barisImport(['nip' => null, 'nama' => 'Tanpa NIP']),
    ]);

    expect($import->hasil->siap[0]['baris'])->toBe(2)
        ->and($import->hasil->ditolak[0]['baris'])->toBe(3);
});

// ── Penyeragaman ejaan unit kerja (27 Nov) ───────────────────────────────
//
// Sebelumnya "igd" dan "IGD" jadi dua unit berbeda, dan karyawan rotasi
// yang unitnya "igd" tidak bisa di-assign pola "IGD" sama sekali (fase 33
// mencocokkan persis). Importer sekarang memakai ejaan yang sudah ada.

it('menyeragamkan ejaan unit kerja mengikuti yang sudah ada', function () {
    PolaRotasi::factory()->create(['unit_kerja' => 'IGD']);

    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => 'igd']),
    ], dryRun: false);

    expect(Karyawan::where('nip', 'NIP-001')->first()->unit_kerja)->toBe('IGD');
});

it('memberi tahu admin bahwa ejaannya diubah', function () {
    // Data yang diketik admin DIUBAH, jadi tidak boleh diam-diam.
    PolaRotasi::factory()->create(['unit_kerja' => 'IGD']);

    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => 'igd']),
    ]);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->peringatan)->toHaveCount(1)
        ->and($import->hasil->peringatan[0]['pesan'])->toContain('diseragamkan');
});

it('tidak memperingatkan kalau ejaannya memang sudah sama', function () {
    PolaRotasi::factory()->create(['unit_kerja' => 'IGD']);

    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => 'IGD']),
    ]);

    expect($import->hasil->peringatan)->toBeEmpty();
});

it('ejaan dari PolaRotasi menang atas ejaan karyawan', function () {
    // Fase 33 mencocokkan unit_kerja karyawan dengan unit POLA secara
    // persis, jadi ejaan pola satu-satunya yang membuat karyawan rotasi
    // bisa di-assign. Memilih ejaan karyawan justru menghasilkan unit
    // yang tidak cocok dengan pola mana pun.
    Karyawan::factory()->count(3)->create(['unit_kerja' => 'Igd']);
    PolaRotasi::factory()->create(['unit_kerja' => 'IGD']);

    $import = jalankanImport($this->instansi->id, [
        barisImport(['nip' => 'NIP-900', 'tipe_jadwal' => 'rotasi', 'unit_kerja' => 'igd']),
    ], dryRun: false);

    expect(Karyawan::where('nip', 'NIP-900')->first()->unit_kerja)->toBe('IGD');
});

it('unit yang benar-benar baru tetap masuk apa adanya', function () {
    $import = jalankanImport($this->instansi->id, [
        barisImport(['tipe_jadwal' => 'rotasi', 'unit_kerja' => 'Unit Baru']),
    ], dryRun: false);

    expect(Karyawan::where('nip', 'NIP-001')->first()->unit_kerja)->toBe('Unit Baru')
        ->and($import->hasil->peringatan[0]['pesan'])->toContain('belum pernah dipakai');
});
