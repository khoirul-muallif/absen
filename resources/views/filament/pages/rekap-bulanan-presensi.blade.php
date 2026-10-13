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
                    <th class="px-3 py-2 text-center">Hadir</th>
                    <th class="px-3 py-2 text-center">Terlambat</th>
                    <th class="px-3 py-2 text-center">Alpha</th>
                    <th class="px-3 py-2 text-center">Izin</th>
                    <th class="px-3 py-2 text-center">Cuti</th>
                    <th class="px-3 py-2 text-center">Dinas</th>
                    <th class="px-3 py-2 text-center">Lewat Toleransi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $baris)
                    <tr class="border-b dark:border-gray-700">
                        <td class="px-3 py-2">{{ $baris['karyawan']->nama }}</td>
                        <td class="px-3 py-2">{{ $baris['karyawan']->unit_kerja }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['total_hadir'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['terlambat'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['alpha'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['izin'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['cuti'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $baris['dinas'] }}</td>
                        <td class="px-3 py-2 text-center">
                            @if ($baris['melebihi_toleransi'])
                                <span class="text-red-600 dark:text-red-400 font-medium">Ya</span>
                            @else
                                Tidak
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                            Tidak ada data untuk filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
