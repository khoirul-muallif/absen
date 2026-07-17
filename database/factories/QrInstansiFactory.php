<?php

namespace Database\Factories;

use App\Models\Instansi;
use App\Models\QrInstansi;
use Illuminate\Database\Eloquent\Factories\Factory;

class QrInstansiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instansi_id' => Instansi::factory(),
            'kode_qr'     => QrInstansi::generateKode(),
            'is_active'   => true,
            'expired_at'  => null, // null = QR statis permanen
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expired_at' => now()->subDay()]);
    }
}
