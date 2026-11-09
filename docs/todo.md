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
commit `584d2e8`). **TukarJadwal × rotasi beda tanggal SELESAI** (tukar beda tanggal untuk rotasi sekarang diizinkan kalau sisi existing-nya libur, baris libur diserap saat approve — lihat CHANGELOG. Batasan yang diterima: kalau kedua sisi sama-sama shift kerja, tukar beda tanggal tetap ditolak, butuh pihak ketiga nutup shift, di luar scope tukar 2-arah).

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

- [ ] **Nilai `data['tipe']` tidak pernah diuji** — helper
      `buatNotifikasi()` di test notifikasi memakai `'tipe' => 'warning'`,
      padahal `AbsenTerlambat` yang asli mengirim `'tipe' => 'terlambat'`
      dan `BelumAbsen` mengirim nilainya sendiri. Jadi test selama ini
      menguji nilai yang tidak pernah ada di produksi. Tidak merusak apa
      pun (`NotifikasiController` generik, tidak membaca `tipe`), tapi
      artinya tidak ada yang mengunci field yang justru dipakai FE buat
      memilih ikon/warna. Tambahkan assertion `tipe` di test
      `AbsenTerlambat`/`BelumAbsen`. Ditemukan 9 Nov 2026.

## Fitur & infrastruktur

- [ ] **Import karyawan via Excel + template download** — admin
      mengunduh template .xlsx, mengisinya, lalu mengunggah balik.
      Tanpa ini, menginput ~150 karyawan RS lewat form Filament satu
      per satu adalah pekerjaan berjam-jam, dan itu terjadi tepat saat
      deploy pertama — momen paling sibuk.

      Infrastruktur sudah ada: `maatwebsite/excel` sudah dipakai untuk
      Export Laporan Presensi (fase 56), jadi tinggal pakai sisi
      import-nya.

      Kolom yang perlu di template: nip, nama, email, nomor_telepon,
      status_pegawai, tipe_jadwal, unit_kerja, jabatan,
      tanggal_bergabung. Password TIDAK di template — perlu diputuskan
      apakah di-generate otomatis lalu dikirim, atau diseragamkan dan
      dipaksa ganti saat login pertama.

      Yang harus hati-hati, dan ini bagian yang gampang diremehkan:

      - **`tipe_jadwal` wajib eksplisit di template**, tidak boleh
        kosong lalu ditebak dari unit_kerja/jabatan — itu larangan
        eksplisit di QUICK_CONTEXT, dan import massal adalah tempat
        paling mungkin orang tergoda menebaknya.
      - **Validasi harus per-baris dengan laporan yang bisa dibaca
        admin**, bukan gagal total di baris pertama. Admin RS tidak
        punya terminal; kalau 150 baris ditolak tanpa tahu baris mana
        yang salah, fitur ini malah lebih buruk daripada input manual.
      - **nip & email unique** — baris duplikat di dalam file ITU
        SENDIRI juga harus ketahuan, bukan cuma bentrok dengan
        database.
      - **Import harus atomic atau punya mode dry-run.** Gagal di
        baris 120 setelah 119 karyawan terlanjur masuk itu keadaan
        yang sulit dibereskan admin.
      - `instansi_id` di-scope, bukan kolom template (admin tidak
        seharusnya bisa memasukkan karyawan ke instansi lain).

      Pertimbangkan juga: apakah import ini sekalian bisa mengisi
      `KaryawanShift` (untuk tipe umum) atau `KaryawanPolaRotasi`
      (untuk rotasi)? Kalau tidak, admin tetap harus menginput
      assignment shift 150 kali — dan itu memindahkan masalah, bukan
      menyelesaikannya. Dicatat 9 Nov 2026.
      


- [ ] **Login pakai username, bukan email** — `AuthController` sekarang
      autentikasi lewat email. Karyawan RS belum tentu punya atau ingat
      email kerjanya, sementara NIP/username lebih dekat ke identitas
      yang sudah mereka pakai sehari-hari. Menyentuh: kolom baru di
      `karyawans` (+ migration + `SCHEMA.md`), unique constraint,
      validasi `AuthController`, Filament Resource Karyawan (field
      input + kolom tabel + pencarian), seeder/factory, dan test auth.
      Perlu diputuskan dulu: username bebas atau pakai NIP yang sudah
      ada? Email tetap dipertahankan untuk notifikasi/reset password,
      atau ikut dibuang? Migrasi data lama bagaimana (belum ada
      production, jadi sekarang justru waktu termurah untuk
      mengubahnya). Diusulkan 6 Nov 2026 saat audit FE login_screen.

- [ ] **`rekap()` tidak pernah mengirim `sakit`** — field-nya ada di
      model `RekapAbsen` di FE dan ditampilkan di kartu rekap Riwayat,
      jadi angkanya permanen 0 bagi semua orang. Nyambung ke Known Gap
      #5 (enum `absensi.status` punya opsi `'sakit'` yang belum jelas
      peruntukannya). Keputusan: backend mulai mengisinya, atau FE
      menghapusnya dari kartu. Ditemukan saat audit FE 6 Nov 2026.
      
- [ ] **Notifikasi admin di panel Filament** — belum ada apa pun yang
      memberi tahu admin bahwa ada pengajuan menunggu approval, di
      KELIMA modul (Cuti, Izin, Lembur, Dinas, TukarJadwal). Admin harus
      ingat sendiri membuka menunya. TukarJadwal paling rawan karena
      punya dua tahap tunggu: mode pindah langsung masuk
      `menunggu_admin`, mode tukar masuk ke sana setelah rekan setuju —
      kalau tidak dilihat, pengajuan mengendap sampai tanggalnya lewat.

      Prasyarat: `->databaseNotifications()` belum ada di
      `AdminPanelProvider` (dicek 9 Nov 2026, kosong), jadi lonceng
      panelnya sendiri belum aktif.

      Perlu diputuskan dulu: dikirim ke SIAPA? Tidak ada konsep "admin
      penanggung jawab" — kandidatnya semua `User`, atau `User` yang
      terkait instansi tertentu. Resource lain sudah scoped per
      instansi, kemungkinan besar harus ikut. Nyambung ke Known Gap #3
      (`Karyawan.role='admin'` tidak kepakai di alur approval; penerima
      notifikasi ini `User`, bukan `Karyawan` — jalur terpisah dari
      notifikasi karyawan yang dibaca lewat `NotifikasiController`).

      Sengaja TIDAK dikerjakan bersama notifikasi TukarJadwal untuk
      karyawan (9 Nov 2026): yang itu blocker FE, yang ini tidak
      memblokir siapa pun, dan mengerjakan TukarJadwal saja justru bikin
      tidak konsisten dengan 4 modul approval lain.

- [ ] **Model + endpoint Informasi/Pengumuman** — frontend sudah punya tab
      "Informasi" di bottom nav (absensi_frontapp, commit [isi setelah
      commit]) tapi masih empty state karena backend belum punya apa pun.
      Perlu: model `Informasi` (judul, isi, tanggal terbit, instansi,
      mungkin lampiran/gambar), Filament Resource buat admin bikin
      pengumuman, dan endpoint `GET /api/informasi` buat karyawan.
      Pertimbangkan scoping per instansi seperti resource lain. Referensi
      tampilan: kartu pengumuman di aplikasi lama (judul + tanggal dibuat).
      Dicatat 5 Nov 2026.

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
