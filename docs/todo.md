# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas

**Backend sudah jauh lebih solid.** Review UX 17 Filament Resource (fase
25–36), Laporan Presensi (fase 56), simulasi rotasi 24/7 (fase 65),
TukarJadwal × rotasi beda tanggal (fase 71–73), notifikasi TukarJadwal
(fase 74), modul Informasi (fase 75), badge pengajuan menunggu (fase 77),
login username (fase 79), terjemahan validasi (fase 80) — semuanya
SELESAI, detail di CHANGELOG.

**Fokus berikutnya: frontend.** Ada DUA blocker rilis di FE yang lahir
dari pekerjaan backend 9 Nov, keduanya bikin aplikasi tidak bisa dipakai
sama sekali kalau tidak digarap (lihat `todo-fr.md` di repo frontend):
1. LoginScreen masih mengirim `email` — tidak ada karyawan yang bisa
   masuk setelah backend fase 79 dideploy.
2. NotifikasiScreen belum mengenal `tipe: 'tukar_jadwal'` — notifikasi
   fase 74 bisa bikin ikon kosong atau crash.

Catatan: keputusan bisnis yang masih menggantung (alur Izin, override
TukarJadwal, `is_cuti_bersama`) butuh masukan dari pihak RS, bukan kerja
teknis — bisa ditanyakan paralel dengan apa pun yang dipilih.

## Kebutuhan masukan dari pihak RS

- [ ] **Aturan masa kerja untuk hak cuti belum ada di kode** — pihak RS
      menyebut (10 Nov 2026) bahwa cuti baru didapat setelah bekerja satu
      tahun. Tidak ada satu pun bagian sistem yang menjalankan aturan itu:
      `kuota_cutis` berisi karyawan_id, jenis_cuti_id, tahun, semester,
      kuota, terpakai — tidak ada yang merujuk masa kerja, dan
      `tanggal_bergabung` di KaryawanForm cuma DatePicker tanpa konsekuensi.

      Dugaan (BELUM diverifikasi): karyawan yang baru sebulan tetap bisa
      mengajukan cuti tahunan dan sistem meluluskannya, asalkan kuotanya
      sudah digenerate.

      Yang perlu diperiksa: perintah/seeder yang membuat KuotaCuti — apakah
      dia menyaring berdasarkan tanggal_bergabung? Lalu CutiController /
      validasi pengajuan — apakah ada cek masa kerja?

      Pertanyaan untuk pihak RS sebelum menulis kode:
      - "Setahun" dihitung dari tanggal_bergabung ke tanggal pengajuan,
        atau karyawan baru dapat kuota penuh di tahun berikutnya?
      - Apakah berlaku untuk semua jenis cuti atau cuti tahunan saja?
        (Cuti sakit dan melahirkan biasanya tidak menunggu setahun.)
      - Karyawan kontrak/magang: beda aturan?

      Konsekuensi ke import Excel: `tanggal_bergabung` DIPERTAHANKAN di
      template justru karena ini. Mengeluarkannya berarti 150 karyawan masuk
      tanpa tanggal, dan saat aturan ini dibuat tidak ada data untuk
      menjalankannya. Dicatat 10 Nov 2026.

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

- [ ] **`riwayat()` 500 kalau ada baris absensi tanpa shift** —
      `AbsensiController::riwayat()` memanggil `$a->shift->nama_shift` dan
      `$a->shift->jam_masuk` tanpa null check, padahal `absensi.shift_id`
      nullable. Perlu dicek: apakah sync Dinas (`status = 'dinas'`) dan
      baris cuti mengisi `shift_id`? Kalau null, maka setiap karyawan yang
      pernah cuti atau dinas dalam sebulan membuat
      `GET /api/absensi/riwayat` untuk bulan itu melempar 500 — bukan
      menampilkan baris dengan shift kosong. Bukan skenario langka: cuti
      pasti terjadi.

      Yang perlu dilihat: observer/service yang membuat `Absensi` saat
      Dinas di-approve, dan yang serupa untuk Cuti. `rekap()` aman (tidak
      menyentuh relasi shift), jadi kalaupun bug ini nyata, gejalanya cuma
      di satu endpoint. Catatan: `menitTerlambat()` di model juga memanggil
      `$this->shift->hitungMenitTerlambat()` di jalur fallback, tapi hanya
      kalau atribut `menit_terlambat` tidak di-set — jarang. Dicatat
      10 Nov 2026.

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

      **Import TIDAK mengisi assignment shift/pola** — diputuskan
      10 Nov 2026, lihat item berikutnya. Import murni data karyawan.

      Kolom template (10): nip, nama, username, email, nomor_telepon,
      status_pegawai, tipe_jadwal, unit_kerja, jabatan,
      tanggal_bergabung. Username boleh dikosongkan — `Karyawan::
      generateUsername()` (fase 79) sudah jadi sumber aturan tunggal
      dan bisa dipakai di sini. Password tidak di template: pakai
      password default yang sama dengan form Filament, dan admin bisa
      me-resetnya per orang (fase 78).

      Yang harus hati-hati, dan ini bagian yang gampang diremehkan:

      - **`tipe_jadwal` wajib eksplisit di template**, tidak boleh
        kosong lalu ditebak dari unit_kerja/jabatan — itu larangan
        eksplisit di QUICK_CONTEXT, dan import massal adalah tempat
        paling mungkin orang tergoda menebaknya. Tetap berlaku
        walaupun assignment-nya sendiri tidak diimpor.
      - **Validasi harus per-baris dengan laporan yang bisa dibaca
        admin**, bukan gagal total di baris pertama. Admin RS tidak
        punya terminal; kalau 150 baris ditolak tanpa tahu baris mana
        yang salah, fitur ini malah lebih buruk daripada input manual.
      - **nip, username & email unique** — baris duplikat di dalam file
        ITU SENDIRI juga harus ketahuan, bukan cuma bentrok dengan
        database.
      - **Import harus atomic atau punya mode dry-run.** Gagal di
        baris 120 setelah 119 karyawan terlanjur masuk itu keadaan
        yang sulit dibereskan admin.
      - **Instansi dipilih sekali di modal import**, bukan kolom template.
        KOREKSI 10 Nov 2026: catatan lama menulis "di-scope" — itu salah,
        panel ini TIDAK multi-tenant. `KaryawanForm` memakai
        `Select::make('instansi_id')->relationship(...)` biasa, admin bebas
        memilih instansi mana pun, tidak ada global scope. Satu Select di
        samping file upload: hasilnya sama (tidak ada 150 sel yang bisa
        salah instansi), tapi alasannya beda — jangan sampai ada yang
        mengira ada tenancy yang perlu dijaga.

      - **`generateUsername()` tidak aman untuk batch.** Loop-nya cuma
        mengecek DB (`static::query()->where('username', $kandidat)`), jadi
        dalam satu import dua "Agus Pranowo" sama-sama dapat `aguspranowo`
        dan yang kedua baru meledak di INSERT — SQL mentah, bukan laporan
        per-baris. Bukan kelemahan fungsinya (dirancang untuk form
        satu-per-satu, dan untuk itu benar); sisi import perlu daftar
        username yang sudah "dipesan" selama proses, dicek bersamaan dengan
        DB. JANGAN ubah `generateUsername()` — dipakai KaryawanForm sejak
        fase 79, menambah parameter mengubah arti pemanggilan yang ada.

      - **`unit_kerja` dicocokkan dengan daftar unit yang sudah ada**
        (gabungan Karyawan + PolaRotasi, sama seperti datalist di form).
        Tidak memblokir — unit baru itu sah — tapi dilaporkan sebagai
        peringatan. Alasannya di komentar fase 33: satu typo bikin karyawan
        rotasi tidak bisa di-assign pola apa pun, dan di import typo itu
        tersalin ke 20 baris sekaligus.

      - **PRASYARAT: `email` harus nullable dulu** — lihat item terpisah.
        Pihak RS mengonfirmasi 10 Nov 2026 bahwa tidak semua karyawan punya
        email. Selama kolomnya `required`, import akan menolak ~110 baris
        dan admin terpaksa mengarang alamat — lebih buruk daripada kosong,
        karena notifikasi terkirim ke alamat yang tidak ada.
      - **Setelah import, tampilkan jumlah karyawan tanpa assignment**
        dan sediakan filternya di daftar karyawan — supaya langkah
        kedua tidak bisa terlupa diam-diam.

      KOREKSI 10 Nov 2026 — prasyarat deploy ternyata TIGA langkah, bukan
      dua. `AbsensiController::masuk()` langkah 5 membaca `KaryawanShift`
      dulu, lalu fallback ke baris `Jadwal` hari ini — BUKAN ke
      `KaryawanPolaRotasi`. Jadi untuk karyawan rotasi, punya baris
      `karyawan_pola_rotasis` belum cukup; `jadwal:generate-rotasi` harus
      sudah jalan juga.

      1. Import karyawan
      2. Bulk action assignment
      3. `jadwal:generate-rotasi` — khusus rotasi, setelah langkah 2

      Karyawan umum selesai di langkah 2. Karyawan rotasi yang berhenti di
      langkah 2 tetap tidak bisa absen, dengan pesan error yang IDENTIK
      dengan karyawan yang belum di-assign sama sekali ("Tidak ada shift
      aktif untuk hari ini") — admin tidak punya cara membedakan keduanya.

      Karena itu filter di daftar karyawan bukan "tanpa assignment" (itu
      meloloskan rotasi yang polanya sudah ada tapi jadwalnya belum
      digenerate), melainkan **"tidak bisa absen hari ini"**: tidak punya
      `KaryawanShift` aktif DAN tidak punya baris `Jadwal` hari ini. Salin
      persis kondisi di langkah 5 controller supaya filter tidak bisa
      menyimpang dari kenyataan.

- [ ] **`email` karyawan jadi nullable** — prasyarat import Excel. Pihak RS
      mengonfirmasi tidak semua karyawan punya email (10 Nov 2026). Baru
      bisa dikerjakan sekarang: selama email masih kredensial login,
      membuatnya kosong berarti ada karyawan yang tidak bisa masuk. Setelah
      fase 79 (login username), email turun jadi data profil biasa.

      - Migration: `email` nullable. Unique index DIPERTAHANKAN — MySQL
        mengizinkan banyak NULL dalam unique index, jadi banyak baris kosong
        tidak saling bentrok tapi dua email terisi yang sama tetap ditolak.
      - `Karyawan::routeNotificationForMail(): string` → `?string`. Return
        type non-nullable = TypeError begitu ada satu karyawan tanpa email.
        MailChannel sudah berhenti sendiri kalau route-nya kosong.
      - CEK DULU: adakah notifikasi yang `via()`-nya memuat 'mail'? Kalau
        semua `['database']`, perubahan tipe itu murni jaga-jaga. Kalau ada,
        perlu diputuskan apa artinya ~110 karyawan diam-diam tidak menerima
        notifikasi — itu keputusan, bukan perbaikan tipe.
      - `KaryawanForm`: cabut `->required()`, pertahankan `->unique()`,
        tambah helper text soal konsekuensinya.
      - KONSEKUENSI FE: response profil `AuthController` bisa mengirim
        `email: null`. Kalau model Dart-nya `String` bukan `String?`,
        parsing melempar. Cek SEBELUM migration jalan di data nyata, bukan
        sesudah. Catat juga di todo-fr.md.
        
- [ ] **Pengajuan TukarJadwal yang macet di `menunggu_rekan` tidak
      terhitung di mana pun** — badge menu (fase 77) hanya menghitung
      `menunggu_admin`. Admin tidak punya cara tahu ada pengajuan
      menggantung berminggu-minggu kecuali membuka menunya dan
      menghitung manual (terlihat 5 pengajuan macet di data dummy,
      tertua 5 hari). Butuh filter umur, bukan sekadar status — itu
      sebabnya sengaja tidak dimasukkan ke badge. Dicatat 9 Nov 2026.

- [ ] **Fitur ganti email & password** — endpoint belum ada; cek dulu
      `AuthController`. Pasangannya di FE juga belum ada (menu
      "Profil"/"Ubah Password" masih placeholder snackbar), jadi tiga
      hal saling menunggu: endpoint, layar FE, dan "paksa ganti password
      setelah reset admin" (fase 78) yang baru masuk akal kalau layar
      itu ada. Yang memutus simpulnya: kerjakan FE-nya.

- [ ] Tambah accessor `Lembur::getDurasiMenitAttribute()` (computed, bukan
      kolom DB) kalau nanti ada kebutuhan laporan/payroll lembur — aware
      kasus lintas tengah malam (jam_selesai < jam_mulai → +1 hari).
