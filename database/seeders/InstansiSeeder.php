<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\QrInstansi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstansiSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = Instansi::create([
            'nama' => 'RSU Banyumanik 2',
            'kode_instansi' => 'RSUB2',
            'latitude' => -7.0784947,
            'longitude' => 110.4119292,
            'radius_meter' => 100,
            'alamat' => 'Jl. Perintis Kemerdekaan No.57, Banyumanik, Kec. Banyumanik, Kota Semarang, Jawa Tengah 50265',
            'telepon' => '024-7466525',
            'is_active' => true,
        ]);

        QrInstansi::create([
            'instansi_id' => $instansi->id,
            'kode_qr' => strtoupper(Str::random(32)),
            'is_active' => true,
        ]);
    }
}
