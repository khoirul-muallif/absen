<?php

use App\Models\Karyawan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Login pindah dari email ke username (keputusan 9 Nov 2026):
     * karyawan RS belum tentu punya atau ingat email kerjanya.
     *
     * Email TIDAK dibuang — tetap NOT NULL dan dipakai untuk notifikasi
     * mail (AbsenTerlambat punya toMail()) serta ditampilkan di layar
     * profil FE. Yang berubah cuma: email bukan lagi kredensial login.
     *
     * Tiga langkah dalam satu migration karena kolom tidak bisa langsung
     * NOT NULL — 156 baris yang sudah ada belum punya nilainya.
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('nip');
        });

        // Backfill dari nama. Pakai generateUsername() yang sama dengan
        // yang dipakai Filament & import, supaya aturannya cuma ada di
        // satu tempat.
        Karyawan::withoutEvents(function () {
            Karyawan::orderBy('id')->chunkById(100, function ($karyawans) {
                foreach ($karyawans as $karyawan) {
                    $karyawan->updateQuietly([
                        'username' => Karyawan::generateUsername($karyawan->nama, $karyawan->id),
                    ]);
                }
            });
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
