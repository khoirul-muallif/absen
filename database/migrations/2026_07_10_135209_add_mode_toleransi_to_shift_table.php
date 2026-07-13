<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shift', function (Blueprint $table) {
            $table->enum('mode_toleransi', ['harian', 'akumulasi_bulanan'])
                ->default('harian')
                ->after('toleransi_menit');
        });
    }

    public function down(): void
    {
        Schema::table('shift', function (Blueprint $table) {
            $table->dropColumn('mode_toleransi');
        });
    }
};
