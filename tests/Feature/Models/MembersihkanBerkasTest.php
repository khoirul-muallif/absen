<?php

use App\Models\Instansi;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('menghapus logo lama saat instansi mengganti logonya', function () {
    Storage::disk('public')->put('logo-instansi/lama.png', 'isi');

    $instansi = Instansi::factory()->create(['logo' => 'logo-instansi/lama.png']);

    Storage::disk('public')->put('logo-instansi/baru.png', 'isi');
    $instansi->update(['logo' => 'logo-instansi/baru.png']);

    Storage::disk('public')->assertMissing('logo-instansi/lama.png');
    Storage::disk('public')->assertExists('logo-instansi/baru.png');
});

it('tidak menghapus apa pun kalau kolom berkasnya tidak berubah', function () {
    Storage::disk('public')->put('logo-instansi/tetap.png', 'isi');

    $instansi = Instansi::factory()->create(['logo' => 'logo-instansi/tetap.png']);
    $instansi->update(['nama' => 'Nama Baru']);

    Storage::disk('public')->assertExists('logo-instansi/tetap.png');
});

it('menghapus logo saat instansinya dihapus', function () {
    Storage::disk('public')->put('logo-instansi/ikut.png', 'isi');

    $instansi = Instansi::factory()->create(['logo' => 'logo-instansi/ikut.png']);
    $instansi->delete();

    Storage::disk('public')->assertMissing('logo-instansi/ikut.png');
});

it('menghapus foto profil dan foto wajah lama sekaligus', function () {
    // Dua kolom berkas di satu model, diganti dalam satu update —
    // memastikan loopnya tidak berhenti di kolom pertama.
    Storage::disk('public')->put('foto-profil/lama.jpg', 'isi');
    Storage::disk('public')->put('foto-wajah/lama.jpg', 'isi');

    $karyawan = Karyawan::factory()->create([
        'foto_profil' => 'foto-profil/lama.jpg',
        'foto_wajah'  => 'foto-wajah/lama.jpg',
    ]);

    $karyawan->update([
        'foto_profil' => 'foto-profil/baru.jpg',
        'foto_wajah'  => 'foto-wajah/baru.jpg',
    ]);

    Storage::disk('public')->assertMissing('foto-profil/lama.jpg');
    Storage::disk('public')->assertMissing('foto-wajah/lama.jpg');
});

it('menghapus hanya kolom yang berubah, bukan semua berkas model', function () {
    // Kalau loopnya tidak mengecek wasChanged() per kolom, mengganti
    // foto profil saja akan ikut melenyapkan foto wajah yang masih dipakai.
    Storage::disk('public')->put('foto-profil/lama.jpg', 'isi');
    Storage::disk('public')->put('foto-wajah/tetap.jpg', 'isi');

    $karyawan = Karyawan::factory()->create([
        'foto_profil' => 'foto-profil/lama.jpg',
        'foto_wajah'  => 'foto-wajah/tetap.jpg',
    ]);

    $karyawan->update(['foto_profil' => 'foto-profil/baru.jpg']);

    Storage::disk('public')->assertMissing('foto-profil/lama.jpg');
    Storage::disk('public')->assertExists('foto-wajah/tetap.jpg');
});

it('tidak error kalau kolom berkasnya null', function () {
    $instansi = Instansi::factory()->create(['logo' => null]);
    $instansi->update(['logo' => 'logo-instansi/pertama.png']);

    expect($instansi->fresh()->logo)->toBe('logo-instansi/pertama.png');
});

it('tidak error kalau berkasnya sudah tidak ada di disk', function () {
    // Dihapus manual, atau sisa dari sebelum trait ini ada.
    $instansi = Instansi::factory()->create(['logo' => 'logo-instansi/hilang.png']);
    $instansi->update(['logo' => 'logo-instansi/baru.png']);

    expect($instansi->fresh()->logo)->toBe('logo-instansi/baru.png');
});
