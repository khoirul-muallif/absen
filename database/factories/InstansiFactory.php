<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstansiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama'          => $this->faker->company() . ' Hospital',
            'kode_instansi' => strtoupper($this->faker->unique()->bothify('INST-####')),
            'latitude'      => $this->faker->latitude(-8, -6),
            'longitude'     => $this->faker->longitude(110, 111),
            'radius_meter'  => 100,
            'alamat'        => $this->faker->address(),
            'telepon'       => $this->faker->phoneNumber(),
            'is_active'     => true,
        ];
    }
}
