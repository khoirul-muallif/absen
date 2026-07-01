<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shift')->cascadeOnDelete();
            $table->date('tanggal_berlaku')->comment('Shift aktif mulai tanggal ini');
            $table->date('tanggal_berakhir')->nullable()->comment('Null = berlaku sampai diganti');
            $table->timestamps();

            $table->index(['karyawan_id', 'tanggal_berlaku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_shift');
    }
};
