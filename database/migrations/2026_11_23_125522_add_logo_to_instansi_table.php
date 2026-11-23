<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instansi', function (Blueprint $table) {
            // Nullable: instansi yang sudah ada tidak punya logo, dan
            // memaksanya berarti migration gagal di data yang sudah jalan.
            $table->string('logo')->nullable()->after('telepon');
        });
    }

    public function down(): void
    {
        Schema::table('instansi', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
