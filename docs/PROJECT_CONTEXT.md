# PROJECT CONTEXT — absensi-app versi 5

> File ini **BUKAN** starter default chat baru — pakai `QUICK_CONTEXT.md` untuk itu.
> Upload file ini cuma kalau task butuh detail teknis dalam: endpoint,
> daftar Filament Resource, atau logika generator jadwal.
> Skema DB detail (kolom, tipe, FK) ada di `SCHEMA.md` — upload terpisah kalau
> task nyentuh migration/query langsung. File ini cuma simpan *alasan desain*
> di balik skema, bukan detail kolomnya.
> Preferensi kerja, konvensi project, dan larangan eksplisit ada di `QUICK_CONTEXT.md` — tidak diulang di sini.
> Task aktif & status testing terkini ada di `todo.md` dan `CHANGELOG.md` — tidak diulang di sini juga, supaya gak ada 2 tempat yang harus disinkronkan tiap update.

**Update terakhir:** 28 Juli 2026

## Ringkasan
Aplikasi absensi karyawan berbasis **Laravel + Filament (admin panel)** dengan **REST API** (Sanctum) untuk aplikasi mobile/klien karyawan. Studi kasus: RSU Banyumanik 2. Fitur: absensi via QR + geolocation + face snapshot, manajemen shift, cuti/izin/lembur/dinas dengan approval workflow, tukar jadwal (tukar rekan / pindah sendiri), hari libur nasional/cuti bersama, toleransi keterlambatan (harian vs akumulasi bulanan), klasifikasi karyawan umum vs rotasi, generator jadwal rotasi otomatis.

**Status project:** masih tahap development — belum ada deployment/database production. Frontend (scan QR + GPS + face gesture) masih rencana, belum mulai dikerjakan. Untuk progres/fase terbaru dan jumlah test saat ini, cek `CHANGELOG.md` (entri paling atas) — sengaja tidak diduplikasi angkanya di sini biar gak ada risiko dua file beda angka.

## Stack
- Laravel + Filament v4.11.7 (admin panel)
- MySQL 8.0 (Laragon, port 3308)
- Laravel Sanctum (API token mobile app)
- Scheduler (pengingat absen, rekap harian alpha)
- Pest v4.7.5 (testing) — database testing terpisah `absensi_app_test`

## Skema Database
Detail lengkap (kolom, tipe, FK, index) ada di `SCHEMA.md`. Di sini cuma
dicatat **alasan desain** yang gak kelihatan dari struktur kolom mentah:

- `karyawan.tipe_jadwal` (umum/rotasi) — source of truth eksplisit, sengaja
  TIDAK diinfer dari unit_kerja/jabatan (lihat QUICK_CONTEXT Larangan).
  umum → pakai `karyawan_shift`; rotasi → pakai `karyawan_pola_rotasis` +
  `jadwals` harian ter-generate.
- `izins`/`lemburs` sengaja TIDAK sync ke `absensi` (partial per jam, beda
  sifat dari cuti/dinas yang statusnya penuh 1 hari).
- `cutis`/`dinas` approve → sync ke `absensi` dan `jadwals` (lihat
  CHANGELOG fase 19-21 untuk histori kenapa & bagaimana).
- `absensi.status` enum punya opsi `'sakit'` yang belum ada modul/controller
  eksplisit menanganinya — lihat catatan ⚠️ di SCHEMA.md, belum diklarifikasi
  apakah reserved atau dead value.

## Generator Jadwal Rotasi
- Command: `php artisan jadwal:generate-rotasi {bulan} {tahun} {--unit=} {--overwrite-generate}`
- Logika: tiap karyawan tipe rotasi dapat posisi di siklus dari `(tanggal - tanggal_mulai) % panjang_siklus`, lalu ambil `langkah` sesuai posisi itu (shift atau libur).
- Default aman: tidak menimpa baris `jadwals` manapun yang sudah ada, kecuali pakai `--overwrite-generate` (dan tetap tidak menyentuh yang `sumber=manual`).
- Hari libur nasional: override jadi libur kecuali `pola.berlaku_saat_libur_nasional=true`.

## Generator Jadwal Bulanan (umum)
- Command: `php artisan jadwal:generate-bulanan {--bulan=} {--tahun=} {--dry-run}`
- Ambil assignment `KaryawanShift` yang overlap bulan target, generate `Jadwal` jenis reguler untuk hari kerja sesuai pola `hari_kerja` shift.
- Skip: tanggal yang sudah ada Jadwal (termasuk manual), bukan hari kerja shift, bentrok Cuti/Dinas approved. Hari libur nasional tetap digenerate eksplisit sebagai jenis `libur`.
- Guard pengaman: karyawan tipe rotasi yang secara anomali masih punya `KaryawanShift` akan dilewati.

## Guard Server-Side Tipe Jadwal
- `KaryawanShiftForm.karyawan_id` → wajib `tipe_jadwal = umum` (`Rule::exists('karyawan','id')->where('tipe_jadwal', Karyawan::TIPE_UMUM)`)
- `KaryawanPolaRotasiForm.karyawan_id` → wajib `tipe_jadwal = rotasi` (kebalikannya)
- Keduanya sudah punya `modifyQueryUsing` untuk UX dropdown + rule validasi server-side yang simetris, jadi tidak bisa lagi ke-assign silang lewat manipulasi request langsung.

## Endpoint API (saat ini)

POST /api/auth/login | logout | GET /api/auth/me
GET /api/absensi/status
POST /api/absensi/masuk (GPS + QR + foto)
POST /api/absensi/pulang (GPS + foto)
GET /api/absensi/riwayat | rekap
GET /api/instansi/qr/{kode}
GET /api/notifikasi [?belum_baca=true] | /jumlah
POST /api/notifikasi/{id}/baca | /baca-semua
DELETE /api/notifikasi/{id}

**Cuti/Izin/Lembur/Dinas** (semua auto-scoped ke karyawan yang login):
POST /api/cuti (ajukan) | GET /api/cuti (riwayat, filter status) | GET /api/cuti/kuota | DELETE /api/cuti/{id} (batalkan, cuma kalau pending)
POST /api/izin | GET /api/izin | DELETE /api/izin/{id}
POST /api/lembur | GET /api/lembur | DELETE /api/lembur/{id}
POST /api/dinas | GET /api/dinas | DELETE /api/dinas/{id}

**Tukar Jadwal:**
POST /api/tukar-jadwal (ajukan, mode tukar & pindah) | GET /api/tukar-jadwal (riwayat) | GET /api/tukar-jadwal/menunggu-respon-saya | POST /api/tukar-jadwal/{id}/respon-rekan | DELETE /api/tukar-jadwal/{id}

## Filament Resources
Absensis, Cutis, Dinas, HariLiburs, Instansis, Izins, Jadwals,
JenisCutis, Karyawans, KaryawanShifts ("Shift Karyawan Umum"),
KaryawanPolaRotasis ("Shift Karyawan Rotasi"), KuotaCutis, Lemburs,
PolaRotasis, QrInstansis, Shifts, TukarJadwals
Dashboard 4 widget (StatsOverview, GrafikKehadiran, RekapBulanIni, AbsensiHariIni). Primary color teal `#1D9E75`.

> Grouping navigationGroup: cek CHANGELOG untuk status reorganisasi terbaru
> kalau butuh tau grup menu saat ini — jangan asumsikan dari versi lama file ini.

## Known Gap Struktural
Item yang sifatnya keputusan desain jangka panjang / butuh investigasi
mendalam (bukan task checklist biasa — task checklist ada di `todo.md`):

1. `tipe_jadwal`: backfill pakai heuristik, baru terverifikasi di 5 data dummy — belum di skala production (tidak relevan sekarang, belum ada production).
2. Assignment `KaryawanShift` di seeder dibatasi per bulan — extend manual `tanggal_berakhir` tiap generate bulan baru.
3. `Karyawan.role`/`isAdmin()` vs `HasApprovalWorkflow` — `HasApprovalWorkflow::approve()` type-hint eksplisit `User $approver` (guard Filament), bukan `Karyawan`, jadi `Karyawan.role='admin'` saat ini tidak kepake sama sekali di alur approval walau namanya mirip. Perlu dipastikan tidak ada celah kalau nanti ada API approve dari sisi karyawan.
4. `absensi.status` enum punya opsi `'sakit'` yang belum jelas peruntukannya (lihat catatan di SCHEMA.md).
5. Error handling sekarang terpusat di `bootstrap/app.php` (`withExceptions()`)
   untuk route `/api/*` — lihat CHANGELOG fase 24 untuk daftar exception
   yang sudah diseragamkan. Error 500 murni sengaja tidak disentuh (tetap
   default Laravel).
