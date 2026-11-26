<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ditandai saat admin mereset password (fase 78), dimatikan saat
     * karyawan menggantinya sendiri (fase 86).
     *
     * TIDAK ada middleware yang memblokir endpoint lain saat flag ini
     * true — keputusan 25 Nov 2026. Password default dari admin bukan
     * rahasia yang bocor ke publik, melainkan diberikan langsung ke
     * karyawan yang bersangkutan, jadi risikonya rendah. Memblokir
     * absen justru bisa mengunci orang dari kewajiban hariannya kalau
     * ada bug di alur ganti password.
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->boolean('harus_ganti_password')
                ->default(false)
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('harus_ganti_password');
        });
    }
};
