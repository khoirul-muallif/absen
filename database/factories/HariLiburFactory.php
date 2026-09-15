<?php

namespace Database\Factories;

use App\Models\Instansi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HariLibur>
 */
class HariLiburFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instansi_id' => Instansi::factory(),
            // unique() dipakai supaya count()->create() tidak menabrak
            // constraint unique(instansi_id, tanggal) saat instansi-nya sama.
            'tanggal' => $this->faker->unique()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'nama' => $this->faker->randomElement([
                'Tahun Baru Masehi',
                'Hari Kemerdekaan',
                'Maulid Nabi',
                'Hari Raya Nyepi',
                'Wafat Isa Almasih',
            ]),
            'keterangan' => null,
            'is_cuti_bersama' => false,
        ];
    }

    public function cutiBersama(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_cuti_bersama' => true,
            'nama' => 'Cuti Bersama '.$this->faker->randomElement(['Idul Fitri', 'Natal', 'Tahun Baru']),
        ]);
    }
}
