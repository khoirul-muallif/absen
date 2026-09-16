# CHANGELOG — absensi-app versi 2

> History detail per-fase. **Jangan upload file ini ke chat baru** — cukup dibuka lokal, atau minta Claude baca kalau lagi butuh nelusurin keputusan/bug lama. Ringkasan status terkini ada di `PROJECT_CONTEXT.md`.

> Catatan teknis: seluruh history commit (fase 1 s/d fase 10) pernah dirapikan lewat `git rebase -i --root` pada 16 Juli 2026 dan di-push paksa (`git push --force-with-lease`). Kalau clone repo ini di device lain dan histori terasa aneh, sync ulang dengan `git fetch` + `git reset --hard origin/main`.

---

## fase 36: Review UX QR Instansi (QrInstansiResource) — selesai

Commit `5b8d1bd`. **Resource terakhir — seluruh 17 Filament Resource selesai
direview.**

### BUG: kolom "Aktif" menyesatkan untuk QR kedaluwarsa

Tabel cuma menampilkan `is_active`, jadi QR yang `expired_at`-nya sudah lewat
tetap bercentang hijau padahal pemindaiannya ditolak. `isValid()` sudah ada di
model sejak fase 1 tapi **tidak pernah dipakai di UI sama sekali** — kelas
masalah yang sama dengan label "Aktif" di KaryawanShift (fase 31).

Diganti kolom Status tiga keadaan lewat `QrInstansi::statusValiditas()`:
Berlaku / Kedaluwarsa / Nonaktif. Ditambah filter "Hanya yang masih berlaku"
yang benar-benar menjawab "QR mana yang bisa dipakai sekarang" — beda dari
filter `is_active` yang mengabaikan kedaluwarsa.

### BUG: helper text `kode_qr` menjanjikan yang mustahil

Tertulis "Kosongkan dan simpan untuk generate otomatis", padahal field-nya
`required()` sehingga mengosongkannya justru gagal validasi. Kodenya memang
sudah ter-generate otomatis sebagai `default()` — kalimatnya keliru arah.

### `kode_qr` bebas diubah kapan saja

Begitu QR pernah dipakai absen, fisiknya sudah dicetak dan ditempel. Mengubah
kodenya membuat semua QR terpasang jadi tidak valid — karyawan tidak bisa
absen dan tidak ada yang tahu penyebabnya. Sekarang terkunci, dengan arahan
membuat QR baru lalu menonaktifkan yang lama supaya riwayat absensinya tetap
bisa ditelusuri.

### Tidak ada guard hapus

`absensi.qr_instansi_id` memakai `ON DELETE RESTRICT`, jadi menghapus QR yang
pernah dipakai melempar `QueryException` 1451 mentah. Guard dipasang di tabel,
hapus massal, dan halaman View.

### Item todo `expired_at` null = permanen

Selain helper text, ditambah Placeholder reaktif yang menyatakan konsekuensinya
langsung: kosong berbunyi "♾️ PERMANEN — berlaku selamanya sampai dinonaktifkan
manual". Keadaan kosong jadi terbaca sebagai **pilihan**, bukan sebagai field
yang belum diisi. Kalau tanggalnya sudah lewat, langsung diperingatkan bahwa QR
akan mati begitu disimpan.

### Status

`QrInstansiResourceTest` dibuat dari nol: 17 test.
Full suite: **457 test passing (1447 assertions)** — naik dari 440.

**Belum dikerjakan:** gambar QR-nya sendiri tidak pernah ditampilkan. Menu
bernama "QR Instansi" cuma memberi string 32 karakter yang harus disalin ke
generator eksternal untuk dicetak. Butuh paket tambahan — masuk todo.

---

## fase 35: Review UX Instansi (InstansiResource) — selesai

Commit `3f06aa7`.

### Guard hapus dengan taruhan terbesar dari semua Resource

Lima tabel menggantung ke `instansi_id` — karyawan, shift, qr_instansi,
hari_liburs, pola_rotasis — dan lewat karyawan, **seluruh riwayat absensi &
pengajuan ikut** karena semua FK `karyawan_id` memakai `ON DELETE CASCADE`.
Menghapus satu instansi berpotensi melenyapkan hampir seluruh isi sistem, dan
selama baru ada satu instansi, itu berarti semuanya. Sebelumnya tanpa
peringatan apa pun.

`Instansi::sedangDipakai()` menutup kelimanya. Relasi `hariLiburs()` dan
`polaRotasis()` ditambahkan — sebelumnya tidak pernah didefinisikan walau
kedua tabel punya `instansi_id`.

### BUG: latitude & longitude tanpa batas nilai

Keduanya tertukar tersimpan tanpa keluhan. Akibatnya SEMUA absen ditolak "di
luar radius" tanpa petunjuk penyebabnya — dan tidak ada apa pun di UI yang
mengarahkan admin ke sana. Sekarang dibatasi -90..90 dan -180..180.

Batas itu saja belum cukup: lintang 11 dan bujur 7 dua-duanya sah secara angka
tapi jatuh di Afrika. Ditambah Placeholder reaktif yang mengecek apakah titik
masuk wilayah Indonesia, dan kalau tidak, memeriksa apakah nilainya cocok bila
lat-lng ditukar lalu **menyebut angka yang seharusnya**. Peringatan lunak,
tidak menolak submit — suatu saat bisa saja ada instansi di luar negeri.

### KEPUTUSAN: `kode_instansi` dikunci setelah ada karyawan atau QR

Helper text lama berbunyi "tidak bisa diubah setelah dipakai QR" — **dua-duanya
keliru**. Tidak ada apa pun yang mencegah perubahan, DAN kode ini tidak dipakai
pemindaian QR sama sekali: endpoint `/api/instansi/qr/{kode}` mencari
`QrInstansi.kode_qr`, kolom yang sepenuhnya terpisah. Yang benar, kode ini
dikirim ke aplikasi mobile lewat `/api/auth/me` sebagai identitas instansi.

### Status

`InstansiResourceTest` dibuat dari nol: 20 test, termasuk dua untuk
`dalamRadius()` yang selama ini dipakai validasi absen tapi tidak pernah dites
lewat Resource-nya.

Full suite: 420 → 440 test passing.

---

## fase 34: Review UX Karyawan (KaryawanResource) — selesai

Commit `f9f9f0a`.

### BUG PALING BERBAHAYA: menghapus karyawan melenyapkan seluruh riwayatnya

Semua FK ke `karyawan_id` memakai `ON DELETE CASCADE` (SCHEMA.md), jadi satu
klik Hapus ikut menghapus absensi, cuti, izin, lembur, dinas, jadwal, kuota,
dan penugasan shift orang itu — permanen, tanpa peringatan.
`DeleteBulkAction` bisa melakukannya untuk banyak orang sekaligus. Padahal
`is_active` sudah ada persis untuk kasus "karyawan sudah tidak bekerja".

`Karyawan::punyaRiwayat()` mengecek delapan relasi. Tombol hapus disembunyikan
kalau ada riwayat, diganti versi abu-abu yang menjelaskan apa yang akan hilang.

### `tipe_jadwal` tidak muncul di tabel sama sekali

Bukan kolom, bukan filter — padahal itu field yang paling banyak mencabangkan
perilaku sistem (fase 13) dan menentukan menu mana yang dipakai untuk
menjadwalkan orangnya. Admin tidak punya cara melihat siapa umum dan siapa
rotasi selain membuka satu per satu. Sekarang jadi kolom badge + filter, plus
filter `unit_kerja`.

### KEPUTUSAN: ubah `tipe_jadwal` ditolak selama masih punya penugasan

Sebelumnya bebas, dan hasilnya adalah baris yang menurut guard fase 18
seharusnya mustahil — karyawan rotasi yang punya `KaryawanShift`, atau
sebaliknya. Anomali itu selama ini baru terdeteksi **belakangan** oleh command
`karyawan:cek-tipe-jadwal` (fase 13); lebih masuk akal dicegah di sumbernya.
Guard cuma aktif kalau nilainya benar-benar berubah, jadi menyunting
jabatan/telepon tetap bisa.

### `unit_kerja` jadi wajib untuk tipe rotasi, plus datalist

Keputusan fase 33 membuat assignment pola ditolak kalau `unit_kerja` karyawan
tidak sama persis dengan unit pola — jadi kolom teks bebas ini berubah jadi
kunci keras, dan karyawan rotasi tanpa `unit_kerja` tidak akan pernah bisa
dijadwalkan. Datalist menggabungkan unit dari karyawan dan dari pola rotasi.

`Karyawan::karyawanPolaRotasis()` ditambahkan — pasangan dari
`karyawanShift()` untuk tipe rotasi, sebelumnya tidak pernah didefinisikan.

`Hash::make()` di form dihapus; model sudah punya cast `'password' => 'hashed'`.
Keduanya tidak bikin double hash (cast mengecek `Hash::isHashed()` dulu), tapi
menyisakan dua tempat yang seolah bertanggung jawab atas hal yang sama. Ada
test yang memastikan password tetap tersimpan sebagai hash.

### Catatan proses: 19 test dilewati diam-diam

File test sempat bernama `Karyawanresourcetest.php`. PHPUnit menemukan test
lewat suffix `Test.php` dengan T besar, jadi 19 test itu **dilewati tanpa pesan
error apa pun** — di Windows filesystem case-insensitive sehingga tidak ada
keluhan. Ketahuan cuma karena jumlah suite tidak bertambah (401 → 401).

Jebakan ketiga soal file test yang salah tempat/nama, dan yang paling senyap:
dua sebelumnya (fase 27 & 29) setidaknya melempar error.

Full suite: 401 → 420 test passing.

---

## fase 33: Review UX Shift Karyawan Rotasi (KaryawanPolaRotasiResource) — selesai

Commit `76b1a46`. Menuntaskan grup Manajemen Rotasi.

### BUG: `posisiSiklusPada()` bisa melempar DivisionByZeroError

Baris terakhirnya `$selisihHari % $panjangSiklus`, dan fase 32 baru saja
menetapkan bahwa pola dengan `langkah` array kosong memang mungkin terjadi
lewat seeder atau insert langsung. Yang memanggil method ini bukan cuma
Filament tapi `GenerateJadwalRotasi`, jadi generator bisa mati di tengah jalan.
Sekarang melempar `LogicException` yang menyebut pola mana yang bermasalah, dan
form menolak meng-assign pola tanpa langkah sejak awal.

### BUG: `diffInDays()` mengembalikan nilai absolut

Dipanggil tanpa argumen kedua, jadi untuk tanggal **sebelum** `tanggal_mulai`
posisinya tetap dihitung positif — assignment yang mulai 10 Agustus, ditanya
posisi 5 Agustus, menjawab seolah sudah berjalan 5 hari. Pola bug yang sama
dengan `hitungMenitTerlambat()` di fase 14; komentar di method ini bahkan
menyebut kemiripannya, tapi sisi tanggal-sebelum-anchor tidak ikut ditutup.

Sekarang selisihnya bertanda lalu dinormalisasi ke 0..n-1. Ditambah
`berlakuPada()` supaya pemanggil punya cara menyaring tanggal di luar masa
berlaku. **Belum diverifikasi** apakah `GenerateJadwalRotasi` sudah menyaring —
masuk todo.

### Irisan periode, dengan akibat lebih parah dari KaryawanShift

Dua assignment beririsan berarti dua `tanggal_mulai` berbeda sebagai anchor,
dan anchor itulah yang menentukan **seluruh urutan siklus** — bukan cuma shift
mana yang dipakai pada satu hari. Transisi berurutan tetap diizinkan.

### Dropdown pola tidak dibatasi apa pun

Kejadian **ketiga berturut-turut** setelah KaryawanShift (fase 31) dan
PolaRotasi (fase 32). Sekarang difilter dan divalidasi ke instansi karyawan.

**KEPUTUSAN:** pola yang `unit_kerja`-nya berbeda dari karyawan DITOLAK, bukan
sekadar diperingatkan. Pola IGD di-assign ke karyawan Rawat Jalan itu keliru
secara logika, dan karena `unit_kerja` sudah punya datalist di PolaRotasiForm
(fase 32), risiko penolakan gara-gara beda penulisan sudah berkurang.

### Infolist: preview ber-anchor assignment

Preview 14 harinya dihitung memakai anchor assignment ini, bukan "hari ini"
seperti preview generik di halaman Pola Rotasi — jadi tanggal yang ditampilkan
benar-benar sesuai jadwal yang akan digenerate untuk karyawan tersebut.
Menutup sebagian item todo "anchor preview siklus belum bisa dipilih".

Label "Aktif" diganti kolom Status tiga keadaan (sama seperti perbaikan di
KaryawanShift fase 31), ditambah kolom "Shift hari ini" yang menghitung posisi
siklus aktual.

### Status

`KaryawanPolaRotasiResourceTest`: 5 → 18 test (fixture lama harus disesuaikan
karena guard `unit_kerja`), plus 5 unit test baru untuk `posisiSiklusPada()`
dan `berlakuPada()`.

Full suite: 384 → 401 test passing.

---

## RINGKASAN: Review UX 17 Filament Resource selesai (fase 25–36)

Item Prioritas yang berjalan sejak fase 25 tuntas. Test naik **229 → 457**
(621 → 1447 assertions) sepanjang dua belas fase.

Pola bug yang berulang, dan berapa kali ditemukan:

| Pola | Ditemukan di |
|---|---|
| Constraint DB tidak divalidasi di form (1062/1451 bocor mentah) | KuotaCuti 25, Absensi 27, Shift 30, PolaRotasi 32, Karyawan 34, Instansi 35, QrInstansi 36 |
| Dropdown tidak dibatasi ke instansi terkait | KaryawanShift 31, PolaRotasi 32, KaryawanPolaRotasi 33 |
| Helper text menyatakan hal yang tidak benar | Shift 30 (toleransi), HariLibur 29 (cuti bersama), Instansi 35 (kode), QrInstansi 36 (generate) |
| Label status hanya membaca satu kolom, mengabaikan yang lain | KaryawanShift 31, KaryawanPolaRotasi 33, QrInstansi 36 |
| Guard ada di tabel tapi bolong di halaman lain | ViewCuti/Dinas/TukarJadwal 25, Shift 30 |
| Halaman View & Infolist tidak ada | 10 dari 17 Resource |
| Resource sama sekali tanpa test | HariLibur 29, Shift 30, Karyawan 34, Instansi 35, QrInstansi 36 |

Yang tidak terduga: sebagian besar bug paling serius bukan soal UX sama
sekali. Kolom KPI yang tidak pernah tersimpan (fase 27), jebakan cast
`datetime:H:i` di lima titik (fase 27), kuota terhitung ganda oleh command
backfill (fase 26), `DivisionByZeroError` yang menunggu di generator rotasi
(fase 33) — semuanya ketemu karena membaca kode Resource baris per baris,
bukan karena mencarinya.

## fase 32: Review UX Pola Rotasi (PolaRotasiResource) — selesai

Commit `69f1e2e` (batch A) dan `0382b4f` (batch B & C).

### BUG: dropdown shift di Repeater mengambil semua instansi

`Shift::query()->pluck('nama_shift', 'id')` — tanpa filter `instansi_id`,
padahal `pola_rotasis` punya kolom itu. Jadi pola milik satu instansi bisa
memakai shift milik instansi lain, dan **tidak ada guard server-side sama
sekali**. Bug yang sama dengan KaryawanShift di fase 31, tapi di sini tanpa
pembanding apa pun. Sekarang dropdown difilter DAN divalidasi, plus
`labelLengkap()` dipakai supaya dua shift bernama sama dengan jam berbeda bisa
dibedakan (fase 30).

### N+1 di itemLabel

`itemLabel` memanggil `Shift::find()` per baris. Siklus 21 hari berarti 21
query tiap kali Repeater dirender ulang — dan tiap toggle memicu render ulang.
Diganti cache per-request.

### `count($record->langkah)` di kolom tabel

Dipindah ke `PolaRotasi::panjangSiklus()`. Kolom `langkah` ternyata **NOT
NULL** di DB, jadi null-safety-nya murni defensif, bukan menutup bug nyata —
dua test yang sempat ditulis untuk skenario null gagal di level constraint dan
diganti dengan skenario array kosong. Array kosong memang bisa terjadi lewat
seeder atau insert langsung karena `minItems(1)` cuma berlaku di form; pola
seperti itu dilewati generator tanpa pesan apa pun, jadi sekarang ditandai
badge merah di tabel.

### Tidak ada guard hapus

Perilaku FK `karyawan_pola_rotasis.pola_rotasi_id` tidak disebut di SCHEMA.md
— kalau CASCADE, menghapus pola menghilangkan assignment diam-diam dan
karyawan rotasi kehilangan sumber jadwalnya; kalau RESTRICT, muncul
`QueryException` 1451 mentah. `PolaRotasi::sedangDipakai()` menutup kedua
kemungkinan, dengan pola tombol yang sama seperti Shift di fase 30 (versi
abu-abu yang menjelaskan, bukan tombol yang hilang begitu saja). Guard dipasang
di tabel, hapus massal, dan halaman View.

### KEPUTUSAN: `nama_pola` wajib unik per (instansi, unit_kerja)

Beda dari `nama_shift` yang sengaja dibiarkan boleh duplikat (fase 30), di sini
`unit_kerja` sudah jadi pembeda tersendiri — jadi nama kembar tidak punya
alasan struktural dan cuma bikin dropdown Shift Karyawan Rotasi ambigu. Nama
yang sama di unit berbeda tetap diizinkan.

### `unit_kerja` diberi datalist

Kolom ini adalah kunci yang dipakai opsi `--unit` pada generator rotasi. Satu
typo berarti polanya tidak pernah ikut tergenerate — tanpa pesan error apa pun,
karena generator cuma tidak menemukan apa-apa. Sekarang unit yang sudah ada
ditampilkan sebagai saran.

### Preview siklus (item todo lama, akhirnya dikerjakan)

Form sebelumnya cuma menampilkan data mentah — admin harus membayangkan
sendiri hasil jadwalnya.

Preview 14 hari ditambahkan, reaktif terhadap langkah yang sedang diisi.
Kalau `berlaku_saat_libur_nasional` mati dan ada libur nasional di rentang itu,
barisnya ditandai "di-override libur nasional" sehingga konsekuensi toggle
terlihat langsung. Di atas tabel ada peringatan bahwa preview menganggap siklus
dimulai hari ini, sementara anchor sebenarnya `tanggal_mulai` per karyawan —
urutan shift-nya benar, tanggalnya belum tentu.

**Perhitungannya dipindah ke `PolaRotasi::hitungPreviewSiklus()` di batch B**,
mengembalikan array alih-alih HTML. Versi batch A merakit HTML langsung di
dalam Placeholder, jadi satu-satunya assertion yang mungkin cuma mencocokkan
potongan kalimat — bukan kebenaran posisi siklusnya. Dibuat static supaya
dipakai form (state Repeater yang belum tersimpan) maupun infolist (record),
sehingga tidak ada dua versi perhitungan yang bisa menyimpang.

### Toggle "Hari Libur" diperjelas

Diganti jadi pertanyaan "Hari ini libur?" dengan helper text yang berubah
mengikuti posisinya: OFF → "KERJA — pilih shift-nya di sebelah", ON → "LIBUR —
karyawan tidak dijadwalkan". Menutup keluhan "tidak eksplisit OFF=kerja/
ON=libur" di todo. **Ikon reorder belum disentuh** — perlu dilihat langsung di
browser dulu.

### Halaman View + Infolist

Infolist menampilkan arti `berlaku_saat_libur_nasional` sebagai kalimat (bukan
cuma ikon centang), urutan siklus sebagai daftar "Hari ke-N → shift/libur",
preview 14 hari, dan section **"Dipakai Oleh"**: tiap karyawan yang di-assign
beserta `tanggal_mulai` DAN shift apa yang dia dapat hari ini lewat
`posisiSiklusPada(today())`. Staggered start — dua karyawan di pola sama dengan
anchor berbeda — selama ini cuma konsep di kepala; di sini jadi konkret.

### Catatan proses

Model `PolaRotasi` sempat punya `karyawanPolaRotasis()` dan `panjangSiklus()`
ter-deklarasi dua kali: blok tambahan ditempel utuh padahal dua method itu
sudah ada. Fatal error "Cannot redeclare method" sebelum apa pun jalan.
Pelajaran yang sama seperti file test yang dua kali tersimpan ke path salah —
lebih aman meminta file aslinya dulu daripada memberi blok tempel untuk file
yang belum pernah dilihat.

Batch A juga sempat di-commit **tanpa test sama sekali** (365 tetap 365)
meski menambahkan empat guard dan satu fitur; test menyusul lewat amend.

### Status

`PolaRotasiResourceTest`: 5 → 24 test. Full suite: **384 test passing (1222
assertions)** — naik dari 365.

---

## fase 31: Review UX Shift Karyawan Umum (KaryawanShiftResource) — selesai

Commit `7cc5053` (batch A) dan `c74e6e6` (batch B & C).

### BUG: tidak ada validasi periode tumpang tindih

Tabel `karyawan_shift` cuma punya index `(karyawan_id, tanggal_berlaku)`, bukan
unique, dan form tidak mengecek apa pun. Jadi satu karyawan bisa punya dua
penugasan yang periodenya beririsan.

Akibatnya bukan sekadar data berantakan: `AbsensiController::masuk()` memilih
penugasan lewat `latest('tanggal_berlaku')->first()`, sehingga dengan dua
penugasan ber-`tanggal_berlaku` sama, shift mana yang dipakai **tidak
deterministik** antar request — status dan `menit_terlambat` karyawan bisa
berbeda tergantung urutan baris yang dikembalikan MySQL.
`GenerateJadwalBulanan` juga mengambil penugasan yang overlap bulan target,
jadi jadwal yang digenerate bisa salah shift tanpa ada yang tahu.

**KEPUTUSAN: irisan apa pun ditolak.** Sempat dipertimbangkan melonggarkannya
untuk kasus "jadwal terpecah" — `Shift` punya kolom `hari_kerja`, jadi dua
penugasan beririsan dengan hari kerja yang tidak bertabrakan (Senin–Rabu pagi,
Kamis–Jumat siang) sebenarnya masuk akal secara model. Ditolak karena (a)
dikonfirmasi tidak ada staf umum yang begitu di RS ini, dan (b)
`AbsensiController::masuk()` tidak pernah melihat `hari_kerja` sama sekali,
jadi skenario itu akan tetap salah hitung walau datanya diizinkan. Kalau nanti
dibutuhkan, pelonggaran harus dikerjakan bersamaan dengan perbaikan controller.

Transisi berurutan (berakhir 31 Jul, berlaku mulai 1 Agu) sengaja TIDAK
dianggap beririsan. Penugasan lama yang open-ended juga terdeteksi — pesan
errornya menyebut shift mana dan periodenya supaya admin tahu yang mana harus
diakhiri dulu.

### BUG: dropdown shift tidak dibatasi ke instansi karyawan

Tidak ada `modifyQueryUsing` maupun rule server-side, jadi karyawan bisa
di-assign shift milik instansi lain tanpa ada yang menolak — sisi shift
terlewat waktu guard `karyawan_id` dipasang di fase 18. Sekarang dropdown
difilter DAN divalidasi server-side, simetris dengan pola `karyawan_id`.

### Label "Aktif" salah arti

Sebelumnya kolom tanggal berakhir punya description `'Aktif'` yang cuma
menandai `tanggal_berakhir` null — itu berarti "berlaku sampai diganti",
**BUKAN** "sedang berlaku". Penugasan yang mulai bulan depan & open-ended ikut
ditandai "Aktif", sementara penugasan yang berakhir akhir bulan ini — yang
justru sedang berlaku — tidak ditandai apa pun.

Diganti kolom Status dengan tiga keadaan (Sedang berlaku / Belum mulai / Sudah
berakhir) yang dihitung dari tanggal hari ini, plus filter toggle "Hanya yang
sedang berlaku".

### Halaman View + Infolist

Infolist menampilkan rincian shift (jam masuk/pulang, hari kerja, mode
toleransi) langsung di halaman penugasan — admin yang membukanya hampir pasti
ingin tahu jam kerjanya, dan tanpa ini harus pindah ke menu Shift lalu kembali.
Ada juga section riwayat penugasan lain karyawan yang sama, berguna justru
karena aturan irisan yang baru: kalau penugasan baru ditolak, admin bisa
langsung melihat mana yang bentrok.

`DeleteAction` diberi `modalDescription`. Menghapus penugasan aman secara
teknis (tidak ada FK RESTRICT ke `karyawan_shift`), tapi konsekuensinya nyata:
karyawan jadi tanpa shift pada periode itu, absennya ditolak API dengan "Tidak
ada shift aktif untuk hari ini", dan jadwalnya tidak ikut digenerate. Modalnya
menyarankan mengisi tanggal akhir alih-alih menghapus.

### Perbaikan kecil

- Ikon diganti ke `OutlinedUserGroup`. Sebelumnya `OutlinedCalendarDays`, yang
  jadi kembar dengan `HariLiburResource` setelah ikon itu diganti di fase 29.
- `Shift::labelLengkap()` dipasang di dropdown, menyisakan Repeater
  `PolaRotasiForm` (selesai di fase 32).
- Nama shift di tabel diberi description berisi jamnya.

### Test

5 → 17 test. Kelima test lama **harus** disesuaikan karena semuanya membuat
`Karyawan` dan `Shift` tanpa menyamakan instansi — guard baru menolak fixture
seperti itu. Test "menampilkan daftar" juga diperbaiki: sebelumnya cuma
`assertSuccessful()` tanpa memeriksa isi tabel, jadi halaman kosong pun lolos.

Full suite naik 352 → 365 (1167 assertions).

## fase 30: Review UX Shift (ShiftResource) — selesai

Commit `08894b1` (batch A), `fa4a7d6` (label dropdown), `3af8d4b` (batch B & C).

### BUG: helper text `toleransi_menit` menyatakan hal yang salah

Tertulis "Karyawan masih dianggap tepat waktu dalam batas ini" — padahal
`tentukanStatus()` menandai `'terlambat'` begitu lewat 0 menit dan **tidak
pernah melihat `toleransi_menit` sama sekali**. Perilaku itu sudah
terdokumentasi di QUICK_CONTEXT dan dikunci dataset eksplisit di `ShiftTest`
sejak fase 14 ("14 menit lewat, masih dalam toleransi_menit tapi tetap
terlambat").

Jadi form-nya justru menyebarkan kesalahpahaman yang ingin dicegah oleh item
todo ini. Helper text diganti jadi menyebut eksplisit bahwa angka itu TIDAK
memengaruhi status harian, dan helper `mode_toleransi` dibuat reaktif
mengikuti mode yang sedang dipilih.

### BUG: DeleteAction tanpa guard, padahal FK-nya RESTRICT

`absensi.shift_id` memakai `ON DELETE RESTRICT` (SCHEMA.md). Menghapus shift
yang pernah dipakai absensi melempar `QueryException` 1451 mentah ke layar —
kelas bug yang sama dengan 1062 di KuotaCuti (fase 25) dan Absensi (fase 27),
cuma beda nomor.

- `Shift::sedangDipakai()` mengecek **tiga** relasi: absensi, jadwals,
  karyawan_shift. Yang terdokumentasi RESTRICT cuma absensi, tapi perilaku FK
  dua relasi lain belum dipastikan — kalau ternyata CASCADE, penghapusan akan
  diam-diam menghilangkan jadwal atau assignment periode. Memblokir ketiganya
  aman untuk kedua kemungkinan.
- Guard dipasang di tabel, halaman Edit, DAN halaman View secara terpisah.
  Pola "tabel sudah benar tapi halaman lain bolong" sudah jadi bug di
  ViewCuti/ViewDinas/ViewTukarJadwal (fase 25).
- Tombol hapus tidak sekadar hilang: ada versi abu-abu yang menjelaskan kenapa
  dan menyarankan menonaktifkan shift.
- **KEPUTUSAN:** pada hapus massal, kalau satu shift dalam batch masih dipakai
  maka TIDAK ADA yang terhapus — termasuk yang sebenarnya bebas. Lebih aman
  daripada menghapus sebagian tanpa admin sadar mana yang lolos.

### Tidak ada validasi durasi nol

`jam_masuk == jam_pulang` lolos tersimpan. Shift malam (pulang di dini hari
keesokan harinya) tetap **SAH**, jadi aturannya bukan "pulang harus setelah
masuk" melainkan "tidak boleh sama" — keputusan yang sama sudah diambil untuk
Lembur di fase 25.

### KEPUTUSAN: `nama_shift` boleh duplikat dalam satu instansi

Dugaan awal: ini bug `JenisCuti.nama` fase 25 yang terulang. Ternyata bukan.

Tabel `shift` **tidak punya kolom `unit_kerja`** (yang punya itu
`pola_rotasis`). Jadi satu-satunya cara merepresentasikan jam masuk berbeda
antar unit — IGD masuk 07:00, Rawat Jalan 08:00 — adalah dua baris yang
sama-sama bernama "Pagi". Itu data yang sah. Melarang duplikat akan memaksa
penamaan "Pagi IGD", yang menyelundupkan informasi unit ke dalam kolom nama —
hal yang justru dilarang di project ini untuk `tipe_jadwal`.

Yang bermasalah cuma dropdown yang menampilkan keduanya sebagai teks identik.
Jadi yang diperbaiki labelnya: `Shift::labelLengkap()` mengembalikan
`"Pagi (07:00–14:00)"`. Sudah dipasang di dropdown `AbsensiForm` &
`JadwalForm`; `KaryawanShiftForm` dan Repeater langkah di `PolaRotasiForm`
menyusul saat gilirannya direview.

Duplikat **persis** (nama DAN jam sama) ditandai lewat peringatan di helper
text, bukan penolakan — mengikuti pola helper `terpakai` di KuotaCuti fase 25.

### Halaman View + Infolist

`ViewShift` + `ShiftInfolist` ditambahkan, `ViewAction` masuk ke `ActionGroup`.
Infolist tidak berhenti di menampilkan kolom:

- Section **"Toleransi Keterlambatan"** punya baris "Artinya" yang
  menerjemahkan kombinasi mode + angka jadi kalimat. Menampilkan "Per Hari" dan
  "15 menit" berdampingan justru mengundang kesimpulan keliru bahwa 15 menit
  itu batas toleransi harian.
- Section **"Pemakaian"** menampilkan jumlah penugasan karyawan, baris jadwal,
  dan baris absensi yang memakai shift ini, plus kalimat apakah shift bisa
  dihapus. Tombol hapus yang disembunyikan jadi punya penjelasan angkanya.
- Durasi shift dihitung sadar lintas tengah malam: 22:00→07:00 muncul sebagai
  "9 jam (melewati tengah malam)", bukan angka negatif.

### Celah test yang ditutup

`DeleteBulkAction::before()` dengan `$action->cancel()` (batch A) sama sekali
tidak tersentuh test, padahal API itu belum pernah dipakai di project ini —
error-nya baru akan muncul saat admin benar-benar memakai hapus massal.
Ditambahkan dua test di batch B: satu memastikan batch dibatalkan kalau ada
shift terpakai, satu memastikan hapus massal tetap jalan normal kalau semuanya
bebas.

### TEMUAN: grup menu di todo.md ternyata basi

Pengecekan `navigationGroup` di seluruh Resource mengungkap **lima** grup,
bukan tiga seperti yang tercatat:

| Grup | Isi |
|---|---|
| Presensi | Data Absensi, Jadwal, Hari Libur |
| Pengajuan & Cuti | Cuti, Izin, Lembur, Dinas, Tukar Jadwal, Jenis Cuti, Kuota Cuti |
| Manajemen Shift | Shift, Shift Karyawan Umum |
| Manajemen Rotasi | Pola Rotasi, Shift Karyawan Rotasi |
| Master Data | Karyawan, Instansi, QR Instansi |

`todo.md` menaruh Shift, Shift Karyawan Umum/Rotasi, dan Pola Rotasi di bawah
Master Data — pengelompokan dari sebelum commit `4257e23` ("fase 20:
merapikan sidebar menu"). Sudah dikoreksi.

**Efek samping reorganisasi itu yang belum pernah dicatat:** CHANGELOG fase 15
menyebut "Shift Karyawan Umum" dan "Shift Karyawan Rotasi" sengaja dinamai
paralel dan ditaruh bersebelahan supaya jelas keduanya pasangan untuk dua
`tipe_jadwal` yang berbeda. Sekarang keduanya berada di **grup yang berbeda**,
jadi tidak lagi bersebelahan — maksud desain itu hilang tanpa pernah
diputuskan. Admin yang salah pilih menu tidak punya petunjuk visual bahwa ada
pasangannya di grup lain. Relevan langsung dengan item todo "cek guard
tipe_jadwal sudah tervisualisasi jelas"; masuk todo.

### Perbaikan kecil

- Helper text `sumber` di `JadwalForm` yang menyebut
  `jadwal:generate-rotasi --overwrite-generate` diganti bahasa hasil, sesuai
  larangan yang masuk QUICK_CONTEXT di fase 29. Tiga tempat lain masih
  tercatat di todo.
- Kolom nama shift di tabel diberi `description` berisi jamnya.
- Badge toleransi jadi abu-abu kalau mode harian (angkanya memang tidak
  dipakai), dengan tooltip yang menjelaskan.
- Filter `mode_toleransi` ditambahkan.

### Status

`ShiftResourceTest` dibuat dari nol: 0 → 18 test. Resource ini sebelumnya
cuma punya unit test model.

Full suite: **352 test passing (1118 assertions)** — naik dari 334.

Sisa review UX Filament: 6 Resource — Shift Karyawan Umum (Manajemen Shift),
Pola Rotasi & Shift Karyawan Rotasi (Manajemen Rotasi), Karyawan, Instansi,
QR Instansi (Master Data).

## fase 29: Review UX Hari Libur (HariLiburResource) — selesai

Commit `c6328d7` (batch A) dan `a0939ad` (batch B & C).

### BUG: validasi unique salah cakupan — arahnya KEBALIKAN dari biasanya

Constraint DB-nya `unique(instansi_id, tanggal)`, tapi form cuma memvalidasi
kolom `tanggal` tanpa `modifyRuleUsing` untuk menyertakan `instansi_id`.
Efeknya form jadi **lebih ketat daripada DB**: instansi kedua tidak bisa
mendaftarkan tanggal yang sudah dipakai instansi pertama — data yang sah
ditolak.

Ini kebalikan dari tiga kasus sebelumnya (KuotaCuti fase 25, Absensi fase 27)
yang form-nya justru terlalu longgar sehingga QueryException 1062 bocor ke
admin. Pola yang benar sudah ada di `JadwalForm` sejak awal, cuma tidak
diterapkan di sini. Belum berdampak karena baru ada satu instansi — diam
sampai ada yang kedua.

### Menambah hari libur tidak menyentuh Jadwal yang sudah digenerate

Alurnya wajar dan gampang terjadi: `jadwal:generate-bulanan` dijalankan dulu,
baru admin sadar ada hari libur yang terlewat. Baris Jadwal tetap `reguler`
dan `RekapHarian` tetap menandai alpha bagi yang tidak absen — tanpa
peringatan apa pun.

Ditambahkan Placeholder reaktif di form yang menghitung berapa jadwal kerja
non-libur sudah ada di tanggal yang sedang diisi, beserta arahan cara
memperbaikinya. (Teks arahannya sendiri ternyata masih bahasa programmer —
lihat catatan di akhir entri ini.)

### TEMUAN: `is_cuti_bersama` bukan kolom mati, tapi sumber data salah

Dugaan awal: kolom mati seperti enum `'sakit'`. Ternyata bukan.

**Kebijakan RS: saat cuti bersama karyawan TETAP MASUK** — yang ingin libur
harus mengajukan cuti seperti hari biasa. Sementara sistem memperlakukan baris
ini persis sama dengan libur nasional:
- `GenerateJadwalBulanan` → Jadwal `jenis='libur'`
- `GenerateJadwalRotasi` → menimpa jadi libur, kecuali polanya punya
  `berlaku_saat_libur_nasional`
- `RekapHarian` → Absensi `status='libur'`, bukan alpha

Jadi begitu admin mendaftarkan cuti bersama, seluruh karyawan **umum**
tercatat libur padahal seharusnya masuk, dan yang tidak masuk tidak tertangkap
sebagai alpha. Karyawan rotasi unit 24 jam selamat lewat flag pola; karyawan
umum tidak punya perlindungan setara.

Flag `is_cuti_bersama` tidak dibaca di satu tempat pun — seharusnya dialah
pembedanya.

Perbaikannya menyentuh 3 command sekaligus, jadi ditunda ke fase tersendiri
(masuk todo.md). Untuk sekarang helper text-nya diubah jadi peringatan
eksplisit bahwa penandanya belum berpengaruh dan cuti bersama sebaiknya
**jangan** didaftarkan di sini dulu. Ada test yang mendokumentasikan keadaan
salah ini supaya pembalikannya nanti disengaja, bukan kebetulan.

### Test dari nol

`HariLiburResourceTest` dibuat dari nol — Resource ini sama sekali belum
pernah punya test sampai fase 29, padahal satu barisnya berpengaruh ke 4
tempat. `HariLiburFactory` juga baru dibuat (dengan state `cutiBersama()`),
sebelumnya belum pernah ada.

### Halaman View + Infolist

`ViewHariLibur` + `HariLiburInfolist` ditambahkan, `ViewAction` masuk ke
`ActionGroup`.

Infolist tidak berhenti di menampilkan kolom: ada section **"Dampak ke Modul
Lain"** yang menghitung berapa jadwal kerja non-libur masih tersisa di tanggal
itu, dan menyebut keempat tempat yang membaca baris ini. Informasi itu selama
ini cuma ada di kepala developer.

### Perbaikan kecil (batch C)

- Ikon diganti ke `OutlinedCalendarDays`. Sebelumnya `OutlinedRectangleStack`,
  persis sama dengan `JadwalResource` — dua menu berbeda di grup Presensi
  tampil dengan ikon identik.
- `navigationSort = 3` (Absensi 1, Jadwal 2). Sebelumnya kosong.
- Label eksplisit "Hari Libur" — default Filament memluralkannya jadi
  "Hari Liburs".
- Filter instansi & rentang tanggal. Tabel ini bertambah tiap tahun dengan
  default sort tanggal menaik, jadi libur lama menumpuk di atas.

### Catatan proses — dua hal

**Test file tersimpan ke path salah, lagi.** Isi
`HariLiburResourceTest.php` sempat masuk ke `HariLiburFactory.php`, dan Pest
lagi-lagi melaporkan `TestAlreadyExist ... in the filename vendor/...` yang
menyesatkan. Kejadian kedua setelah `AbsensiResource.php` di fase 27. Yang
informatif tetap baris `at ...` di bawah pesan errornya.

**Teks peringatan masih bahasa programmer.** Ketahuan karena form-nya dibuka
di browser, bukan dari membaca kode atau menjalankan test. Peringatan "Dampak
ke jadwal yang sudah ada" menyebut `jadwal:generate-bulanan` /
`jadwal:generate-rotasi --overwrite-generate` sebagai solusi — padahal admin
RS tidak punya akses terminal sama sekali, jadi teksnya memberitahu ada
masalah lalu menyodorkan jalan keluar yang mustahil dia lakukan. Pola yang
sama juga terlanjur ditulis di `JadwalForm`, `JadwalInfolist`, dan
`HariLiburInfolist` (fase 28-29). Masuk todo.md.

Semua test batch A/B/C untuk modul ini hijau — mereka memverifikasi
peringatannya **muncul**, bukan peringatannya **bisa dipahami**. Tidak ada
assertion yang bisa menangkap "kalimat ini ditulis untuk orang yang salah".

### Status

`HariLiburResourceTest`: 0 → 12 test. Full suite: **334 test passing (1066
assertions)** — naik dari 322.

Sisa review UX Filament: 7 Resource, semuanya di grup Master Data.

## fase 28: Review UX Jadwal (JadwalResource) — selesai

Commit `ba90b26` (batch A) dan `4b4cbb6` (batch B & C). Item todo aslinya cuma
"cek kejelasan field `sumber` di UI", tapi ternyata masalahnya lebih dalam
dari soal kejelasan.

### BUG: `sumber` tidak terlihat DAN tidak pernah bernilai benar

Kolom `sumber` (generate/manual) tidak muncul di mana pun — tidak di form,
tidak di tabel, tidak di filter. Padahal dialah yang menentukan apakah sebuah
baris bertahan saat `jadwal:generate-rotasi --overwrite-generate` dijalankan.

Lebih jauh: `sumber` **tidak pernah di-set `manual` dari jalur Filament**.
`CreateJadwal` tidak punya hook, jadi baris yang diinput admin memakai default
DB yaitu `generate` — dan ikut terhapus oleh generator. Fase 15 menambahkan
kolom ini dengan alasan eksplisit "supaya generator tidak menimpa entry yang
sudah diedit manual admin"; niat itu tidak pernah terwujud untuk jalur admin
panel selama 13 fase.

Catatan cakupan: `sumber` cuma berpengaruh di satu jalur.
`jadwal:generate-bulanan` idempoten (melewati tanggal yang sudah punya baris,
apa pun sumbernya) dan `generate-rotasi` juga tidak menimpa apa pun secara
default. Kolom ini baru menentukan nasib baris ketika `--overwrite-generate`
dijalankan secara sadar — jadi ini risiko saat tindakan pemulihan, bukan
risiko harian.

**KEPUTUSAN:** `sumber` jadi field yang diatur admin secara sadar, bukan
otomatis.
- Create lewat Filament default `manual` — baris itu memang tidak pernah
  dibuat generator, jadi menandainya `generate` memang salah.
- Edit **TIDAK** mengubah `sumber` otomatis. Auto-switch ke `manual` sempat
  dipertimbangkan (sesuai tersirat fase 15) tapi ditolak: itu opt-out senyap
  dan terlalu tumpul — admin yang cuma memperbaiki typo di `keterangan` ikut
  mengeluarkan barisnya dari kendali generator selamanya, tanpa diberi tahu,
  dan tanpa jalan kembali. Lama-lama makin banyak baris jadi `manual` dan
  `--overwrite-generate` kehilangan gunanya persis saat paling dibutuhkan
  (ketika pola rotasi diperbaiki dan jadwal perlu dibangun ulang). Ada
  regression test yang mengunci keputusan ini.
- Field-nya diberi helper text yang menyebut konsekuensinya, dan kolom +
  filter `sumber` ditambahkan ke tabel.

### BUG: baris hasil sinkronisasi tidak bisa disimpan lewat Edit

`shift_id` diberi `required()` untuk semua `jenis` kecuali `libur`. Baris
hasil sinkronisasi punya `jenis='cuti'`/`'dinas'` dengan `shift_id` null —
jadi begitu admin membukanya dan menekan simpan, form menuntut shift diisi,
padahal mengisinya justru merusak data. Sekarang `required()` & `visible()`
hanya untuk jenis `reguler`/`piket`.

### BUG: guard `disabled()` memakai state hidup, bukan nilai tersimpan

`->disabled(fn (Get $get, ?string $state) => in_array($state, ['cuti','dinas']))`
mengevaluasi state form yang sedang berjalan. Akibatnya saat admin membuat
jadwal baru lalu memilih "Cuti" di dropdown, field itu **langsung mengunci
dirinya sendiri** dan admin terjebak — tidak bisa mengembalikannya ke Reguler.

Guard sekarang dinilai dari `$record` (pola yang sama dengan `AbsensiForm`
fase 27). Sekalian opsi cuti/dinas dihapus dari form create — baris jenis itu
lahir dari approval, bukan diketik admin.

### Guard diperluas

Sebelumnya hanya field `jenis` yang dikunci pada baris tersinkronisasi,
sehingga baris cuti masih bisa dipindahkan ke karyawan atau tanggal lain tanpa
peringatan apa pun. Sekarang `karyawan_id`, `tanggal`, `shift_id`, dan
`sumber` ikut dikunci, plus Placeholder peringatan yang mengarahkan perbaikan
ke modul Cuti/Dinas. `keterangan` tetap terbuka, konsisten dengan keputusan di
AbsensiForm.

### Halaman View + Infolist

`getPages()` sebelumnya cuma index/create/edit dan `recordActions` cuma
`EditAction` — sama seperti Absensi sebelum fase 27 batch B.
`ViewJadwal` + `JadwalInfolist` ditambahkan, `ViewAction` masuk ke
`ActionGroup`.

Infolist tidak cuma menampilkan nilai `sumber`, tapi menerjemahkan
konsekuensinya jadi kalimat ("Baris ini DILINDUNGI, tidak akan ditimpa" vs
"BOLEH ditimpa oleh `--overwrite-generate`"). Nilai `generate`/`manual` itu
sendiri tidak mengatakan apa-apa bagi admin yang bukan developer — dan itulah
inti dari item todo aslinya. Asal data (sinkronisasi vs manual/generator) juga
ditampilkan eksplisit.

### Perbaikan kecil

- Badge `jenis` sebelumnya membuat `reguler` dan `libur` sama-sama abu-abu —
  dua jenis yang artinya berlawanan tampil identik. Kasus yang sama dengan
  badge status TukarJadwal di fase 25. Sekarang 5 jenis punya warna & label
  sendiri.
- Filter karyawan ditambahkan (filter rentang tanggal sudah ada sejak fase 11,
  tapi tabel tidak bisa dipersempit ke satu orang).
- Filter tanggal diberi `?? null` supaya tidak memicu warning undefined array
  key saat dibuka tanpa diisi.
- Kolom `shift` diberi `placeholder('-')` karena bisa null.

### Status

`JadwalResourceTest`: 7 → 19 test. Full suite: **322 test passing (1020
assertions)** — naik dari 310.

Sisa review UX Filament: 8 Resource (Hari Libur + 7 di grup Master Data).

## fase 27 lanjutan: Review UX Data Absensi — batch B & C (selesai)

Commit `430a031` dan `7eac0c5`. Melanjutkan batch A (integritas data form)
di entri fase 27.

### Batch B — guard baris hasil sinkronisasi

Baris Absensi berstatus `cuti`/`dinas` lahir dari
`HasApprovalWorkflow::sinkronisasiJadwalDanAbsensi()`, bukan dari entri
manual maupun absen fisik. Sebelumnya admin bisa membuka baris itu dan
mengubah status atau mengisi `waktu_masuk` **tanpa peringatan apa pun** —
lalu perubahannya bertentangan dengan record Cuti/Dinas yang masih
approved, dan tertimpa diam-diam begitu sinkronisasi dijalankan ulang
(mis. lewat `absensi:backfill-cuti-dinas`). Ini gap yang sudah tercatat di
todo sejak awal review Data Absensi.

- `status`, `waktu_masuk`, `waktu_pulang` dikunci untuk baris tersebut,
  plus Placeholder peringatan yang menjelaskan alasannya dan mengarahkan
  perbaikan ke modul Cuti/Dinas. Mengikuti pola `JadwalForm` fase 21
  (field di-`disabled()` kalau nilainya hasil sync).
- **KEPUTUSAN:** guard dibatasi ke status `cuti`/`dinas` SAJA. Status lain
  yang juga ditulis sistem (`alpha` & `libur` dari `RekapHarian`) sengaja
  TIDAK ikut dikunci — tidak ada record pengajuan di baliknya, dan
  mengoreksinya manual (mis. alpha → izin) itu pekerjaan admin yang wajar.
  Ada dua test yang mengunci pembedaan ini supaya tidak digeneralisasi
  nanti.
- `keterangan` juga sengaja dibiarkan terbuka: admin sering perlu mencatat
  konfirmasi tanpa mengubah data. Ada test yang memastikan mengisi
  keterangan pada baris tersinkronisasi tidak menggeser `status` maupun
  `waktu_masuk`.
- Satu test memakai `Cuti::approve()` sungguhan (bukan factory) untuk
  memastikan status yang benar-benar ditulis `afterApprove()` memang
  memicu guard-nya.

### Batch B — halaman View + Infolist

`getPages()` sebelumnya cuma index/create/edit dan `recordActions` cuma
`EditAction`. Akibatnya untuk sekadar melihat foto masuk/pulang dan
koordinat GPS, admin harus masuk ke **mode edit**. Semua modul approval
sudah punya halaman View read-only sejak fase 25; modul yang paling sering
dibuka justru belum.

- `ViewAbsensi` + `AbsensiInfolist` ditambahkan, `ViewAction` masuk ke
  `ActionGroup` bersama `EditAction`.
- Infolist menampilkan **asal data** secara eksplisit ("hasil sinkronisasi
  otomatis dari pengajuan Cuti/Dinas yang disetujui" vs "entri manual /
  hasil absen karyawan") — informasi yang sebelumnya tidak muncul di mana
  pun.
- Penanda KPI `melebihi_toleransi_bulanan` ditampilkan dengan helper text
  yang menjelaskan artinya, bukan cuma ikon.

### Batch C — filter tabel & label

- Filter **karyawan** dan **rentang tanggal** (dari–sampai) ditambahkan.
  Sebelumnya tabel cuma bisa disaring per status & per shift, padahal ini
  tabel yang paling sering dibuka untuk operasional harian. `JadwalsTable`
  sudah punya filter rentang tanggal sejak fase 11. Filter karyawan
  ditaruh paling atas karena itu yang paling sering dipakai — admin
  biasanya mencari orang tertentu, bukan status tertentu.
- **KEPUTUSAN:** sengaja TIDAK ada filter default (mis. bulan berjalan).
  Itu terasa membantu tapi menyesatkan — admin membuka halaman, tidak
  melihat data bulan lalu, lalu menyangka datanya tidak ada. Kalau nanti
  tabelnya berat karena data menumpuk, solusinya pagination atau default
  sort, bukan menyembunyikan baris diam-diam.
- Label kolom `melebihi_toleransi_bulanan` diperbaiki dari **"Batas Min."**
  (tidak terbaca sebagai apa pun) jadi "Lewat toleransi bulanan" + tooltip.
- Kolom `shift`, `waktu_masuk`, `waktu_pulang` diberi `placeholder('-')`
  karena ketiganya sekarang bisa null (akibat required kondisional di
  batch A dan sinkronisasi yang mengosongkan waktu absen).

### Status

Review UX **Data Absensi selesai**. `AbsensiResourceTest`: 5 → 22 test.
Sisa satu item yang bukan kerja teknis: keputusan enum
`absensi.status = 'sakit'` (Known Gap sejak SCHEMA.md) — butuh masukan soal
proses di RS, lihat todo.md.

Full suite: 310 test passing (934 assertions) — naik dari 299.

## fase 27: audit jebakan cast `datetime:H:i` + integritas data Absensi

Tiga commit: `73e0c8f`, `4d75947`, `03c182d`. Berawal dari satu baris yang
mau dirapikan, melebar jadi audit satu kelas bug yang ternyata tersebar di
5 titik dan satu kolom yang tidak pernah tersimpan selama 13 fase.

### Jebakan cast `datetime:H:i` — 5 titik, semua dari akar yang sama

`Shift::$casts` men-cast `jam_masuk`/`jam_pulang` sebagai `'datetime:H:i'`,
jadi nilainya **SELALU objek Carbon** — format `H:i` cuma memengaruhi
serialisasi lewat model, bukan tipe propertinya. Dari tempat pemanggilan
tidak ada petunjuk apa pun soal ini, dan itu yang bikin polanya terus
kambuh.

Dua manifestasi berbeda:

1. **Diserahkan ke `setTimeFromTimeString()`** → Carbon
   `__toString()`-kan jadi `"Y-m-d H:i:s"` (tanggal HARI INI + jam shift),
   lalu `modify()` di dalamnya ikut **menimpa tanggalnya**, bukan cuma
   jamnya.
2. **Ditaruh langsung ke array respons JSON** → `json_encode` memanggil
   `Carbon::jsonSerialize()` dan menghasilkan ISO8601 yang dikonversi ke
   UTC.

Titik yang ditemukan & diperbaiki:
- `Shift::hitungMenitTerlambat()` — sudah diperbaiki di **fase 14**, tapi
  waktu itu pemakaian pola yang sama di tempat lain tidak ikut disisir.
- `AbsensiSimulasiSeeder` — akibat langsungnya: SEMUA row Absensi hasil
  seeder punya `DATE(waktu_masuk) != tanggal`. Sudah diperbaiki;
  `migrate:fresh --seed` sekarang menghasilkan data benar dan query
  pemeriksa mengembalikan 0.
- `PengingatBelumAbsen` & `PengingatBelumAbsenPulang` — hasilnya kebetulan
  masih benar karena anchor-nya sudah `today()`, jadi penimpaan tanggal
  menghasilkan tanggal yang sama. Pola "benar karena kebetulan", persis
  yang bikin bug fase 14 lolos 41 test. Tetap diperbaiki.
- **Teks notifikasi** di kedua command — ini terlihat pengguna:
  `"Masuk: {$shift->jam_masuk}"` berbunyi
  `Masuk: 2026-07-21 07:30:00`, bukan `07:30`.
- `AbsensiController::riwayat()` & `AuthController::me()` — mobile app
  menerima `"2026-09-15T01:00:00.000000Z"` di field bernama `jam_masuk`.
  Bukan cuma formatnya salah: tanggalnya hari ini dan jamnya bergeser ke
  UTC, jadi isinya tidak menunjukkan jam shift sama sekali. `/me` paling
  sering terkena — dipanggil tiap kali aplikasi dibuka.

`Shift::jamMasukString()` / `jamPulangString()` ditambahkan supaya pemanggil
tidak perlu ingat kenapa `->format('H:i:s')` wajib. Komentar kode usang di
`Shift.php` yang masih menyimpan pola lama dihapus — contoh buruk yang
tersimpan rapi dan siap di-copy-paste.

Dicek dan **aman**: `Izin`/`Lembur` tidak meng-cast kolom jamnya sama
sekali, jadi keluar sebagai string `"08:00:00"` apa adanya. Bukan bug, cuma
`H:i:s` sementara respons lain memakai `H:i` — ketidakseragaman minor,
masuk todo. Menambahkan `->format()` di sana justru salah karena tipenya
string, bukan Carbon.

Test baru: 4 di `ShiftTest` — dua menguji format, dua benar-benar
menjalankan `setTimeFromTimeString()` terhadap 2026-03-15 dan memastikan
tanggalnya tidak bergeser. Yang kedua itu yang mengunci jebakannya.

### BUG: `melebihi_toleransi_bulanan` tidak pernah tersimpan (sejak fase 9)

Kolom ini dihitung di `AbsensiController::masuk()` sejak fase 9 dan di hook
`CreateAbsensi`, tapi **tidak pernah ada di `$fillable` model Absensi**.
Mass assignment membuang kunci yang tidak terdaftar tanpa error apa pun,
jadi kolomnya selalu tinggal di default DB (`false`) — di SELURUH data,
lewat jalur API maupun admin panel. Semua perhitungan akumulasi bulanan
untuk KPI berjalan benar lalu hasilnya menguap.

Kenapa bertahan 13 fase:
- Tidak ada satu pun dari 15 test di `AbsensiControllerTest` yang memeriksa
  kolom itu.
- Test Filament yang seharusnya memeriksanya justru berakhir dengan
  `expect($shift->sudahMelebihiToleransiBulanan(20 + 15))->toBeTrue()` —
  memanggil model dengan angka yang dihitung sendiri di test, tidak pernah
  melihat isi database.

Pola yang sama dengan kolom `sumber` di Jadwal (fase 15).

Ditambahkan juga cast `'boolean'` supaya pembanding tidak perlu ingat
melakukan casting terhadap `0`/`1` dari MySQL (`AuditMenitTerlambat` sudah
terlihat menulis `(bool)` justru karena itu).

**Konsekuensi untuk data lama:** karena kolomnya selalu `false`, seluruh
baris Absensi existing salah di kolom ini.
`absensi:audit-menit-terlambat --fix` bisa memperbaikinya (dia me-replay
akumulasi bulanan dan menulis ulang kedua kolom). Tidak relevan sekarang
(belum ada production), tapi WAJIB dijalankan kalau nanti ada data nyata
dari sebelum fase 27.

Test baru: 3 di `AbsensiControllerTest` (2 regression guard kolom KPI, 1
format jam di riwayat), 1 assert tambahan di `AuthControllerTest` untuk
`/me`.

### Review UX Data Absensi — batch A (integritas data form)

Absensi satu-satunya modul di mana admin bisa menyunting data yang ditulis
sistem (API absen, RekapHarian, sinkronisasi Cuti/Dinas), jadi risikonya
bukan "form kurang jelas" tapi "form bisa merusak data yang bukan miliknya".

- **Unique (karyawan_id, tanggal)** sudah ada di DB sejak fase 1 tapi tidak
  divalidasi di form — submit duplikat baru gagal sebagai QueryException
  1062 mentah. Peluang kejadiannya tinggi: entri susulan untuk tanggal yang
  sudah punya baris alpha dari RekapHarian justru skenario paling wajar.
  Pola yang sama dengan bug KuotaCuti fase 25.
- **`tanggal` ↔ `waktu_masuk`** sebelumnya sama sekali tidak terikat — admin
  bisa menyimpan kombinasi yang persis sama dengan data cacat yang baru
  dibersihkan di fase 26 lanjutan. **KEPUTUSAN:** aturan kecocokan tanggal
  SENGAJA tidak diterapkan ke `waktu_pulang` — shift malam pulang di dini
  hari keesokan harinya.
- **`EditAbsensi` tidak punya hook sama sekali.** Mengubah `waktu_masuk`
  menyimpan waktu barunya tapi meninggalkan `menit_terlambat`, `status`,
  dan `melebihi_toleransi_bulanan` pada nilai lama — padahal helper text di
  form menjanjikan "otomatis dihitung ulang". Logikanya diekstrak ke trait
  `MenghitungKeterlambatan` (dipakai Create & Edit) dengan parameter
  `$kecualiAbsensiId` supaya record yang sedang diedit tidak terjumlah dua
  kali ke akumulasi bulanan.
- Query akumulasi diperbaiki: bulannya diambil dari `tanggal`, bukan dari
  `waktu_masuk`. Versi lama mencampur keduanya
  (`whereYear('tanggal', $waktuMasuk->year)`).
- **`shift_id` & `qr_instansi_id` tidak lagi required tanpa syarat** —
  keduanya nullable di DB, dan `sinkronisasiJadwalDanAbsensi()` justru
  mengosongkan `qr_instansi_id`. Memaksa admin memilih QR untuk baris
  cuti/libur cuma bikin data berbohong. `shift_id` wajib hanya kalau
  `waktu_masuk` diisi.

Test: `AbsensiResourceTest` 5 → 14 test. Dua test lama diubah (yang menguji
required shift/QR tanpa syarat sudah tidak berlaku; yang menguji akumulasi
sebelumnya tidak pernah melihat isi database).

Batch B (guard baris hasil sinkronisasi Cuti/Dinas, ViewAbsensi + Infolist)
dan batch C (filter tabel, label) belum dikerjakan.

### Catatan proses

Sempat ada kegagalan 39-dari-40 test yang bikin bingung: isi
`AbsensiResourceTest.php` tidak sengaja tersimpan ke
`AbsensiResource.php`. Filament memindai semua Resource saat boot, jadi
kode test ikut dieksekusi di setiap test dan kegagalannya menyebar ke
seluruh suite. Pesan Pest `TestAlreadyExist ... in the filename
vendor\composer\ClassLoader.php` menyesatkan — nama file yang disebut
mengacu ke autoloader, bukan ke sumber masalah. Yang informatif justru
baris `at ...` di bawahnya.

Full suite: 299 test passing (866 assertions) — naik dari 287.

## fase 26 lanjutan: fix absensi:backfill-cuti-dinas + dokumentasi command one-off

Ditemukan saat merapikan `docs/runbook.md` — bukan dari audit terjadwal.

### BUG DITEMUKAN & DIPERBAIKI: backfill menghitung ganda kuota cuti

`BackfillAbsensiDariCutiDinas` memanggil `afterApprove()` dalam loop untuk
SEMUA Cuti/Dinas approved. Waktu ditulis (fase 10) itu aman — `afterApprove()`
cuma menyinkronkan Absensi, jadi idempoten. Sejak **fase 22** tidak lagi:
method itu juga menaikkan `KuotaCuti.terpakai`.

Efeknya kalau command dijalankan sekali:
- Seluruh `KuotaCuti.terpakai` terhitung **ganda**, tanpa error apa pun.
- Kalau di tengah loop ada yang melewati kuota,
  `KuotaCutiTidakCukupException` dilempar keluar dari `each()` dan command
  mati di tengah. Record itu sendiri ter-rollback (transaksi di
  `HasApprovalWorkflow::approve()` bersifat per-record), tapi yang sudah
  terlanjur di-increment sebelumnya tetap tinggal — state separuh jalan.
- Sejak fase 21 `afterApprove()` juga menimpa Jadwal bersumber `manual`.
  Untuk backfill itu memang tujuannya, tapi sebelumnya tidak pernah
  diberitahukan ke operator.

Nama dan `$description` command-nya sendiri sudah benar ("sinkronkan ulang
Absensi"). Pemotongan kuota ikut terbawa karena `afterApprove()` menumpuk
dua tanggung jawab, bukan karena diinginkan.

**Fix:** command sekarang memanggil `resyncJadwalDanAbsensi()`, bukan
`afterApprove()`.

- `Cuti::resyncJadwalDanAbsensi()` & `Dinas::resyncJadwalDanAbsensi()` baru
  — pintu masuk publik ke `HasApprovalWorkflow::sinkronisasiJadwalDanAbsensi()`
  yang `protected`.
- **KEPUTUSAN:** sengaja method per-model, BUKAN mengubah trait-nya jadi
  `public`. Dengan begitu string `'cuti'`/`'dinas'` tetap ditentukan model
  itu sendiri, dan pemanggil tidak punya cara mengirim `'dinas'` ke record
  Cuti (yang akan merusak data tanpa error).
- Dinas sebetulnya tidak menyentuh kuota sama sekali, jadi method-nya
  identik dengan `afterApprove()`-nya sekarang. Tetap dibuat terpisah supaya
  jalur re-sync tidak ikut terbawa diam-diam kalau nanti Dinas dapat efek
  samping baru — persis yang terjadi pada Cuti di fase 22.
- Ditambah `confirm()` sebelum jalan (penimpaan Jadwal manual sekarang
  disebut di layar) + opsi `--force` untuk test/CI.

Test baru: `BackfillAbsensiDariCutiDinasTest.php` (6 test) — sync Absensi &
Jadwal untuk Cuti dan Dinas, **regression guard bahwa `KuotaCuti.terpakai`
tidak berubah sama sekali**, idempoten saat dijalankan dua kali, pending/
rejected tidak disentuh, dan Jadwal `manual` memang ditimpa (perilaku yang
disengaja, dikunci eksplisit).

Full suite: 283 test passing (818 assertions) — naik dari 277.

### runbook.md

- Blok peringatan `afterApprove()` beserta dua snippet tinker massal
  (`Cuti::where('status','approved')->each(fn ($c) => $c->afterApprove())`)
  **dihapus seluruhnya** — sudah tidak relevan setelah fix di atas, dan
  membiarkannya justru mengundang copy-paste. Jalur re-sync satu record
  sekarang `$cuti->resyncJadwalDanAbsensi()`.
- `absensi:audit-menit-terlambat` didokumentasikan — sebelumnya tidak
  disebut di dokumen manapun (CHANGELOG/QUICK_CONTEXT/runbook), padahal
  command-nya sudah ada sejak 21 Juli 2026.
- Ditambah bagian Diagnostik (`karyawan:cek-tipe-jadwal`,
  `absensi:rekap-harian`, `schedule:list`), perintah test
  (`php artisan test` / pest --filter), opsi lengkap
  `jadwal:generate-rotasi`, dan duplikat
  `make:filament-resource Instansi` di bagian arsip dihapus.

### absensi:audit-menit-terlambat — dicek, tidak diubah

Command-nya direview dan ternyata sudah benar: akumulator bulanan dibawa
lintas chunk lewat reference, dan `chunk()` biasa (bukan `chunkById`) aman
di sini karena kolom yang di-update bukan kolom pengurutan. Default
dry-run, `--fix` harus eksplisit.

Dijalankan pertama kali: **5 baris dicek, 0 salah.** Angka ini JANGAN
dibaca sebagai "Known Gap fase 14 sudah beres" — command memfilter
`waktu_masuk NOT NULL` + `shift_id NOT NULL`, jadi mayoritas data dummy
(alpha/libur/cuti/dinas) tidak ikut terhitung, dan 5 baris itu kemungkinan
besar dibuat setelah fix fase 14. Belum ada data production sama sekali.

Karena "0 salah" dari populasi yang semuanya benar tidak membedakan
"audit bersih" dari "audit tidak mendeteksi apa-apa", kemampuan deteksinya
diuji manual: satu row sengaja dirusak (`menit_terlambat = 999`), audit
benar melaporkannya (Lama 999 / Benar 2), lalu `--fix` mengoreksinya
kembali. Jalur deteksi & jalur perbaikan dua-duanya terbukti jalan.
Command ini masih belum punya test otomatis.

Temuan sampingan (ditindaklanjuti di entri berikutnya): kelima row Absensi
yang punya waktu_masuk ternyata SEMUANYA punya DATE(waktu_masuk) != tanggal
— tanggal 16–20 Juli, tapi waktu_masuk semuanya 21 Juli (tanggal seeder
dijalankan) dengan cuma jamnya yang berbeda. Penyebabnya bukan anchor yang
salah di seeder (anchor-nya sudah benar ke $tanggal), melainkan jebakan cast
datetime:H:i yang sama dengan bug fase 14 — lihat entri di atas. Data ini
artefak dari kode lama; migrate:fresh --seed menghasilkan data yang benar.
Artinya "0 salah" dari audit di atas berasal dari populasi yang seluruhnya
cacat, jadi praktis tidak membuktikan kesehatan data.

## fase 26: sentralisasi query kuota cuti, sinyal approve 4 keadaan, audit ViewLembur

Trigger: menutup sisa item fase 25 (extract `KuotaCuti::sisaUntuk()` dan
audit `ViewLembur.php`) sebelum membuka review 10 Resource berikutnya.

### Sentralisasi query kuota (KuotaCuti + Cuti)

Query sisa kuota ternyata diduplikasi di **5** tempat (todo lama mencatat
4), dengan **3 penafsiran berbeda** untuk kasus row KuotaCuti yang belum
ada sama sekali:
- `CutiForm` rule tanggal_selesai & `Cuti::afterApprove()` → skip (benar,
  kebijakan fase 22)
- `CutiController::ajukan()` → fallback `default_kuota`
- `CutisTable` approve `color()` DAN `tooltip()` (query identik, ditulis
  2x) → di-treat sebagai **sisa 0**

- **BUG DITEMUKAN & DIPERBAIKI:** yang terakhir itu bug yang sama persis
  dengan yang sudah diperbaiki di CutiForm pada fase 25 — waktu itu cuma
  ditambal di satu tempat. Efeknya: karyawan yang belum punya row
  KuotaCuti bikin tombol "Setujui" berwarna **danger** dengan tooltip
  `⚠ Sisa kuota (0) kurang dari jumlah hari yang diajukan (3)`, padahal
  kebijakan fase 22 justru membolehkan approve itu dan operasinya akan
  SUKSES. Admin diberi peringatan bahaya untuk tindakan yang benar.

- `KuotaCuti::untuk(karyawanId, jenisCutiId, tahun): ?self` dan
  `KuotaCuti::sisaUntuk(...): ?int` jadi finder terpusat. `untuk()`
  dibutuhkan terpisah karena placeholder form & infolist perlu `kuota` +
  `terpakai` juga, bukan cuma sisa.
- **KEPUTUSAN:** `sisaUntuk()` sengaja return `?int`, BUKAN `int`.
  `null` = belum ada row = tidak ada dasar untuk menolak; `0` = row ada,
  kuota memang habis. Menyamakan keduanya sudah jadi bug dua kali, dan
  signature nullable itu yang memaksa tiap pemanggil memilih eksplisit —
  kebijakan fase 22 jadi terlihat di tipe, bukan cuma di komentar.
- **SENGAJA TIDAK dimigrasikan:** `Cuti::afterApprove()` tetap query
  sendiri dengan `lockForUpdate()` di dalam transaksi. Memanggil finder
  di sana justru menghapus proteksi race condition fase 22.
- `Cuti::hariPendingUntuk(karyawanId, jenisCutiId, tahun, ?kecualiCutiId)`
  baru — sebelumnya logika ini cuma hidup inline di CutiController.
  Parameter `$kecualiCutiId` wajib diisi kalau pemanggilnya sedang menilai
  satu record pending tertentu (tooltip approve, form edit), kalau tidak
  `jumlah_hari` record itu terhitung dua kali.

### Info kuota: breakdown, bukan satu angka

Sebelumnya ada 2 definisi "sisa" yang beredar: API memperhitungkan
pengajuan pending lain (fase 22), tampilan admin tidak. Risikonya
karyawan komplain "app saya bilang sisa 2, admin bilang 5" dan keduanya
tidak salah.
- Placeholder CutiForm, CutiInfolist, dan tooltip approve CutisTable
  sekarang menampilkan `Sisa · Pending lain · Efektif` (bagian pending
  cuma muncul kalau > 0, biar tidak berisik untuk kasus normal).
- Validasi form **tidak** diubah — pending lain cuma ditampilkan, tidak
  dipakai menolak submit. Yang menolak tetap sisa mentah.
- Label CutiInfolist diganti dari "Info kuota saat pengajuan" jadi "Info
  kuota saat ini" — angkanya di-query realtime, bukan snapshot; label
  lama menyesatkan.

### Warna tombol approve: 2 keadaan → 4

`CutisTable::infoKuota()` (helper baru, dipakai bersama `color()` &
`tooltip()`):
- `tidak_potong` / `aman` → success
- `belum_ada_row` → **warning** + tooltip "approve tetap bisa, tapi tidak
  akan memotong kuota" (sebelumnya danger — ini bug di atas)
- `kurang` → danger

**KEPUTUSAN:** `kurang` dinilai dari sisa MENTAH (kuota − terpakai),
bukan sisa efektif setelah dikurangi pending. Alasannya itulah yang
benar-benar dicek `afterApprove()`; pengajuan pending lain tidak membuat
approve ini gagal, jadi cuma diinformasikan lewat tooltip. Ada test yang
mengunci keputusan ini secara eksplisit.

Catatan: `color()` dan `tooltip()` masih memanggil `infoKuota()`
masing-masing (2 query per baris). Memoization sengaja tidak dipakai —
cache statis per-record bisa basi dalam request yang sama setelah approve,
menampilkan angka sebelum pemotongan. Duplikasi query lebih murah daripada
angka yang salah.

### Fallback default_kuota di API — dipertahankan, keterbatasan dicatat

**KEPUTUSAN:** asimetri API-ketat / Filament-longgar dipertahankan
(karyawan self-service butuh batas waras; admin dianggap sengaja kalau
approve tanpa kuota) — pola yang sama seperti kebijakan tanggal lewat di
fase 23.

**GAP DITEMUKAN (masuk todo, bukan diperbaiki sekarang):** fallback itu
sebenarnya tidak membatasi apa pun secara akumulatif. Selama row KuotaCuti
belum ada, `afterApprove()` tidak pernah menaikkan `terpakai`, jadi cuti
yang sudah approved tidak pernah masuk hitungan — yang dikurangi cuma
pengajuan yang masih pending. Karyawan bisa ajukan 12 hari → approve →
ajukan 12 hari lagi → approve, tanpa batas sepanjang tahun.
Solusi akarnya (`firstOrCreate` row dari default_kuota) SENGAJA ditunda
dan digabung ke item KuotaCuti semesteran di todo.md, karena item itu akan
mengubah kunci row-nya (`tahun` → `tahun`+`semester`) dan data yang
dibuat sekarang harus dimigrasikan lagi.

### ViewLembur — sudah benar, tapi tidak terjaga

`ViewLembur.php` ternyata SUDAH punya tombol kembali dan `EditAction`
dengan guard `isPending()`, konsisten dengan ViewCuti/ViewDinas/
ViewTukarJadwal. Tidak ada perubahan logika.
- Yang kurang: **tidak ada test yang menjaganya**. Dua test EditAction
  yang ada menguji `ListLemburs`, bukan halaman View — persis celah yang
  bikin bug ViewCuti/ViewDinas/ViewTukarJadwal lolos di fase 25. Ditambah
  2 test halaman View.
- **Test menyesatkan diperbaiki:** test berjudul "approve ... dan kirim
  notifikasi" ternyata memanggil `$lembur->approve()` langsung di model,
  tidak pernah menyentuh `Notification::make()` di LembursTable.
  Notifikasi approve/reject yang baru ditambahkan di fase 25 punya nol
  coverage. Diganti lewat `callTableAction` + `assertNotified`, plus test
  reject yang sebelumnya tidak ada sama sekali.

### Test: bom waktu tanggal hardcode & 422 yang menyamarkan

Saat menjalankan suite, 3 test merah — semuanya di
`tanggal_mulai: validation.after_or_equal`, bukan karena perubahan sesi
ini. Tanggal hardcode `2026-08-01` di CutiControllerTest &
DinasControllerTest sudah lewat, jadi aturan `after_or_equal:today` dari
fase 23 mulai menolaknya. Pola yang sama pernah menyamarkan bug
`hitungMenitTerlambat()` di fase 14 (test lolos karena tanggal hardcode
kebetulan = tanggal server saat test ditulis).
- Fix: `$this->travelTo(Carbon::parse('2026-08-01 08:00:00'))` di
  `beforeEach` kedua file. Dipilih ketimbang mengganti tiap tanggal jadi
  relatif (`today()->addDays(n)`) karena semua tanggal hardcode yang ada
  langsung bermakna lagi tanpa disentuh, tahun 2026 di fixture KuotaCuti
  tetap cocok, dan hasilnya deterministik selamanya.

- **LEBIH PENTING — celah yang ditemukan gara-gara ini:** beberapa test di
  kedua file itu HIJAU karena alasan yang salah. Assert-nya cuma
  `assertStatus(422)`, jadi penolakan dari validasi tanggal tidak bisa
  dibedakan dari penolakan karena kuota/lampiran/jenis nonaktif/bentrok —
  pengecekan yang seharusnya diuji bisa saja sudah mati tanpa ketahuan.
  Assert dipertajam ke `data.sisa_kuota`, pesan spesifik, atau
  `assertJsonValidationErrors`. Untuk DinasController (source-nya belum
  dibaca sesi ini) dipakai `expect($response->json('errors'))->toBeNull()`
  — cukup untuk memastikan 422-nya datang dari cek bentrok, bukan
  validasi.
- Dua test pending yang isinya identik (`sisa kuota memperhitungkan
  pengajuan pending lain` & `menolak pengajuan kalau total dengan pending
  lain melebihi sisa kuota` — setup, request, dan assert sama persis)
  diubah jadi sepasang boundary: yang pertama sekarang menguji pengajuan
  yang PAS dengan sisa efektif harus diterima (201).

Test baru: 5 warna approve (CutiResourceTest), 4 `hariPendingUntuk()`
(CutiTest unit), 3 Lembur (2 ViewLembur + reject lewat action).

Full suite: 277 test passing (789 assertions) — naik dari 265 (742) di
akhir fase 25.

Commit: `f4b6b2c`.

Item baru masuk todo dari sesi ini:
- Celah akumulatif KuotaCuti saat row belum ada (digabung ke item
  semesteran — lihat alasan di atas).
- Simulasi karyawan rotasi dengan langkah LIBUR di polanya (5 hari
  kerja/minggu). Muncul dari obrolan, bukan audit. Seeder rotasi sekarang
  nonstop tanpa libur, jadi seluruh jalur "rotasi sedang libur" tidak
  pernah dilewati. Yang paling dicurigai: `PengingatBelumAbsen` &
  `PengingatBelumAbsenPulang` tidak punya test sama sekali dan kemungkinan
  mengirim notifikasi ke karyawan rotasi yang sedang libur.

Item yang DIHAPUS dari todo (bukan dikerjakan): "Cek ulang error handling
— apakah sudah terpusat". Sudah kejawab di fase 24 (`bootstrap/app.php`
`withExceptions()` untuk route `/api/*`); item ini stale, tertinggal dari
daftar prioritas lama.

## Tambahan fase 25: Jenis Cuti & Kuota Cuti (pendukung modul Cuti)

Setelah 5 modul approval selesai, direview juga 2 Resource pendukung
yang field-nya krusial dipakai di validasi CutiForm (fase 25 bagian Cuti):

JenisCutiForm/JenisCutisTable — sudah rapi dari awal (helper text jelas
di semua toggle), 1 gap ditemukan:
- **BUG DITEMUKAN & DIPERBAIKI:** field `nama` tidak ada validasi unique.
  Kalau admin bikin 2 "Cuti Tahunan" secara tidak sengaja, keduanya
  tampil identik di dropdown jenis_cuti_id (CutiForm/KuotaCutiForm) —
  tidak ada cara bedain mana yang benar dipakai. Fix: `->unique(ignoreRecord: true)`
  ditambahkan ke TextInput nama.

KuotaCutiForm/KuotaCutisTable — 1 gap struktural ditemukan:
- **BUG DITEMUKAN & DIPERBAIKI:** tidak ada validasi form untuk unique
  constraint (karyawan_id, jenis_cuti_id, tahun) yang sudah ada di level
  DB (SCHEMA.md). Sebelumnya, submit duplikat lolos validasi Filament
  dan baru gagal di titik INSERT sebagai QueryException mentah (1062
  duplicate entry) — bukan pesan error ramah di form. Efek sampingnya
  lebih penting: kalau 2 row somehow ada untuk kombinasi yang sama,
  Cuti::afterApprove() (fase 22) cuma ambil row PERTAMA yang match
  lewat first() — row kedua jadi data mati yang membingungkan tapi
  tidak pernah kepakai. Fix: rule custom di field `tahun` (live ke
  karyawan_id + jenis_cuti_id) cek duplikat sebelum submit, exclude
  record sendiri saat edit (ignoreRecord pattern).
- Field `terpakai` ditambah helper text dinamis: warning kalau nilai
  yang diinput melebihi `kuota` (preventif visual, bukan validasi keras
  yang menolak submit — dianggap cukup karena badge danger di tabel
  sudah ada sebagai sinyal reaktif setelahnya).

Test baru: JenisCutiResourceTest (4 test — create, unique nama ditolak,
edit tidak kena unique diri sendiri), KuotaCutiResourceTest (5 test —
create, duplikat kombinasi ditolak, kombinasi beda tahun diizinkan,
edit tidak kena unique diri sendiri).

Full suite: 265 test passing (742 assertions) — naik dari 256 sebelum
tambahan ini (+9 test).

Item baru masuk todo (dampak luas, didiskusikan dulu sebelum dikerjakan):
KuotaCuti perlu dukung periode 6 bulanan (semester), bukan cuma tahunan
— pengaruh ke migration, unique constraint, dan 4 tempat query kuota
yang sudah ada (CutiForm placeholder, CutiInfolist, CutisTable approve
action, Cuti::afterApprove()).

## fase 25: Review UX Filament panel admin — 5 modul approval (Cuti, Izin, Lembur, Dinas, TukarJadwal)

Trigger: development kerasa lancar tanpa hambatan — sinyal curiga test
cuma cover happy path. Direview satu-satu (bukan disamakan buta) karena
tiap modul punya karakteristik beda: Cuti (kuota), Izin (jam_kembali
nullable, insidental), Lembur (durasi bisa lintas tengah malam), Dinas
(sync Absensi+Jadwal tanpa kuota), TukarJadwal (2 mode + snapshot).

### Cuti (CutiForm/CutisTable/CutiInfolist/ViewCuti)
- Info kuota sebelumnya SAMA SEKALI tidak ada di form maupun infolist.
  Ditambahkan Placeholder reaktif di form (live ke karyawan_id/
  jenis_cuti_id/tanggal_mulai) dan TextEntry computed di infolist.
- Tabel: kolom `alasan` ditambahkan (sebelumnya tidak tampil), actions
  dikelompokkan ke ActionGroup (sebelumnya 4 tombol bikin overflow
  horizontal).
- Approve action: warna dinamis (danger kalau sisa kuota < jumlah_hari)
  + tooltip peringatan SEBELUM diklik — preventif, bukan cuma reaktif.
- Lampiran: `required()` kondisional berdasar JenisCuti.perlu_lampiran
  (sebelumnya helper text bilang wajib tapi field tidak required sama
  sekali).
- Layout Section+Grid, DatePicker `native(false)`. Tombol "Kembali ke
  daftar" ditambah di ViewCuti (sebelumnya cuma breadcrumb).
- `jumlah_hari` jadi `disabled()->dehydrated()` (read-only computed).
- **BUG DITEMUKAN & DIPERBAIKI:** rule validasi tanggal_selesai salah
  treat `KuotaCuti` yang belum punya row sama sekali sebagai sisa=0,
  otomatis nolak berapa pun jumlah_hari-nya — kontradiksi langsung
  dengan kebijakan fase 22 ("kuota belum ada row = tidak ada dasar
  buat menolak"). Ketemu lewat CutiResourceTest yang sudah ada duluan
  (test "admin boleh membuat cuti untuk tanggal yang sudah lewat" jadi
  merah). Fix: validasi kuota di-skip total kalau `$kuota` null, bukan
  di-treat sebagai 0.
- **BUG DITEMUKAN & DIPERBAIKI:** `ViewCuti::getHeaderActions()` punya
  `EditAction::make()` tanpa `visible(isPending())`, padahal CutisTable
  sudah benar membatasinya — celah ini bikin Cuti yang sudah approved
  (yang sudah mentrigger potong kuota + sync Absensi/Jadwal) bisa diedit
  ulang lewat halaman View tanpa re-trigger sinkronisasi, berpotensi bikin
  3 tempat data stale. Fix: guard yang sama ditambahkan ke ViewCuti.
- Test baru: CutiResourceTest — 13 test (create, tanggal edge case,
  approve/reject, boundary kuota pas-pasan, regression guard kebijakan
  fase 22, EditAction visibility, lampiran conditional).

### Izin (IzinForm/IzinsTable/IzinInfolist/ViewIzin)
- Pola serupa Cuti: kolom `keperluan` ditambah ke tabel, actions masuk
  ActionGroup, layout Section+Grid, tombol kembali.
- Validasi jam_kembali harus setelah jam_keluar ditambahkan (sebelumnya
  tidak ada sama sekali).
- **BUG DITEMUKAN & DIPERBAIKI (regresi sesi ini):** TimePicker dengan
  `native(false)` menyimpan `00:00` sebagai default value begitu field
  disentuh sedikit, dianggap value valid oleh `required()` — beda dari
  native browser time input yang benar-benar kosong sampai dipilih.
  Fix: TimePicker jam_keluar & jam_kembali dibalikin ke native browser
  input (DatePicker tetap native(false), masalahnya khusus TimePicker).
  **Catatan ke depan:** hindari `TimePicker::native(false)` di project
  ini kecuali ada penanganan tambahan.
- **KEPUTUSAN STRUKTURAL:** awalnya `EditAction` IzinsTable/ViewIzin
  dipasang guard `visible(isPending())` sama seperti Cuti, tapi ini
  ditemukan salah konteks — `jam_kembali` bersifat nullable by design
  (baru diisi setelah karyawan balik dari izin, bisa terjadi SETELAH
  status sudah approved). Kalau dikunci ke isPending(), begitu admin
  approve (yang bisa terjadi sebelum karyawan balik), jam_kembali jadi
  tidak bisa pernah diisi lewat jalur normal. Guard DIREVERT — Izin
  sengaja dibiarkan tetap editable walau approved, sampai solusi
  struktural (auto-approve + endpoint jam_kembali terpisah, lihat
  todo.md "Nanti") benar-benar dikerjakan.
- Approve action: try-catch `\Throwable` generik yang sempat dipasang
  di awal DIHAPUS — Izin::approve() tidak punya afterApprove() custom
  atau exception domain apa pun (beda dari Cuti), jadi catch generik
  cuma berisiko menyamarkan bug non-bisnis sebagai notifikasi normal.
- Test baru: IzinResourceTest — 6 test (create dengan jam_kembali
  kosong, validasi jam, EditAction TETAP visible saat approved sebagai
  regression guard buat keputusan revert di atas, approve tanpa
  exception).

### Lembur (LemburForm/LembursTable/LemburInfolist)
- Kolom `alasan` diubah NULLABLE (migration baru) — sebelumnya wajib,
  padahal tidak semua lembur perlu alasan tertulis. `$fillable` model
  dirapikan (sempat ada duplikat entry 'alasan').
- **KEPUTUSAN BISNIS:** lembur boleh lintas tengah malam (jam_selesai
  < jam_mulai direpresentasikan sebagai 1 baris, BUKAN 2 pengajuan
  terpisah seperti kebijakan cuti lintas tahun di fase 23) — karena
  sifat lembur per-shift, beda dari cuti yang per-hari-kalender. Yang
  tetap ditolak: durasi nol (jam_selesai == jam_mulai).
- Layout Section+Grid, ActionGroup, Notification sukses/gagal
  ditambahkan (sebelumnya tidak ada feedback visual approve/reject
  sama sekali).
- Approve/reject tanpa try-catch (dikonfirmasi: Lembur::approve() tidak
  punya afterApprove() custom, tidak sync ke Absensi — konsisten dengan
  keputusan yang sama untuk Izin).
- Test baru: LemburResourceTest — 7 test (alasan nullable, durasi nol
  ditolak, lintas tengah malam diterima, EditAction visibility pending/
  approved, approve tanpa exception, alasan nullable di level DB).
- Item baru masuk todo: accessor `getDurasiMenitAttribute()` (computed,
  bukan kolom fisik) untuk kebutuhan laporan/payroll nanti — sengaja
  TIDAK disimpan sebagai kolom DB sekarang (belum ada consumer nyata,
  computed value gampang stale kalau disimpan fisik dan datanya diedit
  manual — pola yang sudah pernah jadi bug di Shift::hitungMenitTerlambat
  fase 14).

### Dinas (DinasForm/DinasTable/DinasInfolist/ViewDinas)
- Validasi tanggal (rentang terbalik, lintas tahun, bentrok cuti approved)
  ternyata SUDAH ada sebelum sesi ini — cuma perlu dirapikan Section+Grid,
  ActionGroup, Notification, kolom `keperluan` ditambah ke tabel.
- **BUG DITEMUKAN & DIPERBAIKI:** ViewDinas tidak punya tombol kembali
  DAN EditAction tidak ada guard `visible()` sama sekali (beda dari
  Izin — Dinas approved sync ke Absensi+Jadwal, tidak ada field yang
  "hidup" pasca-approval, jadi lock-on-approve itu benar untuk Dinas).
  Fix: tombol kembali + guard isPending() ditambahkan, konsisten
  dengan Cuti.
- Test baru: DinasResourceTest — 11 test (create, tanggal edge case,
  approve tanpa exception, approve men-sync Absensi untuk SETIAP
  tanggal dalam rentang, sync Jadwal, reject tidak menyentuh Absensi/
  Jadwal, EditAction visibility).

### TukarJadwal (TukarJadwalForm/TukarJadwalsTable/TukarJadwalInfolist/ViewTukarJadwal)
- TukarJadwalForm dikonfirmasi SUDAH matang (3 lapis validasi: rebutan
  jadwal pending, konflik jadwal existing, cuti/dinas approved 4
  kombinasi arah) — tidak ada perubahan di form.
- **BUG DITEMUKAN & DIPERBAIKI (paling signifikan sesi ini):**
  TukarJadwalInfolist mengambil data lewat relasi LIVE (`jadwal`/
  `jadwalTujuan`) alih-alih kolom snapshot (`tanggal_asal`/
  `tanggal_tujuan`/`shift_asal_id`/`shift_tujuan_id`) — kontradiksi
  langsung dengan alasan desain yang sudah ditegakkan di fase 11 & 24
  ("snapshot tetap mencatat kondisi saat pengajuan dibuat walau jadwal
  aslinya sudah berubah kepemilikan lewat pengajuan lain"). Efeknya:
  admin buka detail pengajuan LAMA yang sudah approved, infolist bisa
  nampilin data jadwal yang SUDAH BERUBAH karena pengajuan lain — beda
  dari TukarJadwalsTable yang sudah benar pakai kolom snapshot dari
  awal. Fix: Infolist disamakan pakai kolom snapshot yang sama seperti
  Table.
- Badge status TukarJadwalsTable & Infolist sebelumnya cuma cover 3
  dari 5 enum status yang sebenarnya ada sejak fase 20
  (menunggu_rekan/menunggu_admin/ditolak_rekan/approved/rejected) —
  3 status pertama fallback ke abu-abu generik. Fix: badge + filter
  status dilengkapi semua 5 opsi dengan warna & label yang jelas.
- ViewTukarJadwal: tombol kembali ditambah, EditAction dikasih guard
  isPending() (sebelumnya tidak ada sama sekali, sama seperti Dinas).
- **GAP DITEMUKAN (masuk todo, bukan bug):** selama status masih
  `menunggu_rekan`, SEMUA action (Edit/Setujui/Tolak) tersembunyi di
  tabel admin — cuma ViewAction yang muncul. Kalau rekan tidak kunjung
  merespons, admin tidak punya cara membatalkan/memaksa lanjut
  pengajuan itu lewat Filament. Belum ada keputusan solusi (cancel
  manual vs auto-expiry via scheduler) — dicatat di todo.md "Nanti".
- Test baru: TukarJadwalResourceTest — 11 test (mode tukar & pindah,
  snapshot otomatis dari creating(), rebutan jadwal, konflik tanggal,
  bentrok cuti/dinas 2 arah, alasan kosong ditolak).

### Ringkasan
Full suite: 256 test passing (703 assertions) — naik dari 229 di fase 24
(+27 test baru dari 5 ResourceTest modul approval).

Item baru masuk todo dari sesi ini:
- Extract `KuotaCuti::sisaUntuk()` — masih belum dikerjakan, query sisa
  kuota masih duplikat di beberapa tempat pada modul Cuti.
- Cek `ViewLembur.php` — belum sempat diaudit konsisten dengan 4 modul
  lain (kemungkinan besar kasus sama, EditAction butuh guard, tapi
  belum dikonfirmasi filenya).
- Accessor `Lembur::getDurasiMenitAttribute()` (lihat detail di atas).
- TukarJadwal: kebutuhan override admin saat status menunggu_rekan macet
  (lihat detail di atas).

## fase 24: audit konsistensi format respons API (error handling terpusat + transform data)

Sebelumnya tidak ada `Handler.php`/custom exception rendering sama
sekali (`bootstrap/app.php` cuma punya `shouldRenderJsonWhen`) —
`ValidationException` dan error bawaan Laravel lain (401/404) balik
dalam format `{message, errors}` tanpa `success`, berbeda total dari
semua response manual `{success, message, data}` yang ditulis di
controller. Client (mobile app) harus handle 2 bentuk error berbeda
tergantung jenis errornya.

`bootstrap/app.php` — `withExceptions()` sekarang render eksplisit
untuk route `/api/*`:
- `ValidationException` → `{success: false, message: "Data yang
  dikirim tidak valid.", errors: {...}}` (errors tetap dipertahankan
  buat highlight field di form client).
- `AuthenticationException` → 401 seragam.
- `ModelNotFoundException` & `NotFoundHttpException` → 404 seragam.
- `HttpExceptionInterface` generik → fallback pakai status code &
  message aslinya.
- Error 500 murni (`\Throwable` generik) sengaja TIDAK disentuh —
  tetap default Laravel (log + response generic), supaya tidak
  menyembunyikan stack trace penting saat `APP_DEBUG=true`.

`TukarJadwalController::riwayat()` & `menungguResponSaya()` — celah
konsistensi ditemukan: `data` sebelumnya berisi Eloquent collection
mentah dari `with()` (bukan hasil `map()` terkurasi seperti semua
controller lain), akibatnya:
- Struktur beda dari endpoint lain (semua endpoint lain punya
  `data.total` + `data.records`, ini cuma `data` array langsung).
- Kolom internal bocor ke response (FK mentah, timestamp mentah
  format `Y-m-d H:i:s` alih-alih `d M Y` yang konsisten dipakai di
  tempat lain).
- Fix: ditambah `formatTukarJadwal()` (private static helper) yang
  transform ke field terkurasi — pakai kolom snapshot
  (`tanggal_asal`/`tanggal_tujuan`/`shift_asal_id`/`shift_tujuan_id`)
  alih-alih relasi `jadwal`/`jadwalTujuan` langsung, karena snapshot
  tetap mencatat kondisi saat pengajuan dibuat walau jadwal aslinya
  sudah berubah kepemilikan lewat pengajuan lain. Dibungkus
  `{total, records}` konsisten dengan Cuti/Dinas/Izin/Lembur.

Temuan yang didokumentasikan tapi SENGAJA belum diubah (bukan bug,
keputusan menyusul):
- `AuthController::login()` — pesan root `message` generik ("Data
  yang dikirim tidak valid.") untuk kasus salah password, walau
  `errors.email` sudah berisi pesan spesifik yang benar. UX minor,
  bukan bug struktural.
- Response sukses Izin/Lembur cuma balikin `id`/`tanggal`/`status`,
  lebih sedikit field dibanding Cuti/Dinas. Belum diseragamkan,
  belum ada keputusan apakah perlu.

Test baru: `ErrorResponseFormatTest.php` (file baru) — validation
error, unauthenticated, route not found, semua assert struktur
`{success, message, errors?}`.

`NotifikasiController::index()` — dua celah ditemukan:
- Key response tidak konsisten: `data.notifikasi` (khusus endpoint ini)
  vs `data.records` (semua endpoint list lain).
- `paginate()->through()` menghasilkan objek LengthAwarePaginator
  bersarang di dalam `data.notifikasi` (`{current_page, data, ...}`),
  beda struktur nesting dari endpoint lain yang `data.records` selalu
  array langsung.
- Fix: disederhanakan jadi `limit($perPage)->get()` tanpa pagination
  (keputusan: app tidak butuh info halaman untuk notifikasi), key
  diseragamkan ke `data.records` + `data.total`. Test lama yang masih
  assert `data.notifikasi.data` disesuaikan ke `data.records`.

Audit ini sekarang mencakup seluruh 8 controller API (Absensi, Auth,
Cuti, Dinas, Izin, Lembur, TukarJadwal, Notifikasi) — tidak ada lagi
yang tersisa dari daftar controller di app/Http/Controllers/Api/.

Full suite: 229 test passing (621 assertions).

## fase 23: audit validasi tanggal edge case — Cuti & Dinas (rentang, lewat, lintas tahun, bentrok)

Kebijakan tanggal lewat (keputusan bisnis): karyawan lewat API TIDAK
boleh mengajukan cuti/dinas untuk tanggal_mulai yang sudah lewat;
admin lewat Filament BOLEH (buat entri susulan/backdate).

CutiController::ajukan() & DinasController::ajukan():
- tanggal_mulai sekarang wajib after_or_equal:today (khusus API,
  tidak berlaku di Filament).
- Rentang yang melintasi pergantian tahun (mis. 30 Des - 2 Jan)
  ditolak eksplisit — bukan di-split otomatis. Kebijakan: kuota sisa
  di akhir tahun yang tidak terpakai memang hangus, kuota tahun baru
  baru muncul di Januari; admin yang handle kasus mepet akhir tahun.
  Karyawan yang perlu cuti lintas tahun harus ajukan 2x terpisah.
- Ditambahkan cek bentrok terhadap Cuti/Dinas approved lain milik
  karyawan yang sama (sebelumnya tidak ada sama sekali di kedua
  controller — beda dengan TukarJadwalForm yang sudah punya guard
  serupa sejak fase 20).

CutiForm.php (Filament) — bug nyata ditemukan & diperbaiki:
- Sebelumnya SAMA SEKALI tidak ada validasi tanggal_selesai >=
  tanggal_mulai. Rentang terbalik (mis. mulai 10 Agu, selesai 5 Agu)
  lolos tersimpan karena hitungJumlahHari() pakai Carbon::diffInDays()
  yang mengembalikan nilai absolut (tidak peduli arah) - jumlah_hari
  tetap ke-generate angka valid walau rentangnya kebalik. Efek
  lanjutannya: CarbonPeriod::create() di sinkronisasiJadwalDanAbsensi()
  kemungkinan menghasilkan 0 iterasi kalau start > end, sehingga
  Jadwal/Absensi tidak ter-sync sama sekali walau Cuti sudah approved
  - silent failure yang sulit terdeteksi.
- Fix: DatePicker tanggal_selesai pakai ->afterOrEqual('tanggal_mulai').
  Ditambahkan juga guard cek lintas tahun dan bentrok Dinas approved.
  Sengaja TIDAK ada batas tanggal lewat (admin boleh backdate).

DinasForm.php (Filament):
- Sebelumnya tidak ada validasi tanggal apa pun. Diberi pola sama
  persis dengan CutiForm.php: afterOrEqual, cek lintas tahun, cek
  bentrok Cuti approved. Tidak ada batas tanggal lewat.

Test baru: 11 di CutiControllerTest/DinasControllerTest (tanggal
lewat, lintas tahun, bentrok cuti, bentrok dinas), 10 di
CutiResourceTest/DinasResourceTest (file baru — admin boleh tanggal
lewat, tolak rentang terbalik, tolak lintas tahun, tolak bentrok).

Full suite: 226 test passing (607 assertions).

## fase 22: audit race condition — kuota Cuti (approve) & unique constraint Jadwal/Absensi

Kuota Cuti — approve gagal eksplisit kalau kuota tidak cukup (bukan minus diam-diam):
- HasApprovalWorkflow::approve() dibungkus DB::transaction() — kalau
  afterApprove() throw, seluruh update status ikut rollback (Cuti
  balik ke pending, tidak ada Jadwal/Absensi/kuota yang ikut berubah).
- Cuti::afterApprove() sekarang lockForUpdate() row KuotaCuti lalu
  re-validasi final (terpakai + jumlah_hari > kuota) sebelum
  increment — bukan cuma percaya cek yang dilakukan saat pengajuan.
  Kalau tidak cukup, throw KuotaCutiTidakCukupException baru
  (app/Exceptions/KuotaCutiTidakCukupException.php). Kuota yang belum
  punya row KuotaCuti sama sekali tetap dibiarkan lolos seperti
  behavior lama (tidak ada dasar buat menolak).
- CutiController::ajukan() — sisa kuota saat pengajuan sekarang
  memperhitungkan jumlah_hari dari pengajuan lain yang masih pending
  (jenis+tahun sama), bukan cuma KuotaCuti.terpakai. Ini menutup
  celah sekuensial: dua pengajuan berurutan yang masing-masing lolos
  cek karena yang pertama belum di-approve, sehingga total pending
  bisa melebihi kuota tanpa ketahuan sampai admin approve keduanya.
- Filament CutisTable: action approve menangkap
  KuotaCutiTidakCukupException, tampilkan Notification danger dengan
  pesan sisa/diajukan — bukan crash / error page generik.

Unique constraint Jadwal/Absensi — race condition di titik insert:
- AbsensiController::masuk() — Absensi::create() dibungkus try-catch
  QueryException. Kalau kena unique violation (errorInfo[1] === 1062,
  MySQL duplicate entry) — skenario dua request nyaris bersamaan yang
  keduanya lolos pengecekan awal "belum ada absensi hari ini" — foto
  yang sudah terlanjur ke-upload dihapus dari storage, response 422
  dengan pesan yang sama seperti pengecekan aplikasi biasa. Error lain
  (bukan 1062) tetap dilempar ulang, tidak ditelan diam-diam.
- Constraint DB (unique karyawan_id+tanggal) di tabel absensi & jadwals
  dikonfirmasi sudah ada dari awal — pekerjaan fase ini murni menangani
  exception-nya di level aplikasi, bukan menambah constraint baru.

Test baru: KuotaCutiTidakCukupException saat approve + edge case pas
kuota pas-pasan (CutiTest), pending lain menghalangi pengajuan baru
(CutiControllerTest), race condition absen ganda tersimulasi lewat
model event Absensi::creating() yang menyisipkan row "dari request
lain" tepat sebelum insert asli (AbsensiControllerTest).

Full suite: 208 test passing (539 assertions).

## fase 21: audit interaksi lintas modul — Cuti/Dinas approved x Jadwal rotasi (item terakhir)

Cuti/Dinas x Jadwal:
- Ditemukan celah: sync sebelumnya (fase 20) cuma menyentuh Absensi,
  tidak pernah menyentuh Jadwal. Akibatnya karyawan rotasi yang lagi
  Cuti/Dinas approved tetap tampil dijadwalkan piket/reguler di
  Filament, walau Absensi sudah benar berstatus cuti/dinas. Root
  cause: GenerateJadwalRotasi generate Jadwal murni dari pola rotasi +
  HariLibur nasional, tanpa cek Cuti/Dinas approved sama sekali.
- Fix: Cuti::afterApprove() dan Dinas::afterApprove() sekarang juga
  updateOrCreate Jadwal (shift_id null, jenis cuti/dinas, sumber
  generate) untuk setiap tanggal dalam rentang — menimpa Jadwal apa
  pun yang sudah ada di tanggal itu, termasuk sumber manual, karena
  approval adalah fakta yang lebih valid daripada jadwal yang sudah
  diinput sebelumnya. Ini menutup celah dari kedua arah timing
  (generate-duluan-baru-cuti, atau cuti-duluan-baru-generate) —
  GenerateJadwalRotasi sendiri sengaja TIDAK diubah (lebih robust
  nutup di titik approve daripada di titik generate).
- Refactor sekalian: logika sync Absensi yang duplikat persis di
  Cuti::sinkronisasiAbsensi() dan Dinas::afterApprove() di-extract ke
  HasApprovalWorkflow::sinkronisasiJadwalDanAbsensi() (dipakai kedua
  model). Menutup item todo terpisah yang sebelumnya nunggu audit
  cross-module selesai dulu.
- Jadwal model: const JENIS_CUTI/JENIS_DINAS baru. Kolom `jenis` di DB
  tetap varchar bebas (bukan native ENUM) jadi tidak perlu migration.
- Filament JadwalForm: field jenis dapat opsi cuti/dinas, otomatis
  disabled kalau value-nya hasil sync (bukan manual) — mencegah admin
  asal ubah data yang sumbernya dari approval workflow.
- Filament JadwalsTable: badge warna & filter jenis mengenali opsi
  baru (cuti=info, dinas=success).

Full suite: 203 test passing (521 assertions).

## fase 20: audit interaksi lintas modul (Dinas/Cuti x Absensi, TukarJadwal x Cuti/Dinas + API)

Dinas x Absensi:
- Dinas::afterApprove() dan Cuti::sinkronisasiAbsensi() sekarang SELALU
  override status Absensi jadi dinas/cuti, tidak lagi skip kalau
  waktu_masuk sudah terisi. Semua kolom sesi fisik (waktu_masuk/pulang,
  foto, lat/long, menit_terlambat, melebihi_toleransi_bulanan,
  qr_instansi_id) dibersihkan (null/0/false sesuai constraint NOT NULL).
- AbsensiController::masuk() dapat guard baru: tolak absen fisik kalau
  hari itu sudah berstatus dinas atau cuti ("Anda tercatat X hari ini").

Izin/Lembur x Absensi:
- Dikonfirmasi: keputusan tidak sync Izin/Lembur ke Absensi tetap
  benar (kebijakan bisnis, beda per instansi). Ditambahkan test
  dokumentasi (IzinLemburAbsensiTest.php) mengunci behavior ini.

TukarJadwal x Cuti/Dinas + REST API baru:
- Redesain status TukarJadwal: menunggu_rekan -> menunggu_admin ->
  approved/rejected, dengan ditolak_rekan sebagai state akhir kalau
  rekan menolak. Migration ubah enum kolom status + tambah kolom
  direspon_oleh_rekan_id/direspon_rekan_at/catatan_penolakan_rekan.
- Model: method responRekan() untuk rekan tujuan approve/reject,
  scopePending()/isPending() di-override khusus TukarJadwal (pakai
  menunggu_admin, bukan pending dari trait HasApprovalWorkflow),
  helper statis karyawanCutiDinasApproved() dipindah dari Filament
  form ke model biar reusable oleh API.
- Validasi cuti/dinas approved di TukarJadwalForm (Filament) sekarang
  cek 4 kombinasi karyawan x tanggal (bukan cuma 1 sisi).
- REST API baru: POST /api/tukar-jadwal (ajukan, mode tukar & pindah),
  GET /api/tukar-jadwal (riwayat), GET /api/tukar-jadwal/menunggu-respon-saya,
  POST /api/tukar-jadwal/{id}/respon-rekan, DELETE /api/tukar-jadwal/{id}.

Full suite: 191 test passing (474 assertions).

## fase 19: REST API Cuti/Izin/Lembur/Dinas selesai (4/4)

- CutiController: ajukan (jumlah_hari dihitung server-side dari
  tanggal_mulai/tanggal_selesai, bukan dari input klien), riwayat
  (filter status), sisa kuota (GET /api/cuti/kuota, fallback ke
  default_kuota jenis cuti kalau belum ada row KuotaCuti untuk
  tahun itu), batalkan (cuma kalau pending). Validasi kuota
  dilakukan sebelum create — tolak di awal kalau jumlah_hari
  yang diajukan melebihi sisa kuota (bukan nyangkut pas approval).
  Validasi lampiran kondisional berdasar jenisCuti.perlu_lampiran.
  11 test PASS (17 assertions)
- IzinController: ajukan, riwayat (+filter status), batalkan
  (cuma kalau pending). Sengaja TIDAK sync ke Absensi (beda dari
  Cuti/Dinas — izin cuma partial per jam, bukan status harian
  penuh). 8 test PASS (15 assertions)
- LemburController: pola identik dengan Izin (ajukan/riwayat/
  batalkan, tanpa sync Absensi). LemburFactory.php dibuat dari
  nol (belum pernah ada). 8 test PASS (15 assertions)
- DinasController: pola sama dengan Cuti tapi tanpa bagian kuota
  (Dinas approve → sync Absensi.status='dinas', tapi tidak
  menyentuh kolom kuota apapun). 8 test PASS (14 assertions)
- Pola desain yang konsisten dipakai di semua 4 controller:
  scoped lewat $request->user()->{relasi}() (auto-terisolasi ke
  karyawan yang login, bukan query manual + cek karyawan_id),
  pembatalan cuma diizinkan selagi status masih pending (approve/
  reject yang sudah menyentuh Absensi/kuota harus lewat admin,
  bukan self-service), response format standar success/message/
  data konsisten dengan AbsensiController & NotifikasiController
  yang sudah ada duluan.
- Full suite: 166 test PASS (409 assertions) — naik dari 126 di
  fase 18 (+40 test dari 4 API baru). Tidak ada regresi ke test
  lama.
- Semua 4 endpoint dari "Prioritas 1" di todo.md selesai. Item
  yang masih terbuka dari fase ini: telisik lebih dalam soal
  field Karyawan.role vs HasApprovalWorkflow::approve() yang
  type-hint eksplisit ke model User (Filament admin) — bukan
  Karyawan, walau ada isAdmin() yang mirip. Belum ada jalur API
  approve dari sisi karyawan sekarang, tapi perlu dipastikan
  rencana ke depan tidak membuka celah karyawan ber-role admin
  ikut bisa approve pengajuannya sendiri/orang lain.
- Fokus berikutnya: Prioritas 2 di todo.md — evaluasi solidity
  & UX backend (reorganisasi navigationGroup Filament, audit
  interaksi lintas modul yang belum ditest, audit race condition
  kuota Cuti, review konsistensi response API, review UX admin
  panel).

## fase 18: guard server-side simetris tipe_jadwal + drop 2 item prioritas

- KaryawanShiftForm.karyawan_id sekarang punya rule server-side
  Rule::exists('karyawan','id')->where('tipe_jadwal', TIPE_UMUM),
  simetris dengan guard yang sudah ada di KaryawanPolaRotasiForm
  (TIPE_ROTASI) — sebelumnya cuma diproteksi modifyQueryUsing
  (UI-only, bisa dilewati manipulasi request langsung)
- KaryawanShiftResourceTest.php dibuat dari nol (belum pernah ada
  test untuk resource ini) — 5 test: list, create valid, validasi
  tanggal_berakhir < tanggal_berlaku, karyawan_id kosong, dan guard
  baru (menolak karyawan tipe rotasi)
- Full suite: 126 test PASS (327 assertions)
- Keputusan: 2 item dari daftar "Belum Dikerjakan" di-drop dari
  prioritas — konfirmasi 3 kasus dummy jadwal_hilang (Dedi/Siti/
  Rina) dan setup CI GitHub Actions. Dianggap kurang penting
  dibanding kerjaan lain saat ini.
- Fokus baru ditetapkan: REST API untuk Cuti/Izin/Lembur/Dinas
  (belum ada sama sekali — transaksi ini cuma bisa lewat admin
  panel Filament sekarang), dan evaluasi ulang solidity +
  user-friendliness backend secara keseluruhan sebelum lanjut
  nambah fitur baru.

## fase 17: regression test Shift + test command RekapHarian & GenerateJadwalBulanan

- ShiftTest: 3 regression test baru mengunci bahwa
  hitungMenitTerlambat()/tentukanStatus() anchor ke tanggal
  $waktuMasuk, bukan today() — guard supaya bug lama (selalu
  return 0 menit terlambat kalau today() beda tanggal dari
  absen) tidak kambuh. Termasuk kasus lintas tahun (31 Des ->
  5 Jan)
- RekapHarianTest (10 test): sudah-absen skip, libur mingguan
  (karyawan umum tanpa shift/di luar hari kerja), alpha untuk
  umum & rotasi, jadwal_hilang untuk rotasi tanpa Jadwal, libur
  nasional, qr_instansi_id assignment, karyawan tidak aktif
  dilewati
- GenerateJadwalBulananTest (7 test): generate reguler dari
  KaryawanShift, proteksi Jadwal manual, skip libur mingguan,
  skip bentrok Cuti approved, libur nasional digenerate
  eksplisit, --dry-run tidak persist, guard karyawan rotasi yang
  anomali masih punya KaryawanShift
- Full suite: 125 test PASS (321 assertions), commit & push

## fase 16: API test Sanctum lengkap (Auth, Absensi, Notifikasi)

- AuthControllerTest (8): login sukses, password salah, karyawan
  nonaktif ditolak, token lama dihapus saat login baru, logout
  invalidasi token, akses /me dengan & tanpa token
- AbsensiControllerTest (12): status, masuk() untuk karyawan umum
  (tepat waktu & terlambat + notifikasi AbsenTerlambat), validasi
  radius GPS (Haversine), validasi QR (salah/expired), sudah absen
  masuk hari ini, fallback shift rotasi lewat Jadwal harian (bukan
  KaryawanShift), pulang() sukses & validasi urutan
- NotifikasiControllerTest (9): list + filter belum_baca, isolasi
  antar karyawan (tidak bisa lihat/tandai/hapus punya orang lain),
  tandai satu/semua sudah dibaca, hapus, hitung badge belum_baca
- Fix kecil di sisi test: tabel Absensi ternyata singular absensi
  (bukan absensis) — konsisten dengan konvensi custom project
  (instansi, shift)
- Total suite: 108 test PASS (48 Unit + 60 Feature)

## fase 15: generator jadwal rotasi + Filament Resource + test lengkap

- Tabel baru: pola_rotasis (template pola per unit_kerja, kolom
  `langkah` json — urutan shift/libur, panjang siklus fleksibel
  per unit) dan karyawan_pola_rotasis (assignment + tanggal_mulai
  sebagai anchor siklus, mendukung staggered start antar karyawan)
- Tambah kolom `sumber` (generate/manual) ke jadwals supaya
  generator tidak menimpa entry yang sudah diedit manual admin
- Model KaryawanPolaRotasi::posisiSiklusPada() menghitung posisi
  di siklus dari tanggal_mulai — 7 unit test (wrap-around, lintas
  tahun, staggered antar karyawan)
- Command jadwal:generate-rotasi {bulan} {tahun} dengan opsi
  --unit dan --overwrite-generate, integrasi hari_liburs (flag
  berlaku_saat_libur_nasional untuk unit 24 jam seperti IGD/ICU)
  — 6 feature test
- Filament Resource PolaRotasi (form Repeater untuk `langkah`,
  grup Master Data) dan KaryawanPolaRotasi (grup Presensi, label
  "Shift Karyawan Rotasi" berpasangan dengan "Shift Karyawan
  Umum" biar jelas ini untuk 2 tipe_jadwal berbeda) — 9 feature
  test Filament Resource
- Fix kecil: kolom `sumber` sempat tidak ada di $fillable Jadwal
- Total suite: 79 test PASS (48 Unit + 31 Feature)

## fase 15 lanjutan: Filament Resource untuk pola rotasi

- Resource PolaRotasi: form dengan Repeater untuk kolom `langkah`
  (urutan shift/libur per posisi siklus), field instansi_id
  (sempat lupa di form awal -> fix NOT NULL constraint error)
- Resource KaryawanPolaRotasi: assignment karyawan rotasi ke pola
  + tanggal_mulai sebagai anchor siklus
- Rename label biar nggak bingung ke pengguna: "Jadwal Shift" ->
  "Shift Karyawan Umum", "Assignment Rotasi" -> "Shift Karyawan
  Rotasi" -- dua menu ini sengaja dibuat paralel/bersebelahan
  supaya jelas ini pasangan untuk 2 tipe_jadwal yang beda
- Helper text diperjelas di kedua form, saling menunjuk ke menu
  yang benar kalau admin pilih karyawan dengan tipe salah
- Belum dikerjakan: Feature test untuk kedua Filament Resource
  (menyusul, pola sama seperti TukarJadwalResourceTest/
  AbsensiResourceTest)

## fase 15: generator jadwal rotasi (pola_rotasis + karyawan_pola_rotasis)

- Tabel baru: pola_rotasis (template pola per unit_kerja, kolom `langkah`
  json berisi urutan shift/libur, panjang siklus fleksibel per unit) dan
  karyawan_pola_rotasis (assignment karyawan ke pola + tanggal_mulai
  sebagai anchor siklus, mendukung staggered start per karyawan)
- Tambah kolom `sumber` (generate/manual) ke tabel jadwals supaya
  generator tidak menimpa entry yang sudah diedit manual admin
- Model KaryawanPolaRotasi::posisiSiklusPada() menghitung posisi di
  siklus dari tanggal_mulai, dites eksplisit untuk kasus wrap-around,
  lintas tahun, dan staggered antar karyawan (7 unit test)
- Command jadwal:generate-rotasi {bulan} {tahun} generate Jadwal
  bulanan untuk karyawan tipe_jadwal=rotasi berdasarkan pola yang
  di-assign, dengan opsi --unit dan --overwrite-generate
- Integrasi hari_liburs: pola punya flag berlaku_saat_libur_nasional
  (unit 24 jam seperti IGD/ICU tetap masuk saat libur nasional,
  unit lain di-override jadi libur)
- 6 feature test untuk command generator, semua pass
- Fix kecil: kolom `sumber` sempat tidak ada di $fillable Jadwal,
  ditambahkan

## fase 14 lanjutan: Feature test Filament Resource + fix bug hitungMenitTerlambat()

- TukarJadwalResourceTest (9 test): mode tukar & pindah lewat form, validasi rebutan jadwal pending, konflik tanggal, bentrok cuti/dinas
- AbsensiResourceTest (5 test): list, create manual, preview status, akumulasi bulanan (verifikasi via model, bukan lewat form karena menit_terlambat aktual dihitung di AbsensiController::masuk(), bukan di form Filament admin)
- **KETEMU BUG:** `Shift::hitungMenitTerlambat()` selalu return 0, apapun waktu_masuk-nya. Root cause GANDA:
  1. `jam_masuk` di-cast `'datetime:H:i'` di model → `$this->jam_masuk` SELALU objek Carbon, bukan string, meski format tampilannya dibatasi H:i
  2. Objek Carbon itu di-pass langsung ke `setTimeFromTimeString()` yang expect string `"H:i:s"` → Carbon ke-convert otomatis ke string lewat `__toString()` jadi `"Y-m-d H:i:s"` (tanggal SEKARANG + jam shift), bukan format time yang benar → parsing gagal silent, selisih selalu ke-hitung 0
  - Kenapa lolos di 41 test awal: kebetulan tanggal hardcode di test (2026-07-17) waktu itu = tanggal server saat test ditulis, jadi perilaku salahnya "kebetulan" konsisten dengan ekspektasi test
  - **FIX:** tambah `->format('H:i:s')` saat ambil `$this->jam_masuk`, DAN anchor `$jamMasukShift` ke `$waktuMasuk->copy()` (bukan `today()`) supaya benar juga untuk tanggal yang berbeda dari hari ini
  - **DAMPAK:** `AbsensiController::masuk()` TIDAK terpengaruh secara visible (bug ini kebetulan juga bikin hasil 0 menit terlambat, match kalau selalu dipanggil real-time) — tapi kolom `menit_terlambat` & `melebihi_toleransi_bulanan` di SEMUA data yang sudah tersimpan berpotensi salah. PERLU AUDIT data production (lihat Known Gap di PROJECT_CONTEXT.md)
  - Tervalidasi via tinker: `hitungMenitTerlambat()` sekarang return 15 (sebelumnya 0) untuk kasus masuk 07:45 dengan jam_masuk 07:30
- Total: 57 test (41 Unit + 16 Feature — 9 TukarJadwal + 5 Absensi + 2 Example bawaan Laravel), semua PASS
- Commit & push: `c414e07` ke main (khoirul-muallif/absen)
- Belum dikerjakan: audit data Absensi production existing (menit_terlambat mungkin salah di semua row lama), regression test khusus tanggal-berbeda-dari-run-date di ShiftTest, API test (Sanctum), test RekapHarian command, test GenerateJadwalBulanan command

## fase 14: setup testing - Pest v4 + unit test business logic kritis

- Install pestphp/pest v4.7.5, pest-plugin-laravel, pest-plugin-livewire
- phpunit.xml pakai MySQL testing terpisah (absensi_app_test, port 3308, database SAMA mesin dengan absensi_app tapi database berbeda) — bukan SQLite in-memory, karena project pakai fitur MySQL-specific (JSON column shift.hari_kerja)
- tests/Pest.php: RefreshDatabase aktif untuk folder Feature & Unit, helper actingAsAdmin()/actingAsKaryawan()
- HasFactory ditambahkan ke seluruh 15 model (fase 14a, commit terpisah)
- Factory: Instansi, Shift, Karyawan, QrInstansi, KaryawanShift, Jadwal, TukarJadwal, Absensi, JenisCuti, Izin, KuotaCuti, Cuti, Dinas
- ShiftTest (17 test): dokumentasikan temuan penting dari source code — tentukanStatus() TIDAK mempertimbangkan toleransi_menit sama sekali, status harian selalu 'terlambat' begitu lewat 0 menit dari jam masuk. toleransi_menit HANYA dipakai di sudahMelebihiToleransiBulanan() untuk KPI bulanan (mode akumulasi_bulanan)
- TukarJadwalTest (8 test): mode tukar & pindah, race condition kepemilikan jadwal (sisi pengaju maupun tujuan), unique constraint violation saat swap
- HasApprovalWorkflowTest (7 test): pakai model Izin sebagai basis (paling sederhana, tanpa afterApprove() custom) — approve/reject, scope pending/approved/rejected, relasi approver()
- CutiTest (6 test) & DinasTest (3 test): cover afterApprove() — sinkronisasi Absensi untuk SETIAP tanggal dalam rentang (bukan cuma tanggal pertama), skip kalau Absensi sudah ada waktu_masuk asli, TETAP menimpa Absensi existing yang belum ada waktu_masuk, potong kuota KuotaCuti sesuai jenis_cuti.potong_kuota
- Total: 41 test, semua PASS
- Catatan encoding penting: Set-Content -Encoding UTF8 di Windows PowerShell 5.1 menyisipkan BOM yang bikin fatal error "namespace declaration statement harus jadi statement pertama". File .php baru via PowerShell wajib pakai [System.IO.File]::WriteAllText() dengan System.Text.UTF8Encoding($false), atau lebih simpel bikin file langsung lewat VS Code (klik kanan folder → New File)
- Belum dikerjakan: Feature test Filament Resource (livewire testing helpers, built-in di filament/filament v4 — TIDAK perlu install package filament/testing terpisah, itu tidak eksis), API test (Sanctum), test RekapHarian command, test GenerateJadwalBulanan command

## fase 13: tipe_jadwal eksplisit di Karyawan (umum vs rotasi)

Solidkan backend sebelum jumlah karyawan bertambah banyak.

- Migration: kolom karyawan.tipe_jadwal enum(umum/rotasi), default umum, + backfill sekali-jalan dari data KaryawanShift existing (terverifikasi: Budi & Khoirul → umum, Dedi/Siti/Rina → rotasi, sesuai pola seeder)
- Model Karyawan: TIPE_UMUM/TIPE_ROTASI const, isRotasi()/isUmum()
- **KETEMU BUG LATEN:** RekapHarian sebelumnya fallback ke cek KaryawanShift kalau gak ada Jadwal eksplisit — untuk karyawan rotasi yang emang gak pernah punya KaryawanShift, ini bikin "lupa dibuatkan jadwal" didiamkan sebagai libur_mingguan (silent, gak ketahuan). Sekarang RekapHarian branch eksplisit per tipe_jadwal:
  - rotasi: WAJIB ada Jadwal eksplisit, TIDAK ada fallback KaryawanShift. Kalau gak ketemu → stat baru "jadwal_hilang", di-warn ke console, TIDAK dibikin Absensi row (bukan alpha, bukan libur — anomali, perlu dicek manual)
  - umum: logic lama tetap (Jadwal override / fallback KaryawanShift)
- KaryawanShiftForm: Select karyawan_id difilter cuma tipe_jadwal=umum (karyawan rotasi gak akan muncul di dropdown assignment shift periode)
- KaryawanForm: section baru "Tipe Penjadwalan", helper text jelasin konsekuensi tiap tipe
- Command baru: karyawan:cek-tipe-jadwal — deteksi 2 kasus: (a) tipe rotasi tapi punya KaryawanShift (harusnya gak ada), (b) tipe umum tapi gak punya KaryawanShift aktif hari ini
- GenerateJadwalBulanan (fase 12): tambah defensive skip kalau ada karyawan rotasi yang somehow lolos punya KaryawanShift
- Keputusan desain: TIDAK infer tipe dari unit_kerja/jabatan (free-text, rawan typo, mencampur konsep organisasi dgn mekanisme penjadwalan) — pakai field eksplisit sebagai source of truth
- Teruji: karyawan:cek-tipe-jadwal bersih (0 masalah) pasca migrate; absensi:rekap-harian --tanggal=2026-07-15 berhasil flag 3 karyawan rotasi sbg "jadwal_hilang" (Dedi/Siti/Rina) — kemungkinan wajar krn data dummy seeder cuma cover rotasi nonstop 15 hari dari awal periode, 15 Jul kemungkinan di luar rentang itu (belum dikonfirmasi final, cek Jadwal::where('karyawan_id', X)->pluck('tanggal') buat pastikan rentang seeder)

## fase 12: generator Jadwal bulanan otomatis dari KaryawanShift

- Command jadwal:generate-bulanan {--bulan=} {--tahun=} {--dry-run}
- Generate Jadwal reguler dari KaryawanShift (assignment periode), cuma untuk karyawan tipe umum (fase 13) — karyawan rotasi gak disentuh, tetap manual per hari di tabel jadwals
- Skip hari yang bukan hari_kerja shift (libur mingguan wajar, gak bikin row) — konsisten sama fallback logic RekapHarian
- Skip tanggal dengan Cuti/Dinas approved — konsisten sama validasi manual di JadwalForm
- Hari libur nasional (HariLibur) di-generate eksplisit jenis='libur' (shift_id null) supaya tabel jadwal lengkap keliatan
- Idempotent: skip kalau Jadwal udah ada di tanggal tsb (manual override/tukar jadwal gak ketimpa)
- Tervalidasi dry-run bulan Juli 2026: 26 reguler, 14 libur mingguan skip, 22 sudah-ada skip — total 62 = 2 karyawan x 31 hari, cocok
- Catatan: assignment KaryawanShift di seeder dibatasi per bulan (tanggal_berakhir diisi) — generator butuh assignment yang masih cover periode target, extend manual tanggal_berakhir kalau perlu (keputusan sengaja: seeder TIDAK diubah jadi open-ended, per user)
- Belum dijadwalkan ke scheduler otomatis, dijalankan manual dulu sampai logic-nya kepercaya lewat beberapa bulan pemakaian nyata

## fase 11: fix TukarJadwal, filter & sort tanggal di Jadwal

- TukarJadwal: snapshot karyawan_tujuan_id, tanggal_tujuan, shift_asal_id, shift_tujuan_id saat creating() — dulu cuma ada snapshot sisi pengaju
- approveAndSwap(): validasi ulang kepemilikan jadwal sebelum eksekusi swap, cegah race condition (jadwal tujuan berubah kepemilikan sejak pengajuan dibuat)
- Form: validasi jadwal yang dipilih tidak sedang dipakai pengajuan pending lain (cegah 2 pengajuan rebutan jadwal yang sama)
- Form: dropdown jadwal difilter per-karyawan (pilih karyawan dulu, baru muncul jadwalnya) — sebelumnya semua jadwal semua karyawan nyampur jadi satu list
- Table: kolom shift asal & tujuan ditambahkan (sebelumnya cuma tanggal, ambigu kalau karyawan sama punya beberapa shift beda hari)
- Jadwal: default sort tanggal asc (dulu implicit desc, data terbaru ke depan nutupin data yang lebih lampau), tambah filter rentang tanggal (dari-sampai)
- Ditemukan & didokumentasikan: keterbatasan tukar-beda-tanggal untuk karyawan yang kerja nonstop tanpa hari libur
- TukarJadwal sekarang: jadwal_id, karyawan_pengaju_id, tanggal_asal, shift_asal_id, jadwal_tujuan_id (nullable), karyawan_tujuan_id (nullable), tanggal_tujuan (nullable), shift_tujuan_id (nullable), tanggal_baru (nullable, mode pindah), alasan, + kolom approval standar

## fase 10: pisah DatabaseSeeder, sinkronisasi Cuti/Dinas ke Absensi

- DatabaseSeeder jadi orchestrator, seeder dipecah per domain
- KaryawanSeeder: 2 karyawan shift umum (Budi Santoso, Khoirul Alif, Senin-Jumat) + 3 karyawan shift rotasi (Dedi Kurniawan, Siti Aminah, Rina Wulandari — rotasi pagi/siang/malam harian, kerja nonstop 15 hari tanpa libur di data dummy)
- ShiftSeeder: 4 shift (umum, pagi, siang, malam) dengan hari_kerja & mode_toleransi
- HariLiburSeeder: contoh 22 & 30 Juli — baru isi tabel, belum ada integrasi ke Jadwal/Absensi saat itu
- Fix afterApprove() Cuti/Dinas: skip sinkronisasi kalau Absensi sudah ada waktu_masuk asli
- BackfillAbsensiDariCutiDinas command untuk data lama
- Terverifikasi: prediksi akumulasi toleransi, sinkronisasi cuti→absensi, validasi bentrok jadwal

## fase 9.1: merapikan menu

## fase 9: mode toleransi akumulasi bulanan, pisah status harian vs pelanggaran KPI

- Shift: tambah mode_toleransi (harian/akumulasi_bulanan), hari_kerja (pola hari kerja)
- Absensi: tambah menit_terlambat & melebihi_toleransi_bulanan
- Shift::tentukanStatus() selalu berdasar keterlambatan hari itu (awareness karyawan)
- Shift::sudahMelebihiToleransiBulanan() terpisah, cuma buat KPI/admin
- AbsensiController::masuk() simpan menit_terlambat & cek akumulasi bulanan
- Filament: CreateAbsensi hook auto-hitung status & toleransi, preview real-time di form
- ShiftForm/Table: field mode_toleransi & hari_kerja (CheckboxList)
- Model HariLibur (libur nasional/instansi)

## fase 8: model HariLibur, fix duplikat field di JadwalForm

- Tambah model, migration, resource HariLibur (libur nasional/instansi, per tanggal, unique per instansi)
- JadwalForm: hapus field shift_id & jenis yang ke-duplicate, tambah opsi jenis "libur"
- shift_id di form otomatis hidden & non-required saat jenis dipilih "libur"

## fase 7: Filament Resource untuk cuti, izin, lembur, dinas, jadwal, tukar jadwal

- 8 resource: JenisCuti, KuotaCuti, Cuti, Izin, Lembur, Dinas, Jadwal, TukarJadwal
- Form/table/infolist dirapihin: Select pakai relationship nama (bukan ID), field approval (status/approved_by/approved_at) di-hide dari form manual
- Action Setujui/Tolak pakai trait HasApprovalWorkflow, approver = User (Filament admin)
- Jadwal: validasi unique per karyawan+tanggal
- TukarJadwal: approveAndSwap() dengan teknik parkir tanggal sementara buat hindari race condition unique constraint saat swap karyawan_id
- KuotaCuti: auto-fill kuota dari default_kuota JenisCuti, kolom sisa dengan badge warna

## fase 6: model & migration cuti/izin/lembur/dinas/jadwal/tukar jadwal

- Trait HasApprovalWorkflow (status pending/approved/rejected)
- Model: JenisCuti, KuotaCuti, Cuti, Izin, Lembur, Dinas, Jadwal, TukarJadwal
- Jadwal harian (jadwals) dipisah dari shift assignment periode (karyawan_shift)
- approved_by mengarah ke users (admin Filament), bukan karyawan

## fase 5.1: tambah CORS untuk API

## fase 5: scheduler - pengingat absen masuk/pulang, rekap harian alpha

- Command PengingatBelumAbsen & PengingatBelumAbsenPulang: cek karyawan yang belum absen sesuai jam shift, kirim notifikasi
- Command RekapHarian: tandai alpha otomatis untuk karyawan yang sama sekali tidak absen di hari itu — catatan saat itu: belum cek HariLibur (sudah dibenerin, lihat Known Gap di PROJECT_CONTEXT.md)
- Scheduler terdaftar tiap 30 menit (pengingat) & harian jam 23:59 (rekap)
- Teruji manual via artisan command + schedule:list

## fase 4: dashboard filament

- 4 widget: StatsOverview (card statistik), GrafikKehadiran (line chart 30 hari), RekapBulanIni (donut chart rekap status), AbsensiHariIni (tabel)
- Fix kompatibilitas Filament v4: $heading non-static di ChartWidget, tapi tetap static di TableWidget (inkonsistensi API Filament v4)
- Branding admin panel: primary color teal #1D9E75

## fase 3: API lengkap - absensi, notifikasi, logout, riwayat

- NotifikasiController: list, jumlah belum baca, tandai baca, hapus
- Notification class: AbsenTerlambat (auto-trigger saat absen masuk terlambat), BelumAbsen (untuk scheduler)
- Tabel notifications (Laravel notifiable)
- Teruji: notifikasi otomatis masuk saat status absen = terlambat

## fase 3: API absensi selesai - login, masuk, pulang, riwayat, rekap

- AuthController: login, logout, me
- AbsensiController: status, masuk (validasi GPS + QR + foto), pulang, riwayat, rekap bulanan
- Validasi radius GPS pakai Haversine, validasi QR & shift aktif
- Teruji lengkap via Postman: semua endpoint 200 OK

## fase 3: persiapan API

- Install Laravel Sanctum untuk auth token karyawan
- Tambah guard 'karyawan' di config/auth.php (provider terpisah dari admin)
- Uncomment HasApiTokens di model Karyawan

## fase 2: penyesuaian table dan form

- Rapikan semua form & table resource: Select relasi tampil nama (bukan ID)
- Instansi: form 3 section (info, lokasi GPS, status), filter status aktif
- Karyawan: password di-hash saat simpan, FileUpload foto profil/wajah, badge status pegawai & role
- Shift: TimePicker tanpa detik, filter by instansi
- QrInstansi: kode_qr auto-generate saat create
- Absensi: form 3 section (identitas, data masuk, data pulang - collapsed), FileUpload foto, badge status berwarna

## fase 2: resource utama absensi steril / no edit

- Generate 6 Filament Resource dari model (Instansi, QrInstansi, Karyawan, Shift, KaryawanShift, Absensi) via --generate
- Masih bentuk mentah bawaan Filament (select tampil ID, belum ada section)

## fase 1: model + migrate

- Setup 6 tabel inti: instansi, qr_instansi, karyawan, shift, karyawan_shift, absensi
- Urutan migration dijaga manual (bukan auto-generate Laravel) supaya FK aman: instansi → qr_instansi → karyawan → shift → karyawan_shift → absensi
- Model Karyawan extend Authenticatable + HasApiTokens (Sanctum) untuk auth API
- Fitur bawaan model: Instansi::dalamRadius(), QrInstansi::isValid(), Shift::tentukanStatus(), Absensi::durasiMenit()/menitTerlambat()
