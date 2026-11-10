<?php

// Fase 83. Yang diuji PerencanaAssignment, bukan modal Filament — di situlah
// seluruh keputusannya berada, dan dia murni fungsi: masuk Collection, keluar
// rencana. Pratinjau dan eksekusi dua-duanya memanggil kelas ini, jadi
// menguncinya di sini mengunci keduanya sekaligus.

use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\KaryawanShift;
use App\Models\PolaRotasi;
use App\Models\Shift;
use App\Support\PerencanaAssignment;
use Carbon\Carbon;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
});

/** Pola 4 langkah: pagi, siang, malam, libur — bentuk yang dipakai IGD. */
function polaEmpatLangkah(int $instansiId, int $shiftId, string $unit = 'IGD'): PolaRotasi
{
    return PolaRotasi::factory()->create([
        'instansi_id' => $instansiId,
        'unit_kerja'  => $unit,
        'langkah'     => [
            ['shift_id' => $shiftId, 'libur' => false],
            ['shift_id' => $shiftId, 'libur' => false],
            ['shift_id' => $shiftId, 'libur' => false],
            ['shift_id' => null,     'libur' => true],
        ],
    ]);
}

// ── Assignment shift umum ────────────────────────────────────────────────

it('merencanakan assignment shift untuk karyawan umum', function () {
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->siap)->toHaveCount(1)
        ->and($rencana->siap[0]['anchor'])->toBe('2026-11-01');
});

it('menolak karyawan rotasi di assignment shift', function () {
    // Bulk action memilih baris apa adanya — admin bisa mencentang campuran.
    // Kalau diloloskan, hasilnya anomali yang guard fase 18 anggap mustahil.
    $karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->siap)->toBeEmpty()
        ->and($rencana->ditolak[0]['alasan'])->toContain('rotasi');
});

it('menolak karyawan dari instansi lain', function () {
    // Dua instansi bisa sama-sama punya shift bernama "Pagi".
    $lain = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $lain->id]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->ditolak[0]['alasan'])->toContain('instansi lain');
});

it('menolak karyawan yang sudah punya penugasan shift aktif', function () {
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);

    KaryawanShift::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $this->shift->id,
        'tanggal_berlaku'  => '2026-01-01',
        'tanggal_berakhir' => null,
    ]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->siap)->toBeEmpty()
        ->and($rencana->ditolak[0]['alasan'])->toContain('Lepas dulu');
});

it('memakai tanggal_bergabung kalau lebih lambat dari tanggal yang dipilih', function () {
    // Assignment tidak boleh berlaku sebelum orangnya bekerja.
    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id'       => $this->instansi->id,
        'tanggal_bergabung' => '2026-11-20',
    ]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->siap[0]['anchor'])->toBe('2026-11-20');
});

it('memakai tanggal yang dipilih kalau tanggal_bergabung lebih awal', function () {
    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id'       => $this->instansi->id,
        'tanggal_bergabung' => '2024-01-15',
    ]);

    $rencana = PerencanaAssignment::umum(
        collect([$karyawan]),
        $this->shift,
        Carbon::parse('2026-11-01'),
    );

    expect($rencana->siap[0]['anchor'])->toBe('2026-11-01');
});

// ── Stagger anchor — inti fase 83 ────────────────────────────────────────

it('memundurkan anchor satu hari per karyawan', function () {
    // ARAH INI YANG PALING RAWAN. posisiSiklusPada() menghitung
    // diffInDays(tanggal_mulai -> tanggal), jadi anchor lebih awal = posisi
    // lebih maju. Kalau arahnya dibalik jadi addDays, berlakuPada() menolak
    // orang ke-n selama n hari pertama dan generator melewatinya diam-diam —
    // sebaran di pratinjau tetap terlihat merata.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $karyawans = collect([1, 2, 3])->map(fn () => Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]));

    $rencana = PerencanaAssignment::rotasi($karyawans, $pola, Carbon::parse('2026-11-10'));

    expect(collect($rencana->siap)->pluck('anchor')->all())
        ->toBe(['2026-11-10', '2026-11-09', '2026-11-08']);
});

it('mengulang stagger setelah satu siklus penuh', function () {
    // Pola 4 langkah, 5 orang: orang ke-5 kembali ke posisi 0.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $karyawans = collect(range(1, 5))->map(fn () => Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]));

    $rencana = PerencanaAssignment::rotasi($karyawans, $pola, Carbon::parse('2026-11-10'));

    expect(collect($rencana->siap)->pluck('posisi')->all())->toBe([0, 1, 2, 3, 0]);
});

it('anchor hasil stagger menghasilkan posisi siklus yang benar', function () {
    // Mengunci lingkarannya: anchor yang dihitung perencana, dibaca balik
    // oleh posisiSiklusPada() milik model, harus menghasilkan posisi yang
    // dijanjikan pratinjau. Dua perhitungan terpisah yang harus sepakat.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);
    $tanggal = Carbon::parse('2026-11-10');

    $karyawans = collect(range(1, 4))->map(fn () => Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]));

    $rencana = PerencanaAssignment::rotasi($karyawans, $pola, $tanggal);

    foreach ($rencana->siap as $baris) {
        $assignment = new KaryawanPolaRotasi([
            'karyawan_id'    => $baris['karyawan']->id,
            'pola_rotasi_id' => $pola->id,
            'tanggal_mulai'  => $baris['anchor'],
        ]);
        $assignment->setRelation('polaRotasi', $pola);

        expect($assignment->posisiSiklusPada($tanggal))->toBe($baris['posisi']);
    }
});

it('menyebar karyawan merata ke seluruh langkah siklus', function () {
    // Yang sebenarnya ingin dipastikan dari seluruh stagger: tiap langkah
    // terisi, jadi shift malam tidak pernah kosong.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $karyawans = collect(range(1, 12))->map(fn () => Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]));

    $rencana = PerencanaAssignment::rotasi($karyawans, $pola, Carbon::parse('2026-11-10'));

    expect($rencana->distribusiPosisi())->toBe([0 => 3, 1 => 3, 2 => 3, 3 => 3]);
});

it('anchor tidak pernah jatuh setelah tanggal yang dipilih', function () {
    // berlakuPada() menolak tanggal SEBELUM tanggal_mulai. Karena stagger
    // memundurkan, semua anchor jatuh pada atau sebelum tanggal pilihan —
    // tidak ada yang tertolak generator. Arah yang kebetulan aman, dan
    // test ini yang menjaganya tetap begitu.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);
    $tanggal = Carbon::parse('2026-11-10');

    $karyawans = collect(range(1, 8))->map(fn () => Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]));

    $rencana = PerencanaAssignment::rotasi($karyawans, $pola, $tanggal);

    foreach ($rencana->siap as $baris) {
        expect(Carbon::parse($baris['anchor'])->lte($tanggal))->toBeTrue();
    }
});

// ── Penolakan di assignment rotasi ───────────────────────────────────────

it('menolak karyawan yang unit kerjanya beda kapitalisasi', function () {
    // Fase 33 mencocokkan unit_kerja SAMA PERSIS. "igd" bukan "IGD".
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id, 'IGD');

    $karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'igd',
    ]);

    $rencana = PerencanaAssignment::rotasi(collect([$karyawan]), $pola, Carbon::parse('2026-11-10'));

    expect($rencana->siap)->toBeEmpty()
        ->and($rencana->ditolak[0]['alasan'])->toContain('igd')
        ->and($rencana->ditolak[0]['alasan'])->toContain('IGD');
});

it('menolak karyawan umum di assignment rotasi', function () {
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    $rencana = PerencanaAssignment::rotasi(collect([$karyawan]), $pola, Carbon::parse('2026-11-10'));

    expect($rencana->ditolak[0]['alasan'])->toContain('umum');
});

it('menolak karyawan yang sudah punya assignment pola aktif', function () {
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'pola_rotasi_id'   => $pola->id,
        'tanggal_mulai'    => '2026-01-01',
        'tanggal_berakhir' => null,
    ]);

    $rencana = PerencanaAssignment::rotasi(collect([$karyawan]), $pola, Carbon::parse('2026-11-10'));

    expect($rencana->siap)->toBeEmpty()
        ->and($rencana->ditolak[0]['alasan'])->toContain('Lepas dulu');
});

it('menolak pola yang tidak punya langkah sama sekali', function () {
    // minItems(1) cuma berlaku di form; seeder dan insert langsung bisa
    // membuat pola kosong. Tanpa guard, modulo nol melempar
    // DivisionByZeroError di tengah pratinjau.
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
        'langkah'     => [],
    ]);

    $karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    $rencana = PerencanaAssignment::rotasi(collect([$karyawan]), $pola, Carbon::parse('2026-11-10'));

    expect($rencana->siap)->toBeEmpty()
        ->and($rencana->ditolak[0]['alasan'])->toContain('tidak punya langkah');
});

it('karyawan yang ditolak tidak menggeser posisi karyawan berikutnya', function () {
    // $urutan hanya naik untuk yang lolos. Kalau ikut naik untuk yang
    // ditolak, akan ada posisi yang bolong dan sebarannya pincang.
    $pola = polaEmpatLangkah($this->instansi->id, $this->shift->id);

    $valid1 = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id, 'unit_kerja' => 'IGD',
    ]);
    $salahUnit = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id, 'unit_kerja' => 'ICU',
    ]);
    $valid2 = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id, 'unit_kerja' => 'IGD',
    ]);

    $rencana = PerencanaAssignment::rotasi(
        collect([$valid1, $salahUnit, $valid2]),
        $pola,
        Carbon::parse('2026-11-10'),
    );

    expect(collect($rencana->siap)->pluck('posisi')->all())->toBe([0, 1])
        ->and($rencana->ditolak)->toHaveCount(1);
});
