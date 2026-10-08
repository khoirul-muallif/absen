<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_cutis', function (Blueprint $table) {
            $table->enum('periode_kuota', ['tahunan', 'semesteran'])
                ->default('tahunan')
                ->after('default_kuota');
        });

        Schema::table('kuota_cutis', function (Blueprint $table) {
            // 0 = tidak semesteran (sentinel, BUKAN null) — lihat catatan di PR.
            $table->tinyInteger('semester')->unsigned()->default(0)->after('tahun');
        });

        // Bikin unique index BARU dulu, baru drop yang LAMA. Urutan ini wajib:
        // karyawan_id butuh index yang menaunginya terus-menerus karena ada FK
        // ke karyawan (InnoDB requirement) — index composite lama itu yang
        // selama ini menaunginya. Kalau di-drop duluan, sempat tanpa index
        // sama sekali di tengah proses -> error 1553.
        Schema::table('kuota_cutis', function (Blueprint $table) {
            $table->unique(
                ['karyawan_id', 'jenis_cuti_id', 'tahun', 'semester'],
                'kuota_cutis_karyawan_jenis_tahun_semester_unique'
            );
        });

        Schema::table('kuota_cutis', function (Blueprint $table) {
            $table->dropUnique('kuota_cutis_karyawan_id_jenis_cuti_id_tahun_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kuota_cutis', function (Blueprint $table) {
            $table->unique(['karyawan_id', 'jenis_cuti_id', 'tahun']);
        });

        Schema::table('kuota_cutis', function (Blueprint $table) {
            $table->dropUnique('kuota_cutis_karyawan_jenis_tahun_semester_unique');
            $table->dropColumn('semester');
        });

        Schema::table('jenis_cutis', function (Blueprint $table) {
            $table->dropColumn('periode_kuota');
        });
    }
};
