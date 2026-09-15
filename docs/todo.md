# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — Evaluasi Solidity & UX Backend
Trigger: development kerasa lancar banget, gak ada hambatan — itu sinyal
buat curiga, kemungkinan besar test yang ada baru cover happy path.

Sudah selesai (navigationGroup, audit lintas modul, race condition, tanggal
edge case, konsistensi respons API, 5 modul approval + Jenis/Kuota Cuti,
sentralisasi query kuota, jebakan cast datetime, Data Absensi, Jadwal) —
detail di CHANGELOG fase 20-28.

- [ ] **Review UX Filament — 8 Resource yang belum disentuh**

      Presensi:
      - [ ] Hari Libur (HariLiburResource)

      Master Data:
      - [ ] Karyawan (KaryawanResource) — cek kejelasan section
            "Tipe Penjadwalan" (umum/rotasi, fase 13), interaksi dengan
            2 menu shift yang terpisah (lihat 2 item Shift di bawah).
      - [ ] Instansi (InstansiResource)
      - [ ] QR Instansi (QrInstansiResource) — cek kejelasan
            expired_at (null = permanen) di form, potensi admin gak
            sadar QR permanen kalau field dikosongkan begitu saja.
      - [ ] Pola Rotasi (PolaRotasiResource) — SUDAH ADA rencana
            perbaikan detail di item terpisah di bawah (preview 7-14
            hari, kejelasan toggle Hari Libur, ikon reorder) — jangan
            dikerjakan asal, ikuti draft yang sudah ada.
      - [ ] Shift Karyawan Rotasi (KaryawanPolaRotasiResource) — terkait
            erat dengan Pola Rotasi di atas, sebaiknya direview bareng.
      - [ ] Shift (ShiftResource) — cek kejelasan mode_toleransi
            (harian/akumulasi_bulanan) di form, mengingat ini konsep yang
            gampang disalahpahami (lihat CHANGELOG fase 9).
      - [ ] Shift Karyawan Umum (KaryawanShiftResource) — cek guard
            tipe_jadwal (fase 18) sudah tervisualisasi jelas di dropdown,
            bukan cuma tervalidasi di server-side.

      Pola yang sudah terbentuk dari Data Absensi & Jadwal, dipakai lagi
      untuk sisanya: (A) integritas data — validasi yang cuma ada di DB
      tapi tidak di form, field yang required padahal nullable, guard
      untuk baris yang ditulis sistem; (B) halaman View + Infolist kalau
      belum ada; (C) badge/label/filter.

      Urutan yang disarankan: Hari Libur dulu (paling kecil, dan dipakai
      oleh 2 generator + RekapHarian), lalu Shift & Shift Karyawan
      Umum/Rotasi + Pola Rotasi sebagai satu kelompok (saling terkait),
      baru Karyawan/Instansi/QR Instansi.

## Nanti / belum prioritas
- [ ] **Simulasi karyawan rotasi yang punya langkah LIBUR di polanya
      (mis. 5 hari kerja/minggu)** — belum pernah dicoba sama sekali,
      baik manual maupun di test. Seeder rotasi sekarang (Dedi/Siti/Rina)
      kerja nonstop 15 hari TANPA satu pun langkah libur, jadi seluruh
      jalur "karyawan rotasi sedang libur" tidak pernah dilewati.
      Konfirmasi: yang dimaksud memang rotasi beneran (shift berputar),
      bukan tipe_jadwal=umum — umum itu staf non-pelayanan yang tidak
      masuk pola rotasi sama sekali. RS operasional 24 jam, tapi tidak
      semua unit 24 jam.
      Secara struktur SUDAH didukung (`langkah` json panjang bebas,
      langkah libur tanpa shift_id sudah valid & ada test-nya di
      PolaRotasiResourceTest) — jadi ini soal data & jalur yang belum
      pernah dilewati, bukan fitur yang hilang.
      Yang perlu dicek kalau dikerjakan:
      - **Paling dicurigai: `PengingatBelumAbsen` &
        `PengingatBelumAbsenPulang` (fase 5) sama sekali TIDAK punya
        test** (tidak ada file PengingatBelumAbsenTest). Kalau
        logikanya tidak cek Jadwal jenis='libur' untuk karyawan rotasi,
        karyawan yang sedang libur tetap dikirimi notifikasi "belum
        absen". Mustahil ketahuan dengan data dummy sekarang.
      - `AbsensiController::masuk()` untuk rotasi dengan Jadwal yang
        ADA tapi shift_id null (libur) — pesannya jelas atau malah
        error? Test yang ada cuma cover "rotasi tanpa Jadwal".
      - Cuti/Dinas approved menimpa Jadwal libur dan tetap memotong
        kuota untuk hari itu. Untuk karyawan umum tidak masalah
        (generator skip non-hari_kerja), tapi rotasi bisa "buang" jatah
        cuti di hari yang memang liburnya. Keputusan bisnis, belum
        pernah dibahas.
      - TukarJadwal yang melibatkan hari libur rotasi — terkait
        keterbatasan yang sudah didokumentasikan di fase 11.
      - Tambah 1 pola + 1 karyawan rotasi ber-libur ke seeder supaya
        bisa diklik manual di admin panel.
- [ ] **Izin: rethink alur approval + endpoint jam_kembali** — ditemukan saat
      ngobrol santai (belum ada di audit manapun). Saat ini Izin masih pakai
      HasApprovalWorkflow (pending/approved/rejected) sama seperti Cuti/Dinas,
      tapi sifatnya beda: izin keluar sementara itu darurat/insidental, gak
      realistis nunggu approval admin dulu sebelum keluar (admin juga gak
      mungkin standby mantau izin real-time). Draft arah solusi (belum final):
      - Izin auto-approved saat diajukan (skip status pending), bukan hapus
        approval total.
      - Perlu endpoint baru: karyawan update jam_kembali sendiri pas balik
        kerja (sekarang tidak ada endpoint ini sama sekali di IzinController).
      - Sekalian bikin riwayat/histori buat jam_kembali (kapan update terjadi,
        siapa yang update) — belum jelas perlu tabel log terpisah atau cukup
        kolom updated_at di Izin sendiri.
      - Perlu dicek ulang: apakah approval masih relevan buat retroactive
        review (misal admin flag izin yang kelamaan/mencurigakan setelah
        kejadian), atau approval dihapus total.
      Catatan tambahan dari audit fase 25: karena alasan struktural ini,
      `EditAction` di IzinsTable/ViewIzin SENGAJA tidak dibatasi ke status
      pending (beda dari Cuti/Lembur/Dinas/TukarJadwal yang lock-on-approve)
      — supaya jam_kembali masih bisa diisi manual lewat form edit sampai
      solusi endpoint khusus di atas benar-benar dikerjakan.
      Belum ada keputusan final — didiskusikan lagi sebelum dikerjakan.
- [ ] **TukarJadwal: admin butuh override saat status `menunggu_rekan` macet** —
      ditemukan saat audit UX (fase 25). Sekarang EditAction/approve/reject
      semua tersembunyi selama status masih `menunggu_rekan` (isPending()
      di-override khusus cek menunggu_admin) — cuma ViewAction yang muncul.
      Kalau rekan tidak kunjung merespons (lupa, cuti, resign, dsb), admin
      TIDAK punya cara membatalkan atau memaksa lanjut pengajuan itu lewat
      Filament — pengajuan jadi macet permanen tanpa jalan keluar.
      Draft arah solusi (belum final):
      - Tambah action "Batalkan" khusus utk status menunggu_rekan (bukan
        approve/reject, tapi cancel manual oleh admin) — beda dari reject
        biasa yang cuma untuk status menunggu_admin.
      - Atau: expiry otomatis (misal X hari tanpa respons -> auto-cancel/
        auto-escalate ke admin) lewat scheduler, mirip pola PengingatBelumAbsen.
      - Perlu diputuskan: apakah pembatalan oleh admin butuh baris
        catatan/log terpisah (siapa yang batalkan, kapan, kenapa) selain
        catatan_approval yang ada sekarang.
      Belum ada keputusan final — didiskusikan lagi sebelum dikerjakan.
- [ ] **KuotaCuti: dukung periode per 6 bulan + tutup celah "row belum ada"** —
      dua kebutuhan yang digabung karena menyentuh keputusan yang sama:
      apakah row KuotaCuti boleh terus-menerus tidak ada.

      (a) Periode semesteran. Sekarang kuota selalu dihitung per tahun
      kalender penuh (kolom `tahun`, unique per karyawan+jenis+tahun).
      Kebutuhan baru: jenis cuti tertentu bisa punya periode kuota 6
      bulanan (semester), bukan cuma tahunan.
      - Kemungkinan butuh kolom baru di jenis_cutis (misal
        `periode_kuota` enum tahunan/semesteran) yang menentukan
        JenisCuti tertentu pakai periode berapa — is_tahunan yang ada
        sekarang cuma boolean on/off, belum bisa bedain "tahunan" vs
        "6 bulanan" sebagai 2 varian yang beda.
      - KuotaCuti perlu kolom tambahan buat identifikasi periode dalam
        tahun yang sama (misal `semester` 1/2, atau ganti pendekatan
        jadi `periode_mulai`/`periode_selesai` eksplisit, bukan cuma
        `tahun` int) — pilihan ini pengaruh besar ke unique constraint
        & migration data existing.
      - Titik query yang harus ikut direvisi sekarang TINGGAL 3 setelah
        sentralisasi fase 26: `KuotaCuti::untuk()`/`sisaUntuk()`,
        `Cuti::hariPendingUntuk()` (masih pakai whereYear), dan
        `Cuti::afterApprove()` (query lockForUpdate sendiri, sengaja
        tidak ikut disentralisasi). Pemanggil di CutiForm/CutiInfolist/
        CutisTable/CutiController tidak perlu disentuh lagi.
      - Perlu diputuskan: apakah ini per-JenisCuti (sebagian jenis cuti
        tahunan, sebagian semesteran) atau berlaku global ke semua
        jenis cuti sekaligus — kemungkinan besar per-JenisCuti lebih
        masuk akal (misal Cuti Tahunan tetap tahunan, tapi ada jenis
        baru yang semesteran).
      - Perlu dijawab dulu: kebutuhan ini untuk jenis cuti BARU yang
        memang semesteran dari awal, atau Cuti Tahunan yang SUDAH ADA
        diubah supaya direset tiap 6 bulan?

      (b) Celah akumulatif saat row KuotaCuti belum ada (ditemukan
      fase 26). Selama row belum pernah dibuat, `afterApprove()` tidak
      pernah menaikkan `terpakai` (memang tidak ada rownya), jadi cuti
      yang sudah approved tidak pernah masuk hitungan. Fallback
      `default_kuota` di CutiController cuma mengurangi pengajuan yang
      masih PENDING — artinya karyawan bisa ajukan 12 hari, di-approve,
      ajukan 12 hari lagi, approve, dan seterusnya tanpa batas
      sepanjang tahun.
      - Solusi akar yang paling mungkin: `firstOrCreate` row KuotaCuti
        dari `default_kuota` saat pertama dibutuhkan, sehingga keadaan
        null tidak pernah terjadi.
      - SENGAJA belum dikerjakan: kalau auto-create dibikin sekarang,
        data yang dihasilkan harus dimigrasikan lagi begitu kunci
        row-nya berubah di (a). Makanya digabung ke sini.
      - Kalau (a) diputuskan tidak jadi dikerjakan, (b) tetap harus
        diselesaikan sendiri — ini celah nyata, bukan cuma kerapian.

      Belum ada keputusan final — didiskusikan lagi sebelum dikerjakan,
      dampaknya cukup luas (migration + unique constraint + 3 titik
      query kuota).
- [ ] Frontend scan QR + GPS + face gesture (html5-qrcode, Geolocation API,
      face-api.js/MediaPipe) — masih rencana
- [ ] Scheduler otomatis untuk jadwal:generate-bulanan (masih manual)
- [ ] Verifikasi tipe_jadwal di data production asli (nanti kalau udah deploy)
- [ ] UX form Pola Rotasi (Filament) — sekarang cuma nunjukin data mentah
      (list langkah shift/libur berurutan), admin harus bayangin sendiri
      hasil jadwalnya. Ide perbaikan: tambah preview 7-14 hari ke depan
      (kalender/tabel kecil) yang auto-generate dari `langkah` yang lagi
      diisi, update real-time. Juga cek kejelasan toggle "Hari Libur"
      (gak eksplisit OFF=kerja/ON=libur) dan 3 ikon reorder (↕️⬆️⬇️) yang
      fungsinya mirip-mirip. Trigger: dibandingin sama form serupa di
      Morhuman, sadar form sendiri kemungkinan sulit dipahami admin awam
      (bukan developer) — bukan berarti harus niru Morhuman, tapi worth
      dites ke user asli nanti.
- [ ] Tambah accessor `Lembur::getDurasiMenitAttribute()` (computed, bukan
      kolom DB) kalau nanti ada kebutuhan laporan/payroll lembur — aware
      kasus lintas tengah malam (jam_selesai < jam_mulai → +1 hari).
    
- [ ] Fix AbsensiSimulasiSeeder: waktu_masuk di-anchor ke tanggal
      baris, bukan now() — sekarang semua row simulasi punya
      DATE(waktu_masuk) != tanggal (lihat CHANGELOG fase 26 lanjutan).
      Sekalian tambah assertion/test kecil yang mengunci
      DATE(waktu_masuk) == tanggal supaya tidak kambuh.

- [ ] PengingatBelumAbsen/PengingatBelumAbsenPulang: 3 bug aktif, belum
      diperbaiki (ditemukan fase 26 lanjutan, yang diperbaiki baru cast-nya):
      - Tidak ada guard libur sama sekali: tidak cek shift->hari_kerja,
        HariLibur, maupun Cuti/Dinas approved. Karyawan yang sedang cuti
        approved TETAP dikirimi "Anda belum absen masuk" — barisnya ada tapi
        waktu_masuk null. Sama untuk Sabtu/Minggu & libur nasional.
        RekapHarian sudah benar cek hari_kerja, command ini tidak.
      - Shift malam rusak di PengingatBelumAbsenPulang: batas notifikasi
        dihitung dari jam pulang HARI INI yang sudah lewat, jadi notifikasi
        "belum absen pulang" terkirim ~15 menit setelah karyawan absen MASUK.
        Lintas tengah malam belum ditangani sama sekali.
      - KOREKSI dugaan lama: karyawan rotasi bukan salah dikirimi
        notifikasi saat libur — mereka TIDAK PERNAH dikirimi sama sekali.
        Command query dari KaryawanShift, yang sejak fase 13 cuma dimiliki
        karyawan umum. Jadi rotasi yang benar-benar lupa absen pun tidak
        diingatkan.
      - Kedua command masih tanpa test sama sekali.
      - Cast jam_masuk/jam_pulang SUDAH diperbaiki di fase 27. Tiga bug
        lain (guard libur/cuti, shift malam, rotasi tidak pernah dapat
        notifikasi) masih terbuka.

- [ ] **Keputusan enum `absensi.status = 'sakit'`** — sekarang muncul di
      dropdown form & filter tabel seolah fitur yang hidup, padahal tidak
      ada modul/controller yang pernah menghasilkannya (Known Gap sejak
      SCHEMA.md). Perlu diputuskan salah satu:
      (a) entri manual admin yang sah -> perlu keterangan di form soal
          kapan dipakai & bedanya dari Cuti jenis sakit,
      (b) digabung ke alur Cuti sebagai jenis cuti sakit -> hapus dari
          dropdown, biarkan enum-nya di DB,
      (c) dead value -> sembunyikan dari UI.
      Butuh masukan soal proses di RS-nya, bukan keputusan teknis.
      Pertanyaan yang menentukan: di rekap bulanan
      (GET /api/absensi/rekap), apakah "sakit" perlu tampil sebagai baris
      sendiri, atau cukup masuk hitungan cuti? Endpoint itu SUDAH
      menghitung 'sakit' terpisah dari 'cuti' sekarang — jadi kalau
      jawabannya "cukup masuk cuti", enum ini memang dead value dan
      barisnya di rekap juga perlu dihapus.
      Catatan: JenisCuti bisa dibuat dengan nama "Cuti Sakit" lengkap
      dengan perlu_lampiran untuk surat dokter. Kalau alur itu yang
      dipakai, Absensi.status akan terisi 'cuti' lewat sinkronisasi,
      bukan 'sakit' — jadi enum 'sakit' cuma relevan kalau RS ingin
      keduanya dibedakan di laporan.

- [ ] **Format jam di respons Izin & Lembur belum seragam** — `jam_keluar`/
      `jam_kembali` (Izin) dan `jam_mulai`/`jam_selesai` (Lembur) dikirim
      apa adanya dari DB sebagai `"08:00:00"`, sementara seluruh respons
      lain memakai `H:i`. Bukan bug (kolomnya tidak di-cast, jadi tipenya
      memang string), cuma tidak seragam. Hati-hati: menambahkan
      `->format()` di sana SALAH karena nilainya bukan Carbon — perlu
      substr/Carbon::parse, atau tambahkan cast di model lalu ikuti pola
      jamMasukString(). Sekalian cek dampaknya ke frontend yang sudah ada.

- [ ] **Jalankan `absensi:audit-menit-terlambat --fix` kalau nanti ada data
      production dari sebelum fase 27** — kolom
      `melebihi_toleransi_bulanan` tidak pernah tersimpan sejak fase 9
      (lihat CHANGELOG fase 27), jadi SEMUA baris lama salah di kolom itu.
      Command tersebut me-replay akumulasi bulanan dan menulis ulang kedua
      kolom. Tidak relevan sekarang (belum ada production), tapi jangan
      sampai terlewat saat deploy pertama.

- [ ] **`absensi:audit-menit-terlambat` belum punya test otomatis** —
      kemampuan deteksi & jalur `--fix`-nya sudah dibuktikan manual sekali
      (fase 26 lanjutan), tapi tidak meninggalkan jejak di suite. Yang
      paling rawan dan sama sekali tidak terjaga: replay akumulasi bulanan
      lintas chunk (state dibawa lewat reference antar-chunk).

## Ditunda — Sinkronisasi Frontend
Frontend (Flutter, `absensi_frontapp`) freeze sejak ~fase 5 (auth, absensi,
notifikasi, riwayat sudah ada). Sengaja belum disentuh selama backend masih
sering berubah — hindari kerja dobel. Ditunda sampai backend dianggap stabil
(setelah Prioritas di atas kelar).

Modul backend yang BELUM ada implementasi frontend-nya sama sekali:
- Cuti/Izin/Lembur/Dinas (API sejak fase 19)
- Tukar Jadwal (API sejak fase 20)

Saat mulai lagi nanti: cek dulu apakah response format API sudah berubah
dari yang diasumsikan kode frontend existing (auth/absensi/notifikasi),
sebelum nambah modul baru.
