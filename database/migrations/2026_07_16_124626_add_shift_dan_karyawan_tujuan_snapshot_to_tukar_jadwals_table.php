<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->foreignId('karyawan_tujuan_id')->nullable()->after('jadwal_tujuan_id')
                ->constrained('karyawan')->nullOnDelete();
            $table->date('tanggal_tujuan')->nullable()->after('karyawan_tujuan_id');
            $table->foreignId('shift_asal_id')->nullable()->after('tanggal_asal')
                ->constrained('shift')->nullOnDelete();
            $table->foreignId('shift_tujuan_id')->nullable()->after('tanggal_tujuan')
                ->constrained('shift')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tukar_jadwals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('karyawan_tujuan_id');
            $table->dropConstrainedForeignId('shift_asal_id');
            $table->dropConstrainedForeignId('shift_tujuan_id');
            $table->dropColumn('tanggal_tujuan');
        });
    }
};
