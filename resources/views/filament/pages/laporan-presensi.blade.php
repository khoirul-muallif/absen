<x-filament-panels::page>
    <form wire:submit.prevent="exportExcel">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Export Excel
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <th class="px-3 py-2 text-left">Nama</th>
                    <th class="px-3 py-2 text-left">Unit Kerja</th>
                    <th class="px-3 py-2 text-left">Presensi Masuk</th>
                    <th class="px-3 py-2 text-left">Keterlambatan</th>
                    <th class="px-3 py-2 text-left">Presensi Pulang</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $absensi)
                    <tr class="border-b dark:border-gray-700">
                        <td class="px-3 py-2">{{ $absensi->karyawan->nama }}</td>
                        <td class="px-3 py-2">{{ $absensi->karyawan->unit_kerja }}</td>
                        <td class="px-3 py-2">{{ $absensi->waktu_masuk?->format('H:i:s') ?? '-' }}</td>
                        <td class="px-3 py-2">{{ ucfirst(str_replace('_', ' ', $absensi->status)) }}</td>
                        <td class="px-3 py-2">{{ $absensi->waktu_pulang?->format('H:i:s') ?? 'Belum' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                            Tidak ada data untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
