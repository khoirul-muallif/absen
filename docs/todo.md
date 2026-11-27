# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas

**Backend solid.** Review UX 17 Filament Resource (fase 25–36), Laporan
Presensi (fase 56), simulasi rotasi 24/7 (fase 65), TukarJadwal × rotasi
beda tanggal (fase 71–73), notifikasi TukarJadwal (fase 74), modul
Informasi (fase 75), badge pengajuan menunggu (fase 77), login username
(fase 79), terjemahan validasi (fase 80), email nullable (fase 81),
import karyawan Excel (fase 82), bulk action assignment (fase 83),
riwayat() tanpa shift (fase 84), kunci kontrak notifikasi (fase 85),
ganti password sendiri (fase 86), logo instansi (fase 87), pisah jam
kembali Izin (fase 88), harus_ganti_password (fase 89) — semuanya
SELESAI, detail di CHANGELOG.

**Dua blocker rilis FE yang dicatat di sini sudah ditutup 20 Nov 2026**
(LoginScreen `username`, NotifikasiScreen `tipe: 'tukar_jadwal'`).
Tidak ada lagi yang memblokir deploy dari sisi FE.

**Yang tersisa di FE** adalah penyempurnaan, dan per 23 Nov 2026 ada
enam hal yang sudah ditulis tapi BELUM pernah dijalankan di perangkat —
lihat `status-23-11-26.md` di repo frontend. Verifikasi itu dulu sebelum
menambah modul baru.

**Prasyarat deploy pertama** masih tiga langkah, bukan dua: import
karyawan (fase 82) → bulk action assignment (fase 83) →
`jadwal:generate-rotasi` khusus rotasi. Lihat item di bawah.

Catatan: keputusan bisnis yang masih menggantung (alur Izin, override
TukarJadwal, `is_cuti_bersama`, aturan masa kerja cuti) butuh masukan
dari pihak RS, bukan kerja teknis — bisa ditanyakan paralel dengan apa
pun yang dipilih.

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

- [ ] **Penjaga dua-sheet mengasumsikan sheet pertama adalah sheet data**
      — kalau admin mengirim file hasil edit yang urutan sheet-nya
      terbalik, importer memproses "Petunjuk" dan menolak semuanya dengan
      pesan yang tidak masuk akal. Lebih benar: cari sheet berdasarkan
      nama, atau berdasarkan kecocokan header. Dicatat 10 Nov 2026.

- [ ] **Unit kerja beda kapitalisasi masuk apa adanya** — "igd" dan "IGD"
      jadi dua unit berbeda. Peringatan sudah muncul di pratinjau, jadi
      admin tahu; tapi untuk karyawan ROTASI ini berarti dia tidak bisa
      di-assign pola apa pun (fase 33 mencocokkan unit_kerja persis).

      Keputusan terbuka: haruskah importer mencocokkan case-insensitive
      dan memakai ejaan yang sudah ada? Itu mengubah data yang diketik
      admin tanpa diminta — bukan keputusan sepele, dan kelas masalahnya
      sama dengan "jangan menebak tipe_jadwal". Dicatat 10 Nov 2026.

## Fitur & infrastruktur

- [ ] **Ekstrak generator jadwal dari command ke kelas biasa** —
      GenerateJadwal memanggil `Artisan::call()` lalu menampilkan
      `Artisan::output()` ke admin, jadi output CLI dan teks admin
      terpaksa jadi satu benda. Akibatnya sudah dua kali terlihat:
      nama command bocor ke admin (ditutup 27 Nov) dan tabel ASCII
      hancur di notifikasi (ditutup 27 Nov). Yang tersisa: baris baru
      hilang, jadi semua kalimat menyambung jadi satu paragraf, dan
      prefix [DRY-RUN] ikut terbawa walau panel tidak pernah
      mengirimnya.

      Pola yang sudah terbukti di proyek ini: `PerencanaAssignment`
      (fase 83) — satu kelas dipakai dua pemanggil supaya keduanya
      tidak bisa berbeda pendapat. Command menulis table() untuk
      terminal, panel menyusun kalimatnya sendiri dari angka.

      Bonus: anomali "tipe ROTASI tapi punya KaryawanShift" bisa
      ditampilkan sebagai daftar nama yang bisa ditindaklanjuti.

      Ukurannya satu sesi sendiri, bukan tempelan. Dicatat 27 Nov 2026.
      
- [ ] **Selisih `jam_kembali_rencana` vs `jam_kembali_aktual` belum
      dipakai di mana pun** — dua kolom sengaja dibuat 24 Nov 2026 walau
      konsekuensi "aktual lewat dari rencana" BELUM diputuskan pihak RS.
      Untuk sekarang murni catatan.

      Kalau sampai beberapa bulan tidak ada yang memakai selisihnya,
      pertimbangkan menggabungkan balik jadi satu kolom. Kalau dipakai,
      tempat termurah memunculkannya: kolom "Molor" di Laporan Presensi,
      atau badge di daftar Izin panel admin.

      Pertanyaan untuk pihak RS: apakah karyawan yang sering molor dari
      perkiraannya sendiri perlu ditandai? Kalau ya, berapa menit
      toleransinya, dan siapa yang melihatnya?

- [ ] **`logo` instansi tidak dibersihkan saat diganti** — FileUpload
      menyimpan file baru tapi yang lama tetap menumpuk di
      `storage/app/public/logo-instansi`. Masalah yang sama kemungkinan
      ada di `foto_profil` karyawan. Kecil selama cuma satu instansi, tapi
      catat sebelum lupa. Dicatat 23 Nov 2026.

- [ ] **Bulk action assignment belum punya test modal** — fase 83 punya 16
      test untuk `PerencanaAssignment` (seluruh logika keputusan), tapi dua
      bulk action Filament-nya sendiri belum pernah diuji: apakah modal
      terbuka, apakah pratinjau ikut berubah saat pilihan diganti, apakah
      `getSelectedTableRecords()` mengembalikan yang dicentang, dan apakah
      transaksi benar-benar menulis. Butuh `livewire()` +
      `callTableBulkAction()`. Dicatat 10 Nov 2026.

- [ ] **`AbsenTerlambat::toMail()` sudah jadi kode mati** sejak fase 81
      mencabut 'mail' dari `via()`. Dibiarkan sengaja supaya keputusan itu
      murah dibalik. Hapus kalau sudah yakin tidak akan dikembalikan.

      
- [ ] **Bagian import yang masih tidak diuji** — fase 82 punya 26 test untuk
      aturan validasi (`KaryawanImportTest`), tapi semuanya memanggil
      `collection()` langsung dengan Collection buatan. Yang belum pernah
      diuji sama sekali:
      - modal Filament dua langkah (unggah → pratinjau → commit)
      - baca-ulang berkas saat commit, termasuk kasus berkas sudah hilang
      - pembersihan berkas yatim (`bersihkanBerkasLama`)
      - pembacaan file .xlsx sungguhan, termasuk sheet "Petunjuk"

      Butuh fixture .xlsx + `livewire()`. Dicatat 10 Nov 2026.


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
   
- [ ] **Pengajuan TukarJadwal yang macet di `menunggu_rekan` tidak
      terhitung di mana pun** — badge menu (fase 77) hanya menghitung
      `menunggu_admin`. Admin tidak punya cara tahu ada pengajuan
      menggantung berminggu-minggu kecuali membuka menunya dan
      menghitung manual (terlihat 5 pengajuan macet di data dummy,
      tertua 5 hari). Butuh filter umur, bukan sekadar status — itu
      sebabnya sengaja tidak dimasukkan ke badge. Dicatat 9 Nov 2026.


- [ ] **`min:8` belum diadu dengan password default admin** — endpoint
      ubah password mewajibkan minimal 8 karakter, tapi password default
      yang dipakai reset admin (fase 78) belum dicek panjangnya. Kalau
      di bawah 8, karyawan yang baru di-reset punya password lebih lemah
      daripada yang boleh dia pilih sendiri. Tidak merusak apa pun,
      cuma janggal. Dicatat 23 Nov 2026.

- [ ] **Nama test usang di `AuthControllerTest`** — "bisa login dengan
      email dan password yang benar", isinya login pakai `username`.
      Sisa dari sebelum pindah ke username (fase 79). Dicatat 23 Nov 2026.

- [ ] Tambah accessor `Lembur::getDurasiMenitAttribute()` (computed, bukan
      kolom DB) kalau nanti ada kebutuhan laporan/payroll lembur — aware
      kasus lintas tengah malam (jam_selesai < jam_mulai → +1 hari).
