\App\Models\Jadwal::whereDate('tanggal', '2026-07-19')->with(['karyawan', 'shift'])->get()->each(fn ($j) => print("{$j->karyawan->nama} — {$j->shift->nama_shift} — {$j->tanggal->toDateString()}\n"));
Sebelum approve: Dedi = pagi, Rina = malam (tanggal 19 Jul)
Sesudah approve: Dedi = malam, Rina = pagi (tanggal 19 Jul tetap sama, cuma karyawan_id-nya yang ketuker)
