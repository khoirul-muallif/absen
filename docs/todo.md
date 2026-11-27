# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

**Terakhir dibersihkan:** 27 November 2026 (setelah fase 95)

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

Sisa pekerjaan: satu menunggu masukan pihak RS, empat berukuran satu
sesi sendiri (cuti tahunan, TukarJadwal × libur nasional, ekstrak
generator, test modal Filament), dua catatan saat deploy, dan dua
catatan kecil di Lain-lain. Tidak ada lagi yang bisa digarap sambil
lalu.

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

## Saat deploy pertama

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom `melebihi_toleransi_bulanan`
      tidak pernah tersimpan sejak fase 9, jadi SEMUA baris lama salah di
      kolom itu. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] Verifikasi `tipe_jadwal` di data production asli.



## Yang ukurannya satu sesi sendiri

- [ ] **Hak cuti tahunan berdasarkan masa kerja belum ada mekanismenya**
      — bukan sekadar "tambahkan cek masa kerja". Diperiksa ke source 27
      Nov, dan dua catatan lama ternyata keliru.

      **Tidak ada generator kuota sama sekali.** Catatan lama bertanya
      apakah seeder/command KuotaCuti menyaring `tanggal_bergabung` —
      tidak ada yang bisa menyaring, karena baris `kuota_cutis` HANYA
      lahir dari `KuotaCuti::pastikanUntuk()`, yang dipanggil saat
      approve pertama. Bukan di awal tahun, bukan saat karyawan dibuat.

      **Karyawan baru memang bisa cuti tahunan**, tapi lewat jalur yang
      berbeda dari dugaan: `sisaUntuk()` mengembalikan null saat baris
      belum ada, lalu `CutiController::ajukan()` jatuh ke
      `?? $jenisCuti->default_kuota` — 12 hari penuh. Fallback itu
      keputusan sadar fase 22 ("tidak ada dasar untuk menolak"), bukan
      kelalaian.

      Aturan dari pihak RS (27 Nov 2026):
      - Cuti TAHUNAN saja. Sakit/melahirkan tidak menunggu.
      - Patokan BULAN, bukan tanggal. Masuk 15 Maret 2026 → berhak
        mulai 1 Maret 2027.
      - Kuota MENGALIR per periode, 1 hari per bulan. Contoh orang itu
        di 2027: semester 1 (Mar–Jun) = 4 hari, semester 2 = 6 hari
        penuh. 2028 dan seterusnya 6 per semester.
      - Admin bisa menimpa lewat form.

      Konsekuensi: yang perlu dibuat bukan validasi, tapi mekanisme
      pemberian kuota yang selama ini tidak ada — command yang membuat
      baris per periode dari `tanggal_bergabung`, pencabutan (atau
      pembalikan arti) fallback `?? default_kuota` di CutiController,
      dan form admin untuk menimpa. Yang terakhir membuat pencabutan
      fallback aman: ada jalan keluar untuk kasus khusus.

      Belum dibaca dan WAJIB dibaca sebelum menulis: `Cuti::afterApprove()`
      dan `Cuti::hariPendingUntuk()`, untuk melihat utuh bagaimana
      `terpakai` bergerak.

      Karyawan kontrak/magang: belum ditanyakan.

      Dicatat 10 Nov 2026, ditulis ulang 27 Nov 2026 setelah memeriksa
      source.

- [ ] **TukarJadwal memperlakukan semua baris libur sama** — lanjutan
      fase 95, yang sudah menambahkan `jadwals.libur_nasional`. Sisi
      generator sudah selesai; yang belum TukarJadwal.

      Tiga dari empat poin catatan lama sudah tidak berlaku: generator
      bulanan ternyata SUDAH membuat baris libur mingguan eksplisit
      (asimetri umum-vs-rotasi hilang), dan kolom baru sudah
      membedakan mingguan dari nasional.

      Yang tersisa, dan lebih dalam dari dugaan semula: di
      `approveAndSwap()` cabang tukar dua-orang, baris libur yang
      "diserap" benar-benar `delete()`. Kalau itu libur nasional,
      catatannya lenyap dari `jadwals` sama sekali — kolom baru tidak
      menolong karena barisnya sudah tidak ada.

      Dan dua cabang memperlakukannya berbeda: pindah-sendiri MENUKAR
      baris libur (simetris, reversibel), tukar dua-orang MENGHAPUS.
      Belum pernah diputuskan apakah itu disengaja.

      Keputusan yang sudah diambil (27 Nov): karyawan BOLEH pindah/tukar
      ke hari libur nasional, tapi harus TERLIHAT — pengaju diberi tahu
      saat memilih tanggal tujuan, admin melihat tandanya di layar
      approval.

      Yang masih perlu diputuskan: serap (hapus) atau timpa (baris tetap
      ada, jadi hari kerja)? Dan haruskah kedua cabang disamakan?

      Validasi pengajuan ada di `TukarJadwalController::ajukan()`, bukan
      di model — `ajukanPindah()` yang disebut catatan lama tidak ada.

      Sisi FE (tombol Ajukan Tukar/Pindah muncul juga untuk
      cuti/dinas/libur, status-06-11-26.md) sengaja DIPISAH, bukan
      digarap bersama: backend dulu sampai kontraknya jelas.

      Dicatat 9 Nov 2026, ditulis ulang 27 Nov 2026 setelah fase 95.

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
