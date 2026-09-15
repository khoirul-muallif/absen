# RUNBOOK — absensi-app

> Cheatsheet command buat dijalankan di terminal. Ringkasan status project
> ada di `QUICK_CONTEXT.md` — file ini murni operasional, bukan konteks.

## 🔥 Sering dipakai
```
cd E:\laragon\www\absensi-app
php artisan serve --host=0.0.0.0 --port=8001

php artisan test
.\vendor\bin\pest
.\vendor\bin\pest --filter=Cuti

php artisan optimize:clear

php artisan migrate:fresh --seed
php artisan make:filament-user
php artisan db:seed --class=AbsensiSimulasiSeeder

Get-ChildItem -Recurse -Depth 2 | Where-Object { $_.FullName -notmatch "node_modules|vendor|storage" }

git stash pop
```

## 📅 Generator jadwal
```
# generate bulan depan (default)
php artisan jadwal:generate-bulanan --dry-run
php artisan jadwal:generate-bulanan

# atau generate bulan spesifik
php artisan jadwal:generate-bulanan --bulan=7 --tahun=2026

# rotasi (karyawan tipe_jadwal=rotasi)
php artisan jadwal:generate-rotasi {bulan} {tahun}
php artisan jadwal:generate-rotasi 8 2026 --unit=IGD
php artisan jadwal:generate-rotasi 8 2026 --overwrite-generate
```

## 🩺 Diagnostik
```
php artisan karyawan:cek-tipe-jadwal
php artisan absensi:rekap-harian --tanggal=2026-07-15
php artisan schedule:list
```

## 🚑 Darurat / one-off fix (jarang tapi butuh)

# Sinkron ulang Jadwal & Absensi dari Cuti/Dinas approved.
# Idempoten, TIDAK menyentuh kuota. Ada konfirmasi sebelum jalan.
php artisan absensi:backfill-cuti-dinas

# Audit menit_terlambat & melebihi_toleransi_bulanan pada data lama
# (tindak lanjut bug fase 14). Default DRY-RUN — aman dijalankan kapan saja.
php artisan absensi:audit-menit-terlambat
php artisan absensi:audit-menit-terlambat --instansi=1
php artisan absensi:audit-menit-terlambat --fix   # BACKUP DB DULU

### ⚠️ JANGAN jalankan massal — afterApprove() sudah TIDAK idempoten

Dua snippet di bawah ini ditulis sekitar fase 10, waktu `afterApprove()`
cuma menyinkronkan Absensi (aman diulang). Sejak itu perilakunya berubah:

- **fase 21** — `afterApprove()` juga `updateOrCreate` Jadwal dan SENGAJA
  menimpa baris yang sumbernya `manual`. Jalan massal = semua edit manual
  admin di tanggal-tanggal itu ketimpa.
- **fase 22** — `afterApprove()` menaikkan `KuotaCuti.terpakai`. Jalan
  massal = **kuota terpakai terhitung ganda**. Kalau di tengah loop ada
  yang melewati kuota, `KuotaCutiTidakCukupException` dilempar dan loop
  berhenti di tengah: sebagian sudah ter-increment, sebagian belum, tanpa
  rollback menyeluruh (transaksinya per-record, bukan per-loop).

Kalau memang perlu, jalankan untuk SATU record spesifik dan pastikan dulu
`KuotaCuti.terpakai` untuk kombinasi itu memang belum mencerminkan cuti
tersebut. Backup DB dulu.

```
# BACA PERINGATAN DI ATAS SEBELUM MENJALANKAN
\App\Models\Cuti::where('status', 'approved')->get()->each(function ($cuti) { $cuti->afterApprove(); });

\App\Models\Dinas::where('status', 'approved')->get()->each(function ($dinas) { $dinas->afterApprove(); });
```

Untuk Dinas risikonya lebih kecil (tidak menyentuh kuota sama sekali),
tapi penimpaan Jadwal manual tetap berlaku.

## 📦 Setup model + migration awal (arsip — model-model ini udah lama jadi)
```
php artisan make:model Instansi -m
php artisan make:model QrInstansi -m
php artisan make:model Karyawan -m
php artisan make:model Shift -m
php artisan make:model KaryawanShift -m
php artisan make:model Absensi -m

php artisan make:migration create_jenis_cutis_table
php artisan make:migration create_kuota_cutis_table
php artisan make:migration create_cutis_table
php artisan make:migration create_izins_table
php artisan make:migration create_lemburs_table
php artisan make:migration create_dinas_table
php artisan make:migration create_jadwals_table
php artisan make:migration create_tukar_jadwals_table
php artisan make:migration make_shift_id_nullable_in_jadwals_table
php artisan make:migration add_hari_kerja_to_shift_table
php artisan make:migration add_menit_terlambat_to_absensi_table

php artisan make:model JenisCuti
php artisan make:model KuotaCuti
php artisan make:model Cuti
php artisan make:model Izin
php artisan make:model Lembur
php artisan make:model Dinas
php artisan make:model TukarJadwal
php artisan make:model HariLibur -m
```

## 📦 Setup Filament Resource (arsip — resource ini udah lama jadi)
```
php artisan make:filament-resource Instansi --generate
php artisan make:filament-resource QrInstansi --generate
php artisan make:filament-resource Karyawan --generate
php artisan make:filament-resource Shift --generate
php artisan make:filament-resource KaryawanShift --generate
php artisan make:filament-resource Absensi --generate

php artisan make:filament-resource JenisCuti --generate
php artisan make:filament-resource KuotaCuti --generate
php artisan make:filament-resource Cuti --generate
php artisan make:filament-resource Izin --generate
php artisan make:filament-resource Lembur --generate
php artisan make:filament-resource Dinas --generate
php artisan make:filament-resource Jadwal --generate
php artisan make:filament-resource TukarJadwal --generate
php artisan make:filament-resource HariLibur --generate
php artisan make:filament-resource PolaRotasi
php artisan make:filament-resource KaryawanPolaRotasi
```

## 🤖 Template prompt lanjut kerjaan (bukan command, tapi kepake)
```
[tempel QUICK_CONTEXT.md] + [tempel todo.md]
[upload ControllerX.php + ControllerXTest.php — sebagai contoh pola]
"Lanjutin ControllerY, ikutin pola dari ControllerX ini"
```

## 🗑️ Kandidat dihapus (cek dulu sebelum beneran hapus)
- _(kosongin dulu — isi manual pas nemu command yang emang udah gak
  relevan sama sekali)_
