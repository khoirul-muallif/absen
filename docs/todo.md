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

- [ ] **Pesan validasi Laravel tampil sebagai kunci mentah** — locale
      aplikasi `id`, dan `lang/id/validation.php` tidak ada, jadi
      `trans('validation.required')` mengembalikan string
      `"validation.required"` apa adanya. SETIAP pesan validasi yang
      sampai ke karyawan berbentuk kunci mentah: karyawan yang lupa
      mengisi alasan cuti melihat "validation.required" di layar HP-nya.

      Lebih parah daripada kebocoran terjemahan Filament (fase 75)
      karena ini kena ke 150 pengguna aplikasi mobile, bukan ke satu
      admin.

      Beda dari kasus Filament: Laravel memang TIDAK menyertakan `lang/`
      sejak v9 dan tidak punya terjemahan `id` sama sekali, jadi tidak
      ada perbaikan upstream yang akan menyusul — file ini memang harus
      dirawat sendiri. Tidak ada risiko "override menimpa upstream"
      seperti di Filament, jadi tidak perlu pendekatan override
      per-kunci.

      Aturan yang benar-benar dipakai di API (hasil pindai 9 Nov):
      required, required_if, string, integer, boolean, date,
      date_format, after, after_or_equal, in, exists, different, image,
      max, nullable. Filament menambah unique, email, min, numeric, dan
      lainnya — file ini sebaiknya mencakup keduanya, bukan cuma API.

      Dua opsi: `php artisan lang:publish` lalu terjemahkan manual, atau
      ambil terjemahan komunitas (`laravel-lang/lang`) yang menambah
      dependensi. Ditemukan 9 Nov 2026 saat memperbaiki test auth —
      muncul sebagai `"username": ["validation.required"]` di output
      test.

- [ ] **Perjelas definisi libur mingguan vs libur nasional** — sudah
      beberapa kali jadi bug diam-diam, terutama di TukarJadwal.
      Masalahnya satu kata `libur` dipakai untuk dua hal yang berbeda
      sifatnya, dan kode tidak bisa membedakannya.

      Yang sudah terlihat janggal dari dokumen (BELUM diverifikasi ke
      source, ini daftar tempat yang perlu dibuka):

      1. **Karyawan umum vs rotasi tidak simetris.** Generator bulanan
         SKIP hari yang bukan `hari_kerja` shift — jadi akhir pekan
         karyawan umum TIDAK punya baris `jadwals` sama sekali.
         Sementara karyawan rotasi dengan langkah LIBUR di polanya
         DAPAT baris `jenis='libur'`. Dua keadaan yang secara bisnis
         sama ("hari libur mingguan saya") terwakili berbeda di DB.

      2. **Libur nasional justru digenerate eksplisit** sebagai
         `jenis='libur'` — jadi baris `libur` di `jadwals` bisa berarti
         libur mingguan rotasi ATAU libur nasional, tanpa cara
         membedakannya. Satu-satunya pembeda ada di tabel lain
         (`hari_liburs`), yang tidak pernah dicek TukarJadwal.

      3. **TukarJadwal memperlakukan semua `JENIS_LIBUR` sama.**
         `ajukanPindah()` mengizinkan pindah ke tanggal yang barisnya
         libur, dan `approveAndSwap()` MENYERAP baris libur itu
         (ditimpa/dihapus). Kalau baris libur itu ternyata libur
         nasional, karyawan jadi bekerja di hari libur nasional tanpa
         ada yang memberi tahu siapa pun. Untuk karyawan umum kasusnya
         beda lagi: akhir pekannya tidak punya baris, jadi masuk cabang
         "tidak ada baris sama sekali, tinggal dipindah" — lolos tanpa
         pengecekan apa pun.

      4. **`PolaRotasi.berlaku_saat_libur_nasional`** sudah mengakui
         bahwa dua hal ini berbeda, tapi hanya di generator rotasi.
         TukarJadwal tidak punya padanannya.

      Pertanyaan yang perlu dijawab sebelum menulis kode:
      - Apakah karyawan boleh menukar/memindah jadwal KE hari libur
        nasional? (kemungkinan iya untuk RS yang jalan 24/7, tapi harus
        eksplisit, bukan kebetulan lolos)
      - Apakah libur mingguan karyawan umum perlu punya baris `jadwals`
        sendiri supaya simetris dengan rotasi? Itu menambah ~8 baris per
        karyawan per bulan, tapi menghilangkan dua cabang kode berbeda.
      - Perlukah `jadwals.jenis` dipecah (`libur_mingguan` vs
        `libur_nasional`), atau cukup TukarJadwal mengecek
        `hari_liburs`?

      Hati-hati: ini kelas masalah yang sama dengan keputusan "tidak
      menambahkan cek jadwal/libur di client" (CHANGELOG fase 43) dan
      dengan temuan tertunda "tombol Ajukan Tukar/Pindah muncul juga
      untuk cuti/dinas/libur" (status-06-11-26.md). Garap bertiga
      sekaligus, jangan satu-satu. Dicatat 9 Nov 2026.

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
