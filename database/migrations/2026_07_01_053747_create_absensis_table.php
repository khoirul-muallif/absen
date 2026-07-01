<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shift')->restrictOnDelete();
            $table->foreignId('qr_instansi_id')->constrained('qr_instansi')->restrictOnDelete();

            $table->date('tanggal');

            // Data masuk
            $table->datetime('waktu_masuk')->nullable();
            $table->decimal('latitude_masuk', 10, 7)->nullable();
            $table->decimal('longitude_masuk', 10, 7)->nullable();
            $table->string('foto_masuk')->nullable()->comment('Snapshot wajah saat absen masuk');

            // Data pulang
            $table->datetime('waktu_pulang')->nullable();
            $table->decimal('latitude_pulang', 10, 7)->nullable();
            $table->decimal('longitude_pulang', 10, 7)->nullable();
            $table->string('foto_pulang')->nullable()->comment('Snapshot wajah saat absen pulang');

            // Status & keterangan
            $table->enum('status', [
                'tepat_waktu',
                'terlambat',
                'alpha',
                'izin',
                'sakit',
                'cuti',
                'dinas',
                'libur',
            ])->default('alpha');

            $table->string('keterangan')->nullable();

            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
            $table->index(['karyawan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
