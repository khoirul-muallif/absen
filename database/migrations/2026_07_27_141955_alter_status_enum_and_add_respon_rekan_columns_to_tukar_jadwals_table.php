<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum harus di-ALTER langsung, tidak bisa lewat Blueprint::change()
        DB::statement("ALTER TABLE tukar_jadwals MODIFY COLUMN status
            ENUM('menunggu_rekan','menunggu_admin','ditolak_rekan','approved','rejected')
            NOT NULL DEFAULT 'menunggu_admin'");

        // Migrasikan data lama yang mungkin masih 'pending' (kalau ada row eksisting)
        DB::table('tukar_jadwals')->where('status', 'pending')->update(['status' => 'menunggu_admin']);

        Schema::table('tukar_jadwals', function ($table) {
            $table->foreignId('direspon_oleh_rekan_id')->nullable()->after('karyawan_tujuan_id')
                ->constrained('karyawan')->nullOnDelete();
            $table->timestamp('direspon_rekan_at')->nullable()->after('direspon_oleh_rekan_id');
            $table->string('catatan_penolakan_rekan')->nullable()->after('direspon_rekan_at');
        });
    }

    public function down(): void
    {
        Schema::table('tukar_jadwals', function ($table) {
            $table->dropForeign(['direspon_oleh_rekan_id']);
            $table->dropColumn(['direspon_oleh_rekan_id', 'direspon_rekan_at', 'catatan_penolakan_rekan']);
        });

        DB::table('tukar_jadwals')->where('status', 'menunggu_admin')->update(['status' => 'pending']);
        DB::table('tukar_jadwals')->whereIn('status', ['menunggu_rekan', 'ditolak_rekan'])->update(['status' => 'rejected']);

        DB::statement("ALTER TABLE tukar_jadwals MODIFY COLUMN status
            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
