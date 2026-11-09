# RUNBOOK — absensi-app

> Cheatsheet operasional. Konteks project ada di `QUICK_CONTEXT.md`,
> histori di `CHANGELOG.md`. File ini cuma command + prosedur.

## Sering dipakai

cd E:\laragon\www\absensi-app
php artisan serve --host=0.0.0.0 --port=8001

.\vendor\bin\pest
.\vendor\bin\pest --filter=Cuti

php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan make:filament-user
Get-ChildItem -Recurse -Depth 2 | Where-Object { $_.FullName -notmatch "node_modules|vendor|storage" }
git stash pop
## Simulasi data besar

php artisan db:seed --class=SimulasiMassalSeeder


150 karyawan dummy (umum + rotasi), 3 bulan, cuti/dinas/absensi acak.
Seeded (reproducible), insert chunked, ~1-2 menit. Jalankan SETELAH
`migrate:fresh --seed`, bukan bagian dari `DatabaseSeeder`.

## Generator jadwal

php artisan jadwal:generate-bulanan --dry-run
php artisan jadwal:generate-bulanan
php artisan jadwal:generate-bulanan --bulan=7 --tahun=2026

php artisan jadwal:generate-rotasi {bulan} {tahun}
php artisan jadwal:generate-rotasi 8 2026 --unit=IGD
php artisan jadwal:generate-rotasi 8 2026 --overwrite-generate


## Diagnostik

php artisan karyawan:cek-tipe-jadwal
php artisan absensi:rekap-harian --tanggal=2026-07-15
php artisan schedule:list


## Terjemahan Filament (locale `id`)

Terjemahan Indonesia bawaan Filament tidak lengkap — per 9 Nov 2026 ada
41 kunci hilang, yang tampil sebagai string teknis di panel admin (mis.
`filament-tables::table.columns.icon.boolean.true` alih-alih ikon
centang).

Tambalannya di `lang/vendor/`, **sengaja hanya berisi kunci yang
hilang**, bukan salinan lengkap. Laravel menggabungkan file di
`lang/vendor/` ke atas terjemahan paket per-kunci
(`FileLoader::loadNamespaceOverrides` → `array_replace_recursive`), jadi
kunci yang tidak kita tulis tetap ikut paket — termasuk perbaikan di
versi Filament berikutnya.

**JANGAN jalankan `vendor:publish --tag=filament-*-translations`.**
Begitu seluruh file tersalin, perbaikan upstream tidak akan pernah
terpakai lagi untuk paket itu, dan kita mengambil alih perawatan ~790
kunci selamanya.

### Setelah upgrade Filament

php scripts\cek-terjemahan.php


Script membandingkan `en` vs `id` di `vendor/`, lalu mengurangi yang
sudah ditambal di `lang/vendor/`. Outputnya tiga angka:

- **Perlu ditambal** — kunci hilang di paket dan belum ada di override
  kita. Daftarnya dicetak beserta teks Inggrisnya; terjemahkan dan
  tambahkan ke file yang ditunjuk.
- **Sudah ditambal** — hilang di paket, tapi override kita sudah
  menutupinya. Tidak perlu tindakan.
- **Override usang** — upstream sudah menerjemahkan kunci yang kita
  override. Hapus dari override kita, biar versi upstream yang dipakai.

Target: perlu ditambal 0, override usang 0.

Angka "sudah ditambal" juga berfungsi sebagai verifikasi: kalau turun
tanpa kita menghapus apa pun, berarti ada kunci di override yang
strukturnya tidak lagi cocok dengan paket — bug diam-diam yang tidak
terlihat di panel sampai layarnya kebetulan dibuka.

Per 9 Nov 2026: **0 perlu ditambal, 41 sudah ditambal, 0 usang.**

## Darurat / one-off
Sinkron ulang Jadwal & Absensi dari Cuti/Dinas approved.
Idempoten, TIDAK menyentuh kuota. Ada konfirmasi sebelum jalan.

php artisan absensi:backfill-cuti-dinas

Audit menit_terlambat & melebihi_toleransi_bulanan (tindak lanjut
bug fase 14). Default DRY-RUN.

php artisan absensi:audit-menit-terlambat
php artisan absensi:audit-menit-terlambat --instansi=1
php artisan absensi:audit-menit-terlambat --fix # BACKUP DB DULU


### JANGAN jalankan `afterApprove()` massal

Snippet lama (fase 10) yang memanggil `afterApprove()` dalam loop sudah
TIDAK aman. Sejak itu perilakunya berubah:

- **fase 21** — juga `updateOrCreate` Jadwal dan SENGAJA menimpa baris
  bersumber `manual`. Jalan massal = semua edit manual admin ketimpa.
- **fase 22** — menaikkan `KuotaCuti.terpakai`. Jalan massal = kuota
  terhitung ganda. Kalau di tengah loop ada yang melewati kuota,
  `KuotaCutiTidakCukupException` dilempar dan loop berhenti di tengah:
  sebagian ter-increment, sebagian tidak, tanpa rollback menyeluruh
  (transaksinya per-record, bukan per-loop).

Kalau memang perlu, jalankan untuk SATU record dan pastikan dulu
`KuotaCuti.terpakai` belum mencerminkan cuti itu. Backup DB dulu.
Dinas lebih kecil risikonya (tidak menyentuh kuota), tapi penimpaan
Jadwal manual tetap berlaku.

## Deploy pertama

Checklist yang gampang terlewat:

1. `php artisan absensi:audit-menit-terlambat --fix` kalau ada data dari
   sebelum fase 27 — kolom `melebihi_toleransi_bulanan` tidak pernah
   tersimpan sejak fase 9, jadi SEMUA baris lama salah di kolom itu.
2. Backfill `KuotaCuti.terpakai` (di bawah) kalau ada Cuti approved dari
   sebelum fase 42.

### Backfill KuotaCuti.terpakai (fase 42)

BACKUP DB DULU.

Ketiga query memperhitungkan `kuota_cutis.semester`, dan logikanya
MENYALIN `JenisCuti::semesterDari()`: `0` untuk jenis tahunan, dan
untuk `periode_kuota = 'semesteran'` → bulan 1–6 = semester 1, 7–12 =
semester 2. SQL tidak bisa memanggil method PHP, jadi ini duplikasi
yang disengaja — **kalau `semesterDari()` berubah, ketiga query di
bawah harus ikut diubah.**

Unique `kuota_cutis` ada 4 kolom (karyawan_id, jenis_cuti_id, tahun,
semester), jadi pengelompokannya wajib ikut semester. Tanpa itu dua
semester tergabung jadi satu baris dan angkanya salah.

⚠️ **Cuti yang melintasi batas semester** (mis. 28 Jun–3 Jul) dihitung
seluruhnya ke semester `tanggal_mulai`, karena `semesterDari()` hanya
menerima satu tanggal dan `afterApprove()` memanggilnya dengan
`tanggal_mulai`. Konsisten dengan produksi, tapi kalau ada kasus
seperti itu di data lama, periksa manual.

**1. Cek selisih — tinjau manusia sebelum lanjut**, terutama baris yang
`terpakai_sekarang > seharusnya` (kemungkinan koreksi manual admin yang
akan tertimpa).

```sql
SELECT k.id, k.karyawan_id, k.jenis_cuti_id, k.tahun, k.semester,
       k.terpakai AS terpakai_sekarang,
       COALESCE(a.total, 0) AS seharusnya
FROM kuota_cutis k
JOIN jenis_cutis j ON j.id = k.jenis_cuti_id AND j.potong_kuota = 1
LEFT JOIN (
    SELECT c.karyawan_id, c.jenis_cuti_id,
           YEAR(c.tanggal_mulai) AS thn,
           CASE WHEN j2.periode_kuota = 'semesteran'
                THEN IF(MONTH(c.tanggal_mulai) <= 6, 1, 2)
                ELSE 0 END AS smt,
           SUM(c.jumlah_hari) AS total
    FROM cutis c
    JOIN jenis_cutis j2 ON j2.id = c.jenis_cuti_id
    WHERE c.status = 'approved'
    GROUP BY c.karyawan_id, c.jenis_cuti_id, YEAR(c.tanggal_mulai), smt
) a ON a.karyawan_id = k.karyawan_id
   AND a.jenis_cuti_id = k.jenis_cuti_id
   AND a.thn = k.tahun
   AND a.smt = k.semester
WHERE k.terpakai <> COALESCE(a.total, 0);
```

**2. Isi row yang belum ada**

```sql
INSERT IGNORE INTO kuota_cutis
    (karyawan_id, jenis_cuti_id, tahun, semester, kuota, terpakai, created_at, updated_at)
SELECT c.karyawan_id,
       c.jenis_cuti_id,
       YEAR(c.tanggal_mulai),
       CASE WHEN j.periode_kuota = 'semesteran'
            THEN IF(MONTH(c.tanggal_mulai) <= 6, 1, 2)
            ELSE 0 END AS smt,
       j.default_kuota,
       SUM(c.jumlah_hari),
       NOW(), NOW()
FROM cutis c
JOIN jenis_cutis j ON j.id = c.jenis_cuti_id
WHERE c.status = 'approved' AND j.potong_kuota = 1
GROUP BY c.karyawan_id, c.jenis_cuti_id, YEAR(c.tanggal_mulai), smt;
```

`j.default_kuota` dipakai apa adanya untuk baris semesteran juga —
sama seperti `KuotaCuti::pastikanUntuk()` di produksi, yang tidak
membagi dua. Artinya jenis cuti semesteran dengan `default_kuota = 12`
berarti 12 hari PER SEMESTER, total 24 setahun. Query ini sengaja
mencerminkan perilaku produksi, bukan memperbaikinya — kalau angka itu
ternyata salah, yang perlu diperbaiki `afterApprove()`, bukan backfill
ini.

**3. Perbaiki row yang terpakai-nya tidak cocok** (setelah meninjau
hasil query 1)

```sql
UPDATE kuota_cutis k
JOIN jenis_cutis j ON j.id = k.jenis_cuti_id AND j.potong_kuota = 1
JOIN (
    SELECT c.karyawan_id, c.jenis_cuti_id,
           YEAR(c.tanggal_mulai) AS thn,
           CASE WHEN j2.periode_kuota = 'semesteran'
                THEN IF(MONTH(c.tanggal_mulai) <= 6, 1, 2)
                ELSE 0 END AS smt,
           SUM(c.jumlah_hari) AS total
    FROM cutis c
    JOIN jenis_cutis j2 ON j2.id = c.jenis_cuti_id
    WHERE c.status = 'approved'
    GROUP BY c.karyawan_id, c.jenis_cuti_id, YEAR(c.tanggal_mulai), smt
) a ON a.karyawan_id = k.karyawan_id
   AND a.jenis_cuti_id = k.jenis_cuti_id
   AND a.thn = k.tahun
   AND a.smt = k.semester
SET k.terpakai = a.total, k.updated_at = NOW()
WHERE k.terpakai <> a.total;
```
