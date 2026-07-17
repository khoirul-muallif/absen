<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisCutiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Cuti Tahunan',
            'is_tahunan' => true,
            'default_kuota' => 12,
            'perlu_lampiran' => false,
            'potong_kuota' => true,
            'is_active' => true,
        ];
    }

    public function tanpaPotongKuota(): static
    {
        return $this->state(fn () => ['potong_kuota' => false]);
    }
}
