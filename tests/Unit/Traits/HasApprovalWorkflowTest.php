<?php

use App\Models\Izin;
use App\Models\User;

// Pakai Izin sebagai model dasar karena paling sederhana - tidak punya
// afterApprove() custom, jadi murni menguji perilaku trait itu sendiri.

it('approve mengubah status jadi approved dan mengisi kolom approval', function () {
    $admin = User::factory()->create();
    $izin = Izin::factory()->create();

    $result = $izin->approve($admin, 'oke silakan');

    expect($result)->toBeTrue();
    expect($izin->fresh())
        ->status->toBe('approved')
        ->approved_by->toBe($admin->id)
        ->catatan_approval->toBe('oke silakan');
    expect($izin->fresh()->approved_at)->not->toBeNull();
});

it('reject mengubah status jadi rejected dan mengisi kolom approval', function () {
    $admin = User::factory()->create();
    $izin = Izin::factory()->create();

    $result = $izin->reject($admin, 'tidak bisa, sedang sibuk');

    expect($result)->toBeTrue();
    expect($izin->fresh())
        ->status->toBe('rejected')
        ->approved_by->toBe($admin->id)
        ->catatan_approval->toBe('tidak bisa, sedang sibuk');
});

it('catatan approval boleh null', function () {
    $admin = User::factory()->create();
    $izin = Izin::factory()->create();

    $izin->approve($admin);

    expect($izin->fresh()->catatan_approval)->toBeNull();
});

it('tidak error walau model tidak punya afterApprove() custom', function () {
    // Izin sengaja tidak override afterApprove() - trait cek method_exists()
    // dulu sebelum manggil, jadi harus aman dipanggil tanpa exception.
    $admin = User::factory()->create();
    $izin = Izin::factory()->create();

    expect(fn () => $izin->approve($admin))->not->toThrow(\Throwable::class);
});

it('isPending dan isApproved mencerminkan status saat ini', function () {
    $izin = Izin::factory()->create(['status' => 'pending']);

    expect($izin->isPending())->toBeTrue()
        ->and($izin->isApproved())->toBeFalse();

    $izin->update(['status' => 'approved']);

    expect($izin->fresh()->isPending())->toBeFalse()
        ->and($izin->fresh()->isApproved())->toBeTrue();
});

it('scope pending/approved/rejected memfilter status dengan benar', function () {
    Izin::factory()->create(['status' => 'pending']);
    Izin::factory()->create(['status' => 'approved']);
    Izin::factory()->create(['status' => 'rejected']);
    Izin::factory()->create(['status' => 'pending']);

    expect(Izin::pending()->count())->toBe(2)
        ->and(Izin::approved()->count())->toBe(1)
        ->and(Izin::rejected()->count())->toBe(1);
});

it('approver() mengembalikan User yang melakukan approve', function () {
    $admin = User::factory()->create(['name' => 'Admin Satu']);
    $izin = Izin::factory()->create();

    $izin->approve($admin);

    expect($izin->fresh()->approver->id)->toBe($admin->id);
});
