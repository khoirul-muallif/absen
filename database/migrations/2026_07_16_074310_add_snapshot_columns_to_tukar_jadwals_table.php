<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->foreignId('karyawan_pengaju_id')->nullable()->after('jadwal_id')->constrained('karyawan')->nullOnDelete();
            $table->date('tanggal_asal')->nullable()->after('karyawan_pengaju_id');
        });
    }

    public function down(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('karyawan_pengaju_id');
            $table->dropColumn('tanggal_asal');
        });
    }
};
