# QUICK CONTEXT — absensi-app

> Tempel file ini di **setiap chat baru** + tulis: "Lanjutkan progres project ini, lihat QUICK_CONTEXT.md"
> Detail alasan desain & endpoint lengkap ada di `PROJECT_CONTEXT.md` — upload terpisah kalau task butuh itu.
> Skema DB detail (kolom/tipe/FK) ada di `SCHEMA.md` — upload terpisah kalau task nyentuh migration/query langsung. Update file ini (export dump baru) tiap curiga ada migration yang belum kerekap.
> History per-fase & alasan keputusan lama ada di `CHANGELOG.md` — sebut/upload kalau nelusurin masalah lama, atau kalau butuh angka test/status paling akurat (entri paling atas = paling baru).
> Task aktif ada di `todo.md` — tempel bareng file ini kalau mulai sesi kerja baru.

**Update terakhir:** 15 September 2026

## Ringkasan
Aplikasi absensi karyawan — Laravel + Filament (admin panel) + REST API (Sanctum) untuk mobile app. Studi kasus: RSU Banyumanik 2. Fitur: absensi QR + geolocation + face snapshot, manajemen shift, cuti/izin/lembur/dinas dengan approval workflow, tukar jadwal, hari libur, toleransi keterlambatan (harian/akumulasi bulanan), karyawan umum vs rotasi + generator jadwal rotasi otomatis.

**Status:** masih development, belum ada deployment/database production. Frontend (scan QR + GPS + face gesture) belum mulai. REST API Cuti/Izin/Lembur/Dinas sudah selesai. Fokus sekarang: evaluasi solidity & UX backend — audit lintas modul, race condition, validasi tanggal, konsistensi respons API, dan UX 5 modul approval + Jenis/Kuota Cuti sudah selesai. Sisa: review UX 10 Filament Resource lain. Detail progres per-fase: cek `CHANGELOG.md` entri teratas.

## Stack
Laravel + Filament v4.11.7, MySQL 8.0 (Laragon, port 3308), Sanctum, Pest v4.7.5 (DB testing terpisah `absensi_app_test`).

## Preferensi Kerja
- Scaffolding baru (model/migration/controller/factory/resource) **selalu** `php artisan make:*` — jangan tulis file manual.
- Commit pakai editor (`git commit`, bukan `-m`) — subject + body.
- Iteratif: konfirmasi tiap tahap, testing di antara langkah.
- tolong jawab pakai bahsa indonesia

## Catatan Konvensi
- Nama tabel **tidak konsisten sengaja**: `karyawans` plural (default), tapi `instansi`, `shift`, `absensi` singular (custom override). Cek dulu, jangan asumsi plural semua.
- Encoding Windows PowerShell 5.1: jangan `Set-Content -Encoding UTF8` (nyisipin BOM → fatal error PHP). Pakai VS Code, atau `[System.IO.File]::WriteAllText($path, $content, (New-Object System.Text.UTF8Encoding $false))`.
- `KuotaCuti::sisaUntuk()` return **`?int`, dan itu disengaja**: `null` = row KuotaCuti belum ada = tidak ada dasar untuk menolak (kebijakan fase 22); `0` = row ada, kuota memang habis. Jangan "dirapikan" jadi `int` atau di-`?? 0` di pemanggil — penyamaan dua keadaan ini sudah jadi bug dua kali (fase 25 & 26).

## Batasan & Larangan Eksplisit
- Jangan ubah seeder `KaryawanShift` jadi open-ended (dibatasi per bulan, sengaja) tanpa konfirmasi.
- Jangan infer `tipe_jadwal` dari `unit_kerja`/`jabatan` — field eksplisit itu source of truth, jangan ditebak dari field lain.
- Jangan bikin fallback implisit antar tipe_jadwal umum/rotasi — harus branch eksplisit (pernah jadi bug laten, lihat CHANGELOG fase 13).
- Jangan asumsikan nama tabel plural — cek dulu (lihat Catatan Konvensi).
- Jangan sinkronkan Izin/Lembur ke Absensi seperti Cuti/Dinas — sengaja beda (partial per jam, bukan penuh 1 hari).
- Jangan timpa `Absensi.waktu_masuk` yang sudah terisi asli — cuma row dengan `waktu_masuk` masih null yang boleh disinkron ulang (kecuali untuk Cuti/Dinas approved, yang SELALU override — lihat CHANGELOG fase 20).
- Jangan panggil `KuotaCuti::untuk()`/`sisaUntuk()` di dalam `afterApprove()` — di sana row-nya WAJIB diambil dengan `lockForUpdate()` di dalam transaksi (race condition fase 22). Finder terpusat justru menghapus proteksinya.
- Jangan pakai `TimePicker::native(false)` (nyimpen `00:00` sebagai value valid begitu field disentuh — lihat CHANGELOG fase 25). DatePicker `native(false)` tidak bermasalah.
- Kalau migration baru dibuat (kolom/tabel berubah), ingetkan di akhir sesi untuk update `SCHEMA.md` juga (export dump baru) — bukan cuma dicatat di CHANGELOG.

## Kategori Test yang Sering Kelewat
Selain happy path + validasi input + ownership (pola yang sudah konsisten
dipakai di semua controller sekarang), pertimbangkan juga tiap bikin modul
baru:
- Validasi tanggal edge case (mulai > selesai, tanggal sudah lewat, lintas tahun)
- Race condition kalau ada operasi cek-lalu-tulis (misal cek kuota →
  potong kuota, harus atomic/transaction)
- **Interaksi lintas modul (bukan cuma per-controller)** — ini yang paling
  penting. Pengalaman dulu: dev lancar banget, tapi production ternyata
  gak selancar itu. Kalau development kerasa "lancar banget" tanpa
  hambatan, itu justru sinyal buat curiga — kemungkinan besar cuma
  happy path yang ke-test. Contoh yang sudah benar ditest: Cuti/Dinas
  approved sync ke Absensi & Jadwal, TukarJadwal cek bentrok Cuti/Dinas.

### Dua pola bug di TEST-nya sendiri (sudah kejadian, jangan diulang)
- **Tanggal hardcode = bom waktu.** Test yang menyentuh aturan sensitif
  waktu (`after_or_equal:today`, perhitungan yang anchor ke `today()`)
  jangan pakai tanggal hardcode mentah — bekukan waktunya dengan
  `$this->travelTo(Carbon::parse('...'))` di `beforeEach`. Sudah dua kali
  kejadian: fase 14 (`hitungMenitTerlambat()` lolos 41 test karena tanggal
  hardcode kebetulan = tanggal server saat test ditulis) dan fase 26 (3
  test merah begitu 2026-08-01 lewat).
- **`assertStatus(422)` doang bikin test hijau karena alasan salah.** Kalau
  satu endpoint punya banyak sebab penolakan, assert sampai ke sebabnya
  (`assertJsonValidationErrors`, `assertJsonPath('message', ...)`,
  `assertJsonPath('data.sisa_kuota', ...)`). Ditemukan fase 26: beberapa
  test kuota sudah berhenti menguji kuota karena yang menolak duluan
  ternyata validasi tanggal — tidak ada yang tahu sampai test-nya merah
  karena sebab lain.
- **Guard di halaman View tidak ke-cover oleh test tabel.** `assertTableAction*`
  menguji `ListX`, bukan `ViewX`. Celah ini yang bikin bug EditAction tanpa
  guard di ViewCuti/ViewDinas/ViewTukarJadwal lolos sampai fase 25. Kalau
  nambah guard di tabel, tambahkan test halaman View-nya juga.

## Known Gap Aktif
1. Review UX Filament: 5 modul approval + Jenis/Kuota Cuti SUDAH selesai (fase 25). Sisa 10 Resource lain — lihat `todo.md`.
2. `Karyawan.role` vs sistem approval — belum ditelisik mendalam apakah ada celah kalau nanti ada API approve dari sisi karyawan (lihat PROJECT_CONTEXT.md Known Gap Struktural).
3. `tipe_jadwal` backfill baru terverifikasi di 5 data dummy, belum di skala production (belum relevan, belum ada production).
4. Assignment `KaryawanShift` di seeder dibatasi per bulan — extend manual tiap generate bulan baru.
5. `absensi.status` enum punya opsi `'sakit'` yang belum jelas peruntukannya — belum ada modul/controller yang menanganinya (lihat SCHEMA.md).
6. Kuota cuti yang belum punya row KuotaCuti tidak pernah menaikkan `terpakai`, jadi batas `default_kuota` di API cuma berlaku terhadap pengajuan pending — bukan terhadap cuti yang sudah dipakai. Lihat `todo.md` (digabung ke item KuotaCuti semesteran).
7. Karyawan rotasi dengan langkah LIBUR di polanya belum pernah disimulasikan sama sekali (seeder rotasi nonstop tanpa libur). `PengingatBelumAbsen`/`PengingatBelumAbsenPulang` juga belum punya test sama sekali. Lihat `todo.md`.

## Temuan Penting dari Source Code (jangan dianggap bug, sudah terdokumentasi/fixed)
- `Shift::tentukanStatus()` tidak pernah pakai `toleransi_menit` — status harian langsung `'terlambat'` begitu lewat 0 menit. `toleransi_menit` cuma dipakai di `sudahMelebihiToleransiBulanan()` (KPI bulanan).
- `hitungMenitTerlambat()` sudah di-anchor ke tanggal `$waktuMasuk` (bukan `today()`) — fixed fase 14, ada regression test.
- `Cuti::afterApprove()`/`Dinas::afterApprove()` SELALU override status Absensi (fase 20) dan juga sync ke Jadwal (fase 21) untuk setiap tanggal dalam rentang.
- `EditAction` di Izin (tabel & View) SENGAJA tidak dibatasi ke status pending, beda dari 4 modul approval lain — `jam_kembali` baru terisi setelah karyawan balik, yang bisa terjadi setelah approve. Ada regression test yang mengunci ini (fase 25).
- Warna tombol approve Cuti dinilai dari sisa MENTAH (kuota − terpakai), bukan sisa efektif setelah dikurangi pending lain — karena itulah yang dicek `afterApprove()`. Pending lain cuma muncul di tooltip (fase 26, ada test yang mengunci).

## Status Testing
Lihat `CHANGELOG.md` entri paling atas untuk jumlah test & assertion terbaru — sengaja tidak dicatat angka statis di sini supaya file ini gak perlu direvisi tiap fase baru.

## Command Cepat
```powershell
php artisan serve --host=0.0.0.0 --port=8001
.\vendor\bin\pest
php artisan migrate:fresh --seed
php artisan jadwal:generate-bulanan --dry-run
php artisan jadwal:generate-rotasi {bulan} {tahun}
php artisan karyawan:cek-tipe-jadwal
```
