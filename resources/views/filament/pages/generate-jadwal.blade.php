<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Jadwal Karyawan Umum</x-slot>
        <x-slot name="description">Membuat Jadwal bulanan dari shift yang sudah ditugaskan ke karyawan umum.</x-slot>

        {{ $this->formBulanan }}

        <div class="mt-4">
            {{ $this->generateBulananAction }}
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Jadwal Karyawan Rotasi</x-slot>
        <x-slot name="description">Membuat Jadwal bulanan dari pola rotasi yang sudah ditugaskan ke karyawan rotasi, untuk semua unit atau satu unit tertentu.</x-slot>

        {{ $this->formRotasi }}

        <div class="mt-4">
            {{ $this->generateRotasiAction }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
