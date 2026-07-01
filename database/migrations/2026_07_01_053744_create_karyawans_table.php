<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->string('nip', 50)->unique();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('foto_profil')->nullable()->comment('Path foto profil');
            $table->string('foto_wajah')->nullable()->comment('Path foto referensi untuk face recognition');
            $table->enum('status_pegawai', ['tetap', 'kontrak', 'orientasi', 'magang'])->default('orientasi');
            $table->enum('role', ['admin', 'karyawan'])->default('karyawan');
            $table->string('unit_kerja')->nullable();
            $table->string('jabatan')->nullable();
            $table->date('tanggal_bergabung')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
