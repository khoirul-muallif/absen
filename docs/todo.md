# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

**Terakhir dibersihkan:** 27 November 2026 (setelah fase 92)

## Posisi sekarang

Backend solid, FE praktis beres. Tidak ada yang memblokir deploy.

**Prasyarat deploy pertama ada TIGA langkah, bukan dua.**
`AbsensiController::masuk()` langkah 5 membaca `KaryawanShift` lalu
fallback ke baris `Jadwal` hari ini — BUKAN ke `KaryawanPolaRotasi`.
Jadi untuk rotasi, punya baris `karyawan_pola_rotasis` belum cukup:

1. Import karyawan (fase 82)
2. Bulk action assignment (fase 83)
3. Buat jadwal rotasi lewat menu Generate Jadwal — khusus rotasi,
   setelah langkah 2

Karyawan yang tertinggal di langkah 2 atau 3 bisa dilihat lewat filter
"Belum bisa absen hari ini" di tabel karyawan (fase 91), dan jumlahnya
ikut muncul di notifikasi setelah import.

Sisa pekerjaan: tiga hal menunggu masukan pihak RS, dua berukuran satu
sesi sendiri (ekstrak generator, test modal Filament), dan dua catatan
kecil di Lain-lain. Tidak ada lagi yang bisa digarap sambil lalu, dan dua yang ukurannya satu sesi sendiri (ekstrak generator,
test modal Filament).

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


- [ ] **Proporsi staf per unit/tim rotasi** — `SimulasiMassalSeeder`
      membagi rata 120 karyawan rotasi ke 3 unit (Rawat Inap/IGD/ICU).
      Belum tentu realistis (Rawat Inap biasanya butuh lebih banyak staf
      daripada ICU) — baru relevan kalau data dummy ini mulai dipakai
      buat demo/keputusan ke pihak RS, bukan sekadar testing internal.
      Catatan: sejak redesign rotasi-individu (fase 65), setiap karyawan
      rotasi dalam satu unit memakai SATU pola yang sama (bukan lagi
      dibagi ke 3 tim shift terpisah) — pembagian 40/40/40 sekarang murni
      per unit, tidak ada lagi sub-pembagian tim.

## Saat deploy pertama

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] Verifikasi `tipe_jadwal` di data production asli.

## Import Excel


## Yang ukurannya satu sesi sendiri

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
      Dicatat 27 Nov 2026.

- [ ] **Dua modal Filament yang belum pernah diuji** — digabung karena
      setup-nya sejenis (`livewire()`), jadi lebih murah sekali garap.

      **Bulk action assignment** (fase 83): 16 test sudah mengunci
      `PerencanaAssignment` (seluruh logika keputusan), tapi bulk
      action-nya sendiri belum — apakah modal terbuka, apakah pratinjau
      ikut berubah saat pilihan diganti, apakah
      `getSelectedTableRecords()` mengembalikan yang dicentang, dan
      apakah transaksi benar-benar menulis. Butuh
      `callTableBulkAction()`.

      **Import** (fase 82): aturan validasi sudah dikunci 26 test, dan
      pembacaan .xlsx sungguhan 5 test (27 Nov). Yang belum: modal dua
      langkah (unggah → pratinjau → commit), baca-ulang berkas saat
      commit termasuk kasus berkas sudah hilang, dan pembersihan berkas
      yatim (`bersihkanBerkasLama`). Fixture .xlsx tinggal dipakai ulang
      dari `KaryawanImportBerkasTest` (`buatBerkasXlsx()`).

      Dicatat 10 Nov 2026, digabung 27 Nov 2026.

## Lain-lain

- [ ] **Pengajuan TukarJadwal yang macet di `menunggu_rekan` tidak
      terhitung di mana pun** — badge menu (fase 77) hanya menghitung
      `menunggu_admin`. Admin tidak punya cara tahu ada pengajuan
      menggantung berminggu-minggu kecuali membuka menunya dan
      menghitung manual (terlihat 5 pengajuan macet di data dummy,
      tertua 5 hari). Butuh filter umur, bukan sekadar status — itu
      sebabnya sengaja tidak dimasukkan ke badge. Dicatat 9 Nov 2026.

- [ ] Tambah accessor `Lembur::getDurasiMenitAttribute()` (computed, bukan
      kolom DB) kalau nanti ada kebutuhan laporan/payroll lembur — aware
      kasus lintas tengah malam (jam_selesai < jam_mulai → +1 hari).
