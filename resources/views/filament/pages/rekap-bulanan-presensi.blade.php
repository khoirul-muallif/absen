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
        <x-slot name="heading">Hasil Rekap Bulanan</x-slot>
        <x-slot name="description">{{ $rows->count() }} karyawan ditemukan untuk filter ini.</x-slot>

        <div class="-mx-6 -mb-6 overflow-x-auto border-t border-gray-200 dark:border-white/10">
            <table class="w-full min-w-max divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">NIP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit Kerja</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Instansi</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hadir</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Terlambat</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Alpha</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Izin</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cuti</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dinas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Toleransi Bulanan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @if ($rows->isEmpty())
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada data untuk filter ini.
                            </td>
                        </tr>
                    @else
                        @foreach ($rows as $i => $baris)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ $baris['karyawan']->nip }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $baris['karyawan']->nama }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $baris['karyawan']->unit_kerja }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $baris['karyawan']->instansi->nama ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $baris['total_hadir'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <x-filament::badge :color="$baris['terlambat'] > 0 ? 'warning' : 'gray'">
                                        {{ $baris['terlambat'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <x-filament::badge :color="$baris['alpha'] > 0 ? 'danger' : 'gray'">
                                        {{ $baris['alpha'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $baris['izin'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $baris['cuti'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $baris['dinas'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <x-filament::badge :color="$baris['melebihi_toleransi'] ? 'danger' : 'success'">
                                        {{ $baris['melebihi_toleransi'] ? 'Ya' : 'Tidak' }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
