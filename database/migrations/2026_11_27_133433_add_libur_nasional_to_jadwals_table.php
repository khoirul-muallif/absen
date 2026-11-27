<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            // Baris jenis='libur' bisa berarti dua hal yang berbeda
            // sifatnya: libur mingguan (pola rotasi, atau hari bukan-kerja
            // shift) dan libur nasional. Sebelum kolom ini, satu-satunya
            // pembeda adalah string `keterangan` — dan tabel hari_liburs,
            // yang tidak pernah dicek siapa pun selain generator.
            //
            // Default false: libur mingguan jauh lebih banyak, dan baris
            // non-libur tidak pernah menyentuh kolom ini.
            $table->boolean('libur_nasional')->default(false)->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropColumn('libur_nasional');
        });
    }
};
