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
        Schema::create('hari_liburs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama'); // "Idul Fitri 1447 H", "Hari Kemerdekaan"
            $table->text('keterangan')->nullable();
            $table->boolean('is_cuti_bersama')->default(false);
            $table->timestamps();

            $table->unique(['instansi_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hari_liburs');
    }
};
