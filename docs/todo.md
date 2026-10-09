# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — belum ditetapkan

**Review UX 17 Filament Resource SELESAI** (fase 25–36). Test 229 → 457.
Pengingat & RekapHarian selesai ditest: suite 472 test hijau.
Detail & ringkasan pola bug yang berulang ada di CHANGELOG.

Fokus berikutnya:

- **(B) Simulasi & data dummy yang realistis.** Rotasi ber-libur belum pernah
  dicoba sama sekali, dan itu memblokir verifikasi beberapa item di (A).

- **(C) Lanjut ke frontend.** Backend sekarang jauh lebih solid daripada saat
  frontend di-freeze di fase 5.

Catatan: keputusan bisnis yang masih menggantung (alur Izin, override
TukarJadwal, `is_cuti_bersama`) butuh masukan dari pihak RS, bukan kerja
teknis — bisa ditanyakan paralel dengan apa pun yang dipilih.

## Bug aktif yang sudah teridentifikasi

- [ ] **AbsensiSimulasiSeeder: lookup shift tanpa instansi, akumulasi lintas
      bulan, dan absensi di hari libur mingguan** — invarian
      `DATE(waktu_masuk) == tanggal` sudah benar di kode saat ini (fase 27),
      sekarang dikunci test. Tiga hal di atas belum diperbaiki.

- [ ] **Konfirmasi GenerateJadwalRotasi memakai berlakuPada()** — sudah
      di-refactor di a8b4072 dan ada test (GenerateJadwalRotasiMasaBerlakuTest).
      Tinggal memastikan tidak ada jalur lain yang menghasilkan jadwal di
      luar masa berlaku assignment.

- [ ] **Pengingat pulang untuk rotasi yang Jadwal-nya berganti hari di tengah
      shift** — shift malam dan pencarian absensi dua hari sudah diuji (fase 40).
      Yang belum: kasus rotasi yang Jadwal hari T+1-nya berbeda dari hari T,
      sehingga deadline pulang yang dihitung dari `absensi.shift_id` bisa tidak
      sama dengan shift Jadwal hari ini. Perlu test dan keputusan mana yang
      jadi sumber deadline.

## Kebutuhan masukan dari pihak RS

## Simulasi & data

- [x] ~~Simulasi karyawan rotasi yang punya langkah LIBUR di polanya~~ —
      **SELESAI.** `PolaRotasiSeeder` baru (pola "Rotasi 5 Kerja 2 Libur",
      unit Rawat Inap, `berlaku_saat_libur_nasional=false`) + karyawan Yono
      Pratama di-assign lewat `KaryawanPolaRotasi`. Jadwal digenerate manual
      via `jadwal:generate-rotasi`, terverifikasi siklus 7 hari berulang
      benar. Dua temuan dari verifikasi ini:
      - `AbsensiController::masuk()`: FIXED — rotasi dengan Jadwal libur
        sebelumnya dapat pesan "Hubungi admin" (sama seperti anomali tanpa
        Jadwal sama sekali). Sekarang pesannya jelas: "Hari ini jadwal Anda
        libur, tidak perlu absen masuk." Dikunci test di AbsensiControllerTest.
        - Cuti/Dinas approved menimpa Jadwal libur rotasi & tetap memotong
        kuota penuh untuk hari itu — **DIKONFIRMASI RS (8 Okt 2026): perilaku
        ini disengaja**, bukan bug. Tetap didokumentasikan sebagai regression
        guard di CutiTest ("DOKUMENTASI: ..."), mengikuti pola is_cuti_bersama
        fase 29 — tapi sekarang statusnya final, bukan menunggu jawaban.
      - TukarJadwal yang melibatkan hari libur rotasi — belum ditelusuri,
        masih terkait keterbatasan fase 11.
      - Jalur pengingat dan RekapHarian untuk rotasi berlibur sudah dicakup
        test di fase 39. Yang tersisa hanya `AbsensiController::masuk()` untuk
        Jadwal dengan `shift_id` null, yang belum diuji.

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] **`absensi:audit-menit-terlambat` belum punya test otomatis** —
      kemampuan deteksi & jalur `--fix` sudah dibuktikan manual sekali, tapi
      tidak meninggalkan jejak di suite. Yang paling rawan dan tidak terjaga:
      replay akumulasi bulanan lintas chunk (state dibawa lewat reference).

- [ ] Verifikasi tipe_jadwal di data production asli (nanti kalau udah deploy)

## UX yang tersisa

- [ ] **Gambar QR tidak pernah ditampilkan** — menu bernama "QR Instansi" cuma
      memberi string 32 karakter yang harus disalin ke generator eksternal
      untuk dicetak (fase 36). Butuh paket tambahan (`simple-qrcode` atau
      sejenis). Sekalian pikirkan halaman cetak yang siap tempel: QR + nama
      instansi + keterangan lokasi, ukuran A5/A4.

- [ ] **Teks di admin panel masih bahasa programmer** — beberapa peringatan
      menyebut nama command CLI sebagai solusi, padahal admin RS tidak punya
      akses terminal. Tersisa di 3 tempat (HariLiburForm, JadwalInfolist,
      HariLiburInfolist); JadwalForm sudah diperbaiki di fase 30.
      Perbaikan lengkapnya: tombol aksi di Filament yang memanggil generator
      lewat `Artisan::call()`, dengan konfirmasi dan ringkasan hasil.
      Pertanyaan yang menentukan: siapa yang mengoperasikan panel ini
      sehari-hari — staf SDM awam, atau ada IT internal RS?

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
