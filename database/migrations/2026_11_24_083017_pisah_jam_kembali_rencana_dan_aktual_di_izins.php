<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `jam_kembali` dulu ambigu: ajukan() menerimanya saat submit
     * (= rencana) DAN endpoint PATCH mengisinya setelah kembali
     * (= aktual), tanpa cara membedakan keduanya dari data.
     *
     * Dipisah jadi dua. Yang lama di-rename ke `rencana` karena mulai
     * sekarang kolom itu wajib diisi saat submit — itu arti barunya.
     */
    public function up(): void
    {
        // Rename dan add dipisah: digabung dalam satu closure bisa
        // bermasalah di MySQL.
        Schema::table('izins', function (Blueprint $table) {
            $table->renameColumn('jam_kembali', 'jam_kembali_rencana');
        });

        Schema::table('izins', function (Blueprint $table) {
            $table->time('jam_kembali_aktual')
                ->nullable()
                ->after('jam_kembali_rencana');
        });
    }

    public function down(): void
    {
        Schema::table('izins', function (Blueprint $table) {
            $table->dropColumn('jam_kembali_aktual');
        });

        Schema::table('izins', function (Blueprint $table) {
            $table->renameColumn('jam_kembali_rencana', 'jam_kembali');
        });
    }
};
