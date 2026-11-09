<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel singular `informasi`, bukan default `informasis` —
        // ikut pola instansi/shift/absensi (lihat QUICK_CONTEXT Konvensi).
        Schema::create('informasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->string('judul');
            $table->text('isi');
            $table->date('tanggal_terbit');
            $table->boolean('is_aktif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Pola query utama endpoint karyawan: scope instansi + hanya aktif,
            // diurutkan terbaru dulu.
            $table->index(['instansi_id', 'is_aktif', 'tanggal_terbit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informasi');
    }
};
