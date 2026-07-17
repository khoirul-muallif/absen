<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->foreignId('jadwal_tujuan_id')->nullable()->change();
            $table->date('tanggal_baru')->nullable()->after('jadwal_tujuan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->dropColumn('tanggal_baru');
            $table->foreignId('jadwal_tujuan_id')->nullable(false)->change();
        });
    }
};
