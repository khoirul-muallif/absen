# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — belum ditetapkan

**Review UX 17 Filament Resource SELESAI** (fase 25–36). Test 229 → 457.
Detail & ringkasan pola bug yang berulang ada di CHANGELOG.

Fokus berikutnya belum diputuskan. Tiga kandidat, urut dari yang paling
mendesak menurut isi todo sekarang:

- **(A) Tutup bug aktif yang sudah teridentifikasi.** Ada beberapa yang jelas
  merugikan dan analisisnya sudah lengkap: PengingatBelumAbsen (guard
  libur/cuti, shift malam, rotasi tak pernah dapat notifikasi), celah
  akumulatif KuotaCuti, dan `is_cuti_bersama` yang bikin generator salah
  menandai libur. Semua ada di bagian "Bug aktif" di bawah.

- **(B) Simulasi & data dummy yang realistis.** Rotasi ber-libur belum pernah
  dicoba sama sekali, dan itu memblokir verifikasi beberapa item di (A).
  Termasuk memperbaiki AbsensiSimulasiSeeder.

- **(C) Lanjut ke frontend.** Backend sekarang jauh lebih solid daripada saat
  frontend di-freeze di fase 5 — tapi (A) menyentuh perilaku yang dilihat
  karyawan, jadi mengerjakannya duluan menghindari kerja dobel.

Catatan: keputusan bisnis yang masih menggantung (enum 'sakit', KuotaCuti
semesteran, alur Izin, override TukarJadwal) semuanya butuh masukan dari pihak
RS, bukan kerja teknis — bisa ditanyakan paralel dengan apa pun yang dipilih.

## Bug aktif yang sudah teridentifikasi

- [ ] **PengingatBelumAbsen/PengingatBelumAbsenPulang: 3 bug aktif**
      (ditemukan fase 26 lanjutan, yang diperbaiki baru cast-nya):
      - Tidak ada guard libur sama sekali: tidak cek shift->hari_kerja,
        HariLibur, maupun Cuti/Dinas approved. Karyawan yang sedang cuti
        approved TETAP dikirimi "Anda belum absen masuk" — barisnya ada tapi
        waktu_masuk null. Sama untuk Sabtu/Minggu & libur nasional.
        RekapHarian sudah benar cek hari_kerja, command ini tidak.
      - Shift malam rusak di PengingatBelumAbsenPulang: batas notifikasi
        dihitung dari jam pulang HARI INI yang sudah lewat, jadi notifikasi
        "belum absen pulang" terkirim ~15 menit setelah karyawan absen MASUK.
      - Karyawan rotasi TIDAK PERNAH dikirimi notifikasi sama sekali —
        command query dari KaryawanShift, yang sejak fase 13 cuma dimiliki
        karyawan umum. Jadi rotasi yang benar-benar lupa absen pun tidak
        diingatkan.
      - Kedua command masih tanpa test sama sekali.

- [ ] **`is_cuti_bersama` bikin generator salah menandai libur** — kebijakan
      RS: saat cuti bersama karyawan TETAP MASUK, yang ingin libur mengajukan
      cuti biasa. Tapi GenerateJadwalBulanan, GenerateJadwalRotasi, dan
      RekapHarian memperlakukan baris ini sama persis seperti libur nasional;
      flag `is_cuti_bersama` tidak dibaca di satu tempat pun. Akibatnya
      karyawan umum tercatat libur padahal seharusnya masuk, dan yang tidak
      masuk tidak tertangkap sebagai alpha. Karyawan rotasi unit 24 jam
      selamat lewat flag pola; karyawan umum tidak punya perlindungan setara.
      Menyentuh 3 command sekaligus. Sementara ini form Hari Libur
      memperingatkan supaya cuti bersama JANGAN didaftarkan dulu.

- [ ] **Fix AbsensiSimulasiSeeder** — `waktu_masuk` di-anchor ke tanggal
      baris, bukan `now()`. Sekarang semua row simulasi punya
      `DATE(waktu_masuk) != tanggal` (lihat CHANGELOG fase 26 lanjutan).
      Sekalian tambah assertion/test kecil yang mengunci
      `DATE(waktu_masuk) == tanggal` supaya tidak kambuh.

- [ ] **Verifikasi GenerateJadwalRotasi menyaring tanggal di luar masa berlaku
      assignment** — `posisiSiklusPada()` sekarang menormalisasi tanggal
      sebelum anchor jadi posisi yang konsisten (fase 33), tapi posisi untuk
      tanggal di luar masa berlaku memang tidak punya arti bisnis.
      `KaryawanPolaRotasi::berlakuPada()` sudah tersedia untuk menyaringnya —
      perlu dicek apakah generator sudah memakainya, atau masih bisa
      menghasilkan jadwal untuk tanggal sebelum assignment berlaku.

## Kebutuhan masukan dari pihak RS

- [ ] **Keputusan enum `absensi.status = 'sakit'`** — muncul di dropdown form
      & filter tabel seolah fitur yang hidup, padahal tidak ada
      modul/controller yang pernah menghasilkannya. Pilihan: (a) entri manual
      admin yang sah, (b) digabung ke alur Cuti sebagai jenis cuti sakit,
      (c) dead value, sembunyikan dari UI.
      Pertanyaan yang menentukan: di rekap bulanan (GET /api/absensi/rekap),
      apakah "sakit" perlu tampil sebagai baris sendiri, atau cukup masuk
      hitungan cuti? Endpoint itu SUDAH menghitungnya terpisah — jadi kalau
      jawabannya "cukup masuk cuti", barisnya di rekap juga perlu dihapus.

- [ ] **KuotaCuti: dukung periode per 6 bulan + tutup celah "row belum ada"**
      — dua kebutuhan yang digabung karena menyentuh keputusan yang sama:
      apakah row KuotaCuti boleh terus-menerus tidak ada.

      (a) Periode semesteran. Sekarang kuota selalu per tahun kalender penuh.
      Kemungkinan butuh kolom `periode_kuota` di jenis_cutis, dan KuotaCuti
      butuh identifikasi periode dalam tahun yang sama (kolom `semester`, atau
      ganti ke `periode_mulai`/`periode_selesai` eksplisit) — pilihan ini
      pengaruh besar ke unique constraint & migration data existing.
      Titik query yang harus direvisi tinggal 3 setelah sentralisasi fase 26:
      `KuotaCuti::untuk()`/`sisaUntuk()`, `Cuti::hariPendingUntuk()`, dan
      `Cuti::afterApprove()`.
      Perlu dijawab: per-JenisCuti atau global? Untuk jenis cuti BARU yang
      memang semesteran, atau Cuti Tahunan yang SUDAH ADA diubah?

      (b) Celah akumulatif saat row KuotaCuti belum ada (fase 26). Selama row
      belum pernah dibuat, `afterApprove()` tidak pernah menaikkan `terpakai`,
      jadi cuti approved tidak pernah masuk hitungan. Fallback `default_kuota`
      di CutiController cuma mengurangi pengajuan PENDING — artinya karyawan
      bisa ajukan 12 hari, approve, ajukan 12 hari lagi, approve, tanpa batas
      sepanjang tahun. Solusi akar: `firstOrCreate` row dari `default_kuota`.
      SENGAJA ditunda karena kunci row-nya akan berubah di (a).
      **Kalau (a) diputuskan tidak jadi dikerjakan, (b) tetap harus
      diselesaikan sendiri — ini celah nyata, bukan kerapian.**

- [ ] **Izin: rethink alur approval + endpoint jam_kembali** — izin keluar
      sementara itu darurat/insidental, tidak realistis menunggu approval
      admin dulu. Draft: auto-approved saat diajukan, endpoint baru untuk
      karyawan mengisi jam_kembali sendiri, plus riwayat siapa/kapan
      mengubahnya. Perlu dicek apakah approval masih relevan untuk review
      retroaktif.
      Catatan fase 25: karena alasan struktural ini, `EditAction` di
      IzinsTable/ViewIzin SENGAJA tidak dibatasi ke status pending — supaya
      jam_kembali masih bisa diisi manual sampai endpoint khusus dikerjakan.

- [ ] **TukarJadwal: admin butuh override saat status `menunggu_rekan` macet**
      — kalau rekan tidak kunjung merespons (lupa, cuti, resign), admin tidak
      punya cara membatalkan atau memaksa lanjut lewat Filament; pengajuan
      macet permanen. Draft: action "Batalkan" khusus status menunggu_rekan,
      atau expiry otomatis lewat scheduler. Perlu diputuskan apakah pembatalan
      butuh baris catatan/log terpisah.

## Simulasi & data

- [ ] **Simulasi karyawan rotasi yang punya langkah LIBUR di polanya
      (mis. 5 hari kerja/minggu)** — belum pernah dicoba sama sekali, baik
      manual maupun di test. Seeder rotasi sekarang kerja nonstop 15 hari
      TANPA satu pun langkah libur, jadi seluruh jalur "karyawan rotasi sedang
      libur" tidak pernah dilewati. Secara struktur SUDAH didukung.
      Yang perlu dicek kalau dikerjakan:
      - `AbsensiController::masuk()` untuk rotasi dengan Jadwal yang ADA tapi
        shift_id null (libur) — pesannya jelas atau malah error? Test yang ada
        cuma cover "rotasi tanpa Jadwal".
      - Cuti/Dinas approved menimpa Jadwal libur dan tetap memotong kuota
        untuk hari itu. Untuk karyawan umum tidak masalah (generator skip
        non-hari_kerja), tapi rotasi bisa "buang" jatah cuti di hari yang
        memang liburnya. Keputusan bisnis, belum pernah dibahas.
      - TukarJadwal yang melibatkan hari libur rotasi — terkait keterbatasan
        fase 11.
      - Tambah 1 pola + 1 karyawan rotasi ber-libur ke seeder supaya bisa
        diklik manual di admin panel.

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
