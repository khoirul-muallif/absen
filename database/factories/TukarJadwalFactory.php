<?php

namespace Database\Factories;

use App\Models\Jadwal;
use Illuminate\Database\Eloquent\Factories\Factory;

class TukarJadwalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jadwal_id' => Jadwal::factory(),
            // jadwal_tujuan_id & tanggal_baru sengaja tidak diisi default —
            // tentukan lewat state() sesuai mode yang mau ditest
            'alasan'    => $this->faker->sentence(),
            'status'    => 'pending',
        ];
    }

    /**
     * Mode tukar: butuh jadwal_tujuan_id.
     */
    public function modeTukar(?int $jadwalTujuanId = null): static
    {
        return $this->state(fn () => [
            'jadwal_tujuan_id' => $jadwalTujuanId ?? Jadwal::factory(),
        ]);
    }

    /**
     * Mode pindah sendiri: butuh tanggal_baru.
     */
    public function modePindah(?string $tanggalBaru = null): static
    {
        return $this->state(fn () => [
            'tanggal_baru' => $tanggalBaru ?? now()->addWeek()->toDateString(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }
}
