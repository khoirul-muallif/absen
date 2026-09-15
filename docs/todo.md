# TODO — absensi-app

> Isi aktif aja — kalau item selesai, hapus (bukan di-checklist doang) biar
> file ini tetep pendek dan gampang di-scan. Detail alasan/histori tetap di
> CHANGELOG.md, bukan di sini.

## Prioritas — Evaluasi Solidity & UX Backend
Trigger: development kerasa lancar banget, gak ada hambatan — itu sinyal
buat curiga, kemungkinan besar test yang ada baru cover happy path.

Sudah selesai (navigationGroup, audit lintas modul, race condition, tanggal
edge case, konsistensi respons API, 5 modul approval + Jenis/Kuota Cuti,
sentralisasi query kuota + audit ViewLembur) — detail di CHANGELOG fase
20-26. 277 test passing (789 assertions).

- [ ] **Review UX Filament — 10 Resource lain (belum disentuh)**,
      dikelompokkan per navigationGroup. Ini sekarang satu-satunya item
      Prioritas yang tersisa.

      Presensi:
      - [ ] Hari Libur (HariLiburResource)
      - [ ] Data Absensi (AbsensiResource) — perlu perhatian ekstra:
            ada enum status 'sakit' yang belum jelas peruntukannya
            (lihat SCHEMA.md Known Gap), cek juga apakah UX form manual
            create Absensi (bukan lewat API) sudah jelas bedanya dengan
            data yang auto-tersinkron dari Cuti/Dinas approved.
      - [ ] Jadwal (JadwalResource) — cek kejelasan field `sumber`
            (generate/manual) di UI, apakah admin ngerti konsekuensi
            edit manual pada baris yang sumbernya 'generate'.

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

      Urutan yang disarankan: Data Absensi & Jadwal dulu (paling sering
      diakses harian utk operasional), baru Master Data.

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
