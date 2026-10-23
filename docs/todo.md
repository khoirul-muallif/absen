# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — belum ditetapkan

**Review UX 17 Filament Resource SELESAI** (fase 25–36). **Laporan Presensi
SELESAI** (fase 56: Harian/Bulanan/Pelanggaran, Excel+PDF). **Simulasi
rotasi 24/7 realistis SELESAI** (redesign terakhir fase 65: rotasi individu
per unit, bukan tim tetap per shift — lihat CHANGELOG). **Refactor tampilan
3 halaman Laporan Presensi jadi card Filament SELESAI** (fase 56 lanjutan,
commit `584d2e8`). **TukarJadwal × rotasi SELESAI** (fase 65 — validasi lama
terbukti cukup, tidak perlu pembatasan tambahan).

Fokus berikutnya:

- **Lanjut ke frontend.** Backend sekarang jauh lebih solid daripada saat
  frontend di-freeze di fase 5, dan sekarang juga sudah ada laporan admin
  yang jalan di atasnya.

Catatan: keputusan bisnis yang masih menggantung (alur Izin, override
TukarJadwal, `is_cuti_bersama`) butuh masukan dari pihak RS, bukan kerja
teknis — bisa ditanyakan paralel dengan apa pun yang dipilih.


## Kebutuhan masukan dari pihak RS

- [ ] **Proporsi staf per unit/tim rotasi** — `SimulasiMassalSeeder`
      membagi rata 120 karyawan rotasi ke 3 unit (Rawat Inap/IGD/ICU).
      Belum tentu realistis (Rawat Inap biasanya butuh lebih banyak staf
      daripada ICU) — baru relevan kalau data dummy ini mulai dipakai
      buat demo/keputusan ke pihak RS, bukan sekadar testing internal.
      Catatan: sejak redesign rotasi-individu (fase 65), setiap karyawan
      rotasi dalam satu unit memakai SATU pola yang sama (bukan lagi
      dibagi ke 3 tim shift terpisah) — pembagian 40/40/40 sekarang murni
      per unit, tidak ada lagi sub-pembagian tim.

## Simulasi & data

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] Verifikasi tipe_jadwal di data production asli (nanti kalau udah deploy)

      
## UX yang tersisa

- [ ] **Kalender "Pindah ke tanggal" (TukarJadwal) tidak punya indikator
      visual kenapa tanggal di-disable** — functionally sudah benar
      (disabledDates() memblokir tanggal yang sudah punya Jadwal, baik
      kerja maupun libur — dikonfirmasi lewat Tinker 20 Okt 2026: baris
      Jadwal utk karyawan rotasi lengkap tanpa gap, termasuk hari libur
      pola rotasi), tapi admin tidak tahu APAKAH tanggal itu disabled
      karena kerja atau karena libur — cuma keabuan polos. Usulan: hitam
      = hari kerja, merah = libur, hijau = kosong/bisa dipilih. DatePicker
      Filament (Flatpickr) tidak expose pewarnaan per-tanggal secara
      native, cuma disabledDates binary — butuh custom Alpine.js/Flatpickr
      hook (onDayCreate), bukan cuma ganti parameter. Ditemukan 20 Okt 2026
      saat audit TukarJadwal x rotasi.






## Fitur & infrastruktur


- [ ] Frontend scan QR + GPS + face gesture (html5-qrcode, Geolocation API,
      face-api.js/MediaPipe) — masih rencana
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
