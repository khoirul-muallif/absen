<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email turun dari kredensial login jadi data profil biasa (fase 79
     * memindahkan login ke username). Pihak RS mengonfirmasi tidak semua
     * karyawan punya email — selama kolom ini required, import Excel akan
     * menolak ~110 baris dan admin terpaksa mengarang alamat.
     *
     * Unique index SENGAJA dipertahankan: MySQL mengizinkan banyak NULL
     * dalam unique index, jadi banyak baris kosong tidak saling bentrok,
     * tapi dua email terisi yang sama tetap ditolak. Itu persis perilaku
     * yang dibutuhkan, bukan kompromi.
     */
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris dengan email NULL akan menggagalkan rollback ini. Itu
        // disengaja — rollback diam-diam yang mengisi alamat palsu jauh
        // lebih buruk daripada migration yang berhenti dan minta dilihat.
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
