<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_instansi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->cascadeOnDelete();
            $table->string('kode_qr')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expired_at')->nullable()->comment('Null = QR statis permanen');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_instansi');
    }
};
