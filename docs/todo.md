# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas

**Sedang dikerjakan:** Laporan Presensi (export Excel) — Filament Page baru
dengan 3 tab: Harian (per-shift), Rekap Bulanan (per-karyawan), Rekap
Pelanggaran. Mulai dari Harian dulu. PDF menyusul setelah Excel beres.

**Berikutnya:** lanjut ke frontend (Flutter, freeze sejak fase 5) — backend
sekarang jauh lebih solid. Cek dulu breaking change format API sebelum
nambah modul baru (lihat bagian "Ditunda — Sinkronisasi Frontend").

## Sebelum Deploy Production

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix`** kalau nanti ada data
      production dari sebelum fase 27 — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu.
- [ ] **`absensi:audit-menit-terlambat` belum punya test otomatis** —
      kemampuan deteksi & jalur `--fix` sudah dibuktikan manual sekali, tapi
      tidak meninggalkan jejak di suite. Yang paling rawan: replay akumulasi
      bulanan lintas chunk (state dibawa lewat reference).
- [ ] Verifikasi `tipe_jadwal` di data production asli.

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
      fungsinya mirip-mirip. Bawaan Filament, opsinya antara mengatur ulang
      lewat konfigurasi Repeater atau menerima apa adanya. Perlu dilihat
      langsung di browser dulu.
- [ ] **Format jam di respons Izin & Lembur belum seragam** — `jam_keluar`/
      `jam_kembali` (Izin) dan `jam_mulai`/`jam_selesai` (Lembur) dikirim apa
      adanya dari DB sebagai `"08:00:00"`, sementara respons lain memakai
      `H:i`. Bukan bug, cuma tidak seragam. Hati-hati: menambahkan
      `->format()` di sana SALAH karena nilainya bukan Carbon.

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

Saat mulai lagi nanti: cek dulu apakah response format API sudah berubah dari
yang diasumsikan kode frontend existing (auth/absensi/notifikasi), sebelum
nambah modul baru. Yang sudah PASTI berubah sejak fase 5: format `jam_masuk`
di `/api/auth/me` & `/api/absensi/riwayat` (fase 27 — dulu timestamp ISO,
sekarang `H:i`), dan struktur `data.records` di notifikasi (fase 24).
