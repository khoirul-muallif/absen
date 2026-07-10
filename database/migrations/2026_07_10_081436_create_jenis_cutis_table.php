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
        Schema::create('jenis_cutis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->boolean('is_tahunan')->default(true);
            $table->unsignedInteger('default_kuota')->default(12);
            $table->boolean('perlu_lampiran')->default(false);
            $table->boolean('potong_kuota')->default(true); // cuti sakit/melahirkan bisa false
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_cutis');
    }
};
