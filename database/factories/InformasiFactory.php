<?php

namespace Database\Factories;

use App\Models\Informasi;
use App\Models\Instansi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Informasi>
 */
class InformasiFactory extends Factory
{
    protected $model = Informasi::class;

    public function definition(): array
    {
        return [
            'instansi_id' => Instansi::factory(),
            'judul' => $this->faker->sentence(5),
            // Minimal 150 karakter supaya ringkasan benar-benar terpotong —
            // kalau isinya pendek, test excerpt lolos tanpa menguji apa pun.
            'isi' => $this->faker->paragraphs(4, true),
            'tanggal_terbit' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'is_aktif' => true,
            'created_by' => null,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_aktif' => false]);
    }
}
