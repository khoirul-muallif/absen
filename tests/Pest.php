<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function actingAsAdmin(): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    test()->actingAs($user, 'web');
    return $user;
}

function actingAsKaryawan(array $attributes = []): \App\Models\Karyawan
{
    $karyawan = \App\Models\Karyawan::factory()->create($attributes);
    test()->actingAs($karyawan, 'karyawan');
    return $karyawan;
}
