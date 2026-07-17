<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->enum('tipe_jadwal', ['umum', 'rotasi'])
                ->default('umum')
                ->after('role')
                ->comment('umum = pakai KaryawanShift (assignment periode), rotasi = jadwal harian manual di tabel jadwals, wajib eksplisit tiap hari');
        });

        // Backfill SEKALI JALAN untuk data lama: karyawan yang sama sekali gak
        // punya record KaryawanShift kemungkinan besar tipe rotasi (sesuai pola
        // data dummy seeder saat ini: Budi & Khoirul = umum+KaryawanShift,
        // Dedi/Siti/Rina = rotasi tanpa KaryawanShift).
        //
        // PENTING: ini cuma heuristik migrasi satu kali. Setelah ini,
        // tipe_jadwal jadi source of truth EKSPLISIT — bukan lagi diinferensi
        // dari ada/tidaknya KaryawanShift saat runtime. Cek manual hasil
        // backfill ini sebelum lanjut, terutama kalau ada karyawan umum yang
        // KaryawanShift-nya kebetulan belum sempat dibuat saat migrasi jalan
        // (bakal ke-backfill jadi rotasi secara salah).
        $idPunyaShift = DB::table('karyawan_shift')->distinct()->pluck('karyawan_id');

        DB::table('karyawan')
            ->whereNotIn('id', $idPunyaShift)
            ->update(['tipe_jadwal' => 'rotasi']);
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('tipe_jadwal');
        });
    }
};
