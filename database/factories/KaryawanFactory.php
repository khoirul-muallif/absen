<?php

namespace Database\Factories;

use App\Models\Instansi;
use Illuminate\Database\Eloquent\Factories\Factory;

class KaryawanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instansi_id'       => Instansi::factory(),
            'nip'               => $this->faker->unique()->numerify('##########'),
            'nama'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'password'          => 'password', // otomatis di-hash karena cast 'hashed' di model
            'nomor_telepon'     => $this->faker->phoneNumber(),
            'foto_profil'       => null,
            'foto_wajah'        => null,
            'status_pegawai'    => 'tetap',
            'role'              => 'karyawan',
            'tipe_jadwal'       => 'umum',
            'unit_kerja'        => 'Rawat Inap',
            'jabatan'           => 'Staff',
            'tanggal_bergabung' => $this->faker->date(),
            'is_active'         => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function rotasi(): static
    {
        return $this->state(fn () => ['tipe_jadwal' => 'rotasi']);
    }

    public function umum(): static
    {
        return $this->state(fn () => ['tipe_jadwal' => 'umum']);
    }
}
