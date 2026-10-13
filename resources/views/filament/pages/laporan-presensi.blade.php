<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}

        <div class="mt-4 flex gap-2">
            <x-filament::button wire:click="exportExcel" icon="heroicon-m-arrow-down-tray" color="success">
                Export Excel
            </x-filament::button>
            <x-filament::button wire:click="exportPdf" icon="heroicon-m-document-arrow-down" color="danger" outlined>
                Export PDF
            </x-filament::button>
        </div>
    </x-filament::section>

    @php($rows = $this->getRows())

    <x-filament::section>
        <x-slot name="heading">Hasil Laporan</x-slot>
        <x-slot name="description">{{ $rows->count() }} baris presensi ditemukan untuk filter ini.</x-slot>

        <div class="-mx-6 -mb-6 overflow-x-auto border-t border-gray-200 dark:border-white/10">
            <table class="w-full min-w-max divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">NIP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit Kerja</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jabatan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Presensi Masuk</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Keterlambatan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Presensi Pulang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @if ($rows->isEmpty())
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada data untuk filter ini.
                            </td>
                        </tr>
                    @else
                        @foreach ($rows as $i => $absensi)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ $absensi->karyawan->nip }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $absensi->karyawan->nama }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $absensi->karyawan->unit_kerja }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $absensi->karyawan->jabatan }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $absensi->waktu_masuk?->format('H:i:s') ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-filament::badge :color="match (true) {
                                        $absensi->status === 'terlambat' => 'danger',
                                        $absensi->status === 'tepat_waktu' => 'success',
                                        default => 'gray',
                                    }">
                                        {{ ucfirst(str_replace('_', ' ', $absensi->status)) }}
                                    </x-filament::badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($absensi->waktu_pulang)
                                        <span class="text-gray-700 dark:text-gray-300">{{ $absensi->waktu_pulang->format('H:i:s') }}</span>
                                    @else
                                        <x-filament::badge color="warning">Belum</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
