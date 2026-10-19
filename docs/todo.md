# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — belum ditetapkan

**Review UX 17 Filament Resource SELESAI** (fase 25–36). **Laporan Presensi
SELESAI** (fase 56: Harian/Bulanan/Pelanggaran, Excel+PDF). **Simulasi
rotasi 24/7 realistis SELESAI** (fase 56: tim per shift, bukan 1 tim muter
3 shift). **Refactor tampilan 3 halaman Laporan Presensi jadi card Filament
SELESAI** (fase 56 lanjutan, commit `584d2e8`).

Fokus berikutnya:

- **Lanjut ke frontend.** Backend sekarang jauh lebih solid daripada saat
  frontend di-freeze di fase 5, dan sekarang juga sudah ada laporan admin
  yang jalan di atasnya.

Catatan: keputusan bisnis yang masih menggantung (alur Izin, override
TukarJadwal, `is_cuti_bersama`) butuh masukan dari pihak RS, bukan kerja
teknis — bisa ditanyakan paralel dengan apa pun yang dipilih.

## Bug aktif yang sudah teridentifikasi


## Kebutuhan masukan dari pihak RS

- [ ] **Proporsi staf per unit/tim rotasi** — `SimulasiMassalSeeder` (fase 56)
      membagi rata 120 karyawan rotasi ke 3 unit (Rawat Inap/IGD/ICU) dan rata
      lagi ke 3 tim shift per unit. Belum tentu realistis (Rawat Inap biasanya
      butuh lebih banyak staf daripada ICU) — baru relevan kalau data dummy ini
      mulai dipakai buat demo/keputusan ke pihak RS, bukan sekadar testing
      internal.

## Simulasi & data

- [x] ~~Simulasi karyawan rotasi yang punya langkah LIBUR di polanya~~ — selesai
      (fase 43/50), lihat CHANGELOG untuk detail.
- [x] ~~Simulasi massal 150 karyawan × 3 bulan~~ — selesai (fase 49), lalu
      di-redesign jadi tim-per-shift 24/7 yang realistis (fase 56).
- [ ] **TukarJadwal × hari libur rotasi** — masih belum ditelusuri. Seeder
      simulasi massal tidak membuat pengajuan TukarJadwal sama sekali, jadi
      tidak membantu area ini. Masih terkait keterbatasan fase 11.

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] Verifikasi tipe_jadwal di data production asli (nanti kalau udah deploy)

## item yang saya temukan tapi sengaja nggak difix sekarang

- [ ] **Perbandingan `jam_kembali <= jam_keluar` di `IzinController::isiJamKembali()` rapuh** —
      `$request->jam_kembali` formatnya H:i (dari input), `$izin->jam_keluar`
      formatnya H:i:s (dari DB, kolom TIME tidak di-cast). Perbandingan string
      lexicographic kebetulan masih benar untuk kasus umum, tapi harusnya pakai
      Carbon::parse() di kedua sisi biar eksplisit dan nggak rapuh ke depannya.
      Ditemukan saat kerjain item "format jam Izin & Lembur belum seragam".
      
## UX yang tersisa

- [ ] **"Shift Karyawan Umum" & "Shift Karyawan Rotasi" tidak lagi
      bersebelahan di menu** — fase 15 sengaja menaruh keduanya berdampingan
      supaya jelas ini pasangan untuk 2 tipe_jadwal; reorganisasi sidebar
      fase 20 memisahkannya ke grup berbeda tanpa keputusan tertulis.
      Status: separuh tertutup di fase 31 & 33 — kedua form sekarang punya
      helper text yang menyebut nama menu DAN grup pasangannya secara
      eksplisit. Sisa: keputusan apakah kedua menu disatukan lagi dalam satu
      grup.

- [ ] **Anchor preview siklus belum bisa dipilih di halaman Pola Rotasi** —
      preview 14 hari di sana selalu menganggap siklus dimulai HARI INI,
      padahal anchor sebenarnya `tanggal_mulai` per karyawan. Di halaman Shift
      Karyawan Rotasi sudah benar (fase 33). Ide: dropdown "lihat sebagai
      karyawan X" di infolist Pola Rotasi — perhitungannya sudah siap,
      `PolaRotasi::hitungPreviewSiklus()` menerima parameter `$mulai`.

- [ ] **Ikon reorder di Repeater Pola Rotasi** — 3 ikon (↕️⬆️⬇️) yang
      fungsinya mirip-mirip. Bawaan Filament, jadi opsinya antara mengatur
      ulang lewat konfigurasi Repeater atau menerima apa adanya. Perlu
      dilihat langsung di browser dulu.
      Sekalian worth dites ke user asli (admin awam, bukan developer),
      termasuk apakah preview siklus yang baru benar-benar membantu.


## Fitur & infrastruktur


- [ ] Frontend scan QR + GPS + face gesture (html5-qrcode, Geolocation API,
      face-api.js/MediaPipe) — masih rencana
- [ ] Scheduler otomatis untuk jadwal:generate-bulanan (masih manual)
- [ ] Tambah accessor `Lembur::getDurasiMenitAttribute()` (computed, bukan
      kolom DB) kalau nanti ada kebutuhan laporan/payroll lembur — aware
      kasus lintas tengah malam (jam_selesai < jam_mulai → +1 hari).

## Ditunda — Sinkronisasi Frontend
Frontend (Flutter, `absensi_frontapp`) freeze sejak ~fase 5 (auth, absensi,
notifikasi, riwayat sudah ada). Sengaja belum disentuh selama backend masih
sering berubah — hindari kerja dobel.

Modul backend yang BELUM ada implementasi frontend-nya sama sekali:
- Cuti/Izin/Lembur/Dinas (API sejak fase 19)
- Tukar Jadwal (API sejak fase 20)
- Laporan Presensi (fase 56) — ini fitur admin panel, kemungkinan memang
  tidak perlu versi frontend/mobile sama sekali, tapi dicatat di sini biar
  tidak terlewat kalau ternyata dibutuhkan.

Saat mulai lagi nanti: cek dulu apakah response format API sudah berubah dari
yang diasumsikan kode frontend existing (auth/absensi/notifikasi), sebelum
nambah modul baru. Yang sudah PASTI berubah sejak fase 5: format `jam_masuk`
di `/api/auth/me` & `/api/absensi/riwayat` (fase 27 — dulu timestamp ISO,
sekarang `H:i`), dan struktur `data.records` di notifikasi (fase 24).
