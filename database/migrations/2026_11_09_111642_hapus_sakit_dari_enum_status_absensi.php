<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * KEPUTUSAN 9 Nov 2026: 'sakit' adalah dead value, bukan fitur yang
     * belum dibuat. Cuti sakit dicover modul Cuti (jenis "Cuti Sakit" +
     * lampiran surat dokter), yang saat approve menulis status 'cuti' —
     * bukan 'sakit'. Tidak ada satu pun controller/command yang pernah
     * menulis 'sakit'. Menutup Known Gap #5.
     *
     * Diverifikasi sebelum migrate: 0 baris berstatus 'sakit'.
     *
     * Pakai DB::statement, bukan Schema::table()->change(): doctrine/dbal
     * tidak menangani perubahan enum MySQL dengan andal.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `absensi`
            MODIFY COLUMN `status`
            ENUM('tepat_waktu','terlambat','alpha','izin','cuti','dinas','libur')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            NOT NULL DEFAULT 'alpha'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `absensi`
            MODIFY COLUMN `status`
            ENUM('tepat_waktu','terlambat','alpha','izin','sakit','cuti','dinas','libur')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            NOT NULL DEFAULT 'alpha'
        ");
    }
};
