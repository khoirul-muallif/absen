<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaRotasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'instansi_id',
        'unit_kerja',
        'nama_pola',
        'langkah',
        'berlaku_saat_libur_nasional',
        'is_active',
    ];

    protected $casts = [
        'langkah' => 'array',
        'berlaku_saat_libur_nasional' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function karyawanPolaRotasis(): HasMany
    {
        return $this->hasMany(KaryawanPolaRotasi::class);
    }

    /**
     * Panjang siklus (jumlah langkah).
     *
     * Kolom `langkah` NOT NULL di DB, jadi null-safety di sini murni defensif.
     * Yang benar-benar mungkin adalah array kosong — minItems(1) cuma berlaku
     * di form, tidak menghalangi seeder atau insert langsung. Pola tanpa
     * langkah akan dilewati generator tanpa pesan apa pun.
     */
    public function panjangSiklus(): int
    {
        return is_array($this->langkah) ? count($this->langkah) : 0;
    }

    /**
     * Ambil langkah pola di posisi tertentu (0-indexed, sudah di-mod di caller).
     */
    public function langkahKe(int $posisi): array
    {
        return $this->langkah[$posisi];
    }

    /**
     * Apakah pola ini masih di-assign ke karyawan?
     *
     * Perilaku FK `karyawan_pola_rotasis.pola_rotasi_id` tidak disebut di
     * SCHEMA.md. Kalau CASCADE, menghapus pola akan menghilangkan assignment
     * diam-diam dan karyawan rotasi kehilangan sumber jadwalnya. Kalau
     * RESTRICT, muncul QueryException 1451 mentah ke layar. Guard ini aman
     * untuk kedua kemungkinan — pola yang sama dengan Shift::sedangDipakai()
     * di fase 30.
     */
    public function sedangDipakai(): bool
    {
        return $this->karyawanPolaRotasis()->exists();
    }

    /**
     * Preview siklus untuk pola yang sudah tersimpan.
     *
     * $mulai default hari ini. CATATAN: anchor siklus yang sebenarnya adalah
     * `tanggal_mulai` per karyawan di karyawan_pola_rotasis, jadi urutan
     * shift-nya benar tapi tanggalnya hanya benar kalau $mulai kebetulan sama
     * dengan anchor karyawan yang bersangkutan.
     */
    public function previewSiklus(int $jumlahHari = 14, ?Carbon $mulai = null): array
    {
        return static::hitungPreviewSiklus(
            is_array($this->langkah) ? $this->langkah : [],
            $this->instansi_id,
            (bool) $this->berlaku_saat_libur_nasional,
            $jumlahHari,
            $mulai
        );
    }

    /**
     * Hitung preview siklus dari data mentah.
     *
     * Dibuat static supaya bisa dipakai dua-duanya: form (dari state Repeater
     * yang sedang diisi, sebelum tersimpan) dan infolist (dari record). Juga
     * supaya perhitungannya bisa dites langsung sebagai array — versi
     * sebelumnya merakit HTML di dalam Placeholder, jadi satu-satunya
     * assertion yang mungkin cuma mencocokkan potongan kalimat.
     *
     * Tiap baris: tanggal, posisi (0-indexed), libur, shift_id, nama_libur
     * (nama hari libur nasional kalau ada), dan override_libur_nasional
     * (true kalau langkahnya kerja tapi dipaksa libur karena polanya tidak
     * berlaku saat libur nasional).
     */
    public static function hitungPreviewSiklus(
        array $langkah,
        ?int $instansiId,
        bool $berlakuSaatLiburNasional,
        int $jumlahHari = 14,
        ?Carbon $mulai = null
    ): array {
        // Kunci Repeater berupa UUID, jadi perlu di-reindex dulu supaya
        // posisi siklus bisa dihitung dengan modulo.
        $langkah = array_values($langkah);

        if ($langkah === []) {
            return [];
        }

        $panjang = count($langkah);
        $mulai = $mulai ? $mulai->copy()->startOfDay() : today();

        $liburNasional = $instansiId
            ? HariLibur::where('instansi_id', $instansiId)
                ->whereBetween('tanggal', [
                    $mulai->toDateString(),
                    $mulai->copy()->addDays($jumlahHari - 1)->toDateString(),
                ])
                ->get()
                ->keyBy(fn (HariLibur $h): string => Carbon::parse($h->tanggal)->toDateString())
            : collect();

        $hasil = [];

        for ($i = 0; $i < $jumlahHari; $i++) {
            $tanggal = $mulai->copy()->addDays($i);
            $step = $langkah[$i % $panjang];

            $liburLangkah = (bool) ($step['libur'] ?? false);
            $namaLibur = $liburNasional->get($tanggal->toDateString())?->nama;

            $hasil[] = [
                'tanggal' => $tanggal,
                'posisi' => $i % $panjang,
                'libur' => $liburLangkah,
                'shift_id' => $liburLangkah ? null : ($step['shift_id'] ?? null),
                'nama_libur' => $namaLibur,
                'override_libur_nasional' => $namaLibur !== null
                    && ! $berlakuSaatLiburNasional
                    && ! $liburLangkah,
            ];
        }

        return $hasil;
    }
}
