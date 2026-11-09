<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * getNavigationBadge() di lima Resource menjalankan
     * COUNT ... WHERE status = ... di SETIAP page load panel.
     * Tanpa index ini, lima full table scan per halaman begitu data
     * mulai banyak. Dicatat saat memasang badge (fase 77).
     */
    private const TABEL = ['cutis', 'izins', 'lemburs', 'dinas', 'tukar_jadwals'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }
    }
};
