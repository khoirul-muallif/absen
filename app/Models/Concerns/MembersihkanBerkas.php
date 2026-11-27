<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Menghapus berkas lama saat kolomnya diganti, dan seluruh berkasnya saat
 * barisnya dihapus. Tanpa ini FileUpload Filament menyimpan yang baru dan
 * meninggalkan yang lama menumpuk tanpa ada baris DB yang menunjuknya —
 * tidak terlihat dari mana pun, termasuk saat karyawannya dihapus.
 *
 * Model yang memakainya mendeklarasikan:
 *   protected array $kolomBerkas = ['foto_profil' => 'public'];
 *
 * Dipasang lewat event model, bukan hook Filament, supaya jalur tinker
 * dan seeder ikut tertutup. Observer sengaja tidak dipakai: proyek ini
 * belum punya satu pun, dan event di model sudah jadi polanya (lihat
 * shift_asal_id di TukarJadwal).
 */

/**
 * ...
 *
 * @method static void updated(\Closure $callback)
 * @method static void deleted(\Closure $callback)
 */

trait MembersihkanBerkas
{
    public static function bootMembersihkanBerkas(): void
    {
        // 'updated', bukan 'updating': berkas lama baru dibuang setelah
        // penyimpanan benar-benar sukses. Kalau dihapus sebelum simpan dan
        // simpannya gagal, barisnya kembali ke nilai lama sementara
        // berkasnya sudah hilang.
        static::updated(function ($model) {
            foreach ($model->kolomBerkas ?? [] as $kolom => $disk) {
                if (! $model->wasChanged($kolom)) {
                    continue;
                }

                $model->hapusBerkas($disk, $model->getOriginal($kolom));
            }
        });

        static::deleted(function ($model) {
            foreach ($model->kolomBerkas ?? [] as $kolom => $disk) {
                $model->hapusBerkas($disk, $model->getAttribute($kolom));
            }
        });
    }

    protected function hapusBerkas(string $disk, ?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }
}
