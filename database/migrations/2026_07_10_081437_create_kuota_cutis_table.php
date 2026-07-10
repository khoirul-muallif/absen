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
       Schema::create('kuota_cutis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->foreignId('jenis_cuti_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('kuota');
            $table->unsignedInteger('terpakai')->default(0);
            $table->timestamps();
            $table->unique(['karyawan_id', 'jenis_cuti_id', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuota_cutis');
    }
};
