# Status Frontend Absensi — 14 Oktober 2026

**Terakhir diperbarui:** 14 Oktober 2026  
**Posisi:** Fase 7 selesai; audit sinkronisasi API selesai; perbaikan backend shift malam sudah di-commit.

## 1. Status Repository Frontend

- Repository: `absensi_frontapp`
- Fase 7 (`flutter_dotenv`) sudah di-push.
- Working tree bersih dan sinkron dengan `origin/main`.
- Keputusan lanjutan: sinkronkan kontrak API dengan backend terbaru terlebih dahulu, baru mulai modul baru.

### Fitur yang sudah tersedia

- Login menggunakan Sanctum.
- Absen masuk: lokasi GPS, pemindaian QR, dan face liveness.
- Absen pulang: lokasi GPS dan face liveness.
- Riwayat dan rekap absensi.
- Auto-login melalui endpoint `/me`.
- Logout.
- Notifikasi.

## 2. Hasil Audit Sinkronisasi API

Audit file awal frontend telah selesai. Struktur utama `lib/` saat audit:

- `models/`: `absensi_model`, `notifikasi_model`, `riwayat_absen_model`, `user_model`
- `providers/`: `auth_provider`
- `screens/`: home, login, notifikasi, riwayat, splash, serta flow absen masuk/pulang dan langkah-langkahnya
- `services/`: `absensi_service`, `auth_service`, `notifikasi_service`
- `utils/`: `constants`, `permission_helper`

Dependensi yang sudah tersedia antara lain `http`, `geolocator`, `mobile_scanner`, `camera`, `google_mlkit_face_detection`, `provider`, `shared_preferences`, `intl`, `permission_handler`, dan `flutter_dotenv`.

## 3. Temuan dan Tindak Lanjut Frontend

### Bug terkonfirmasi: parsing daftar notifikasi

`notifikasi_service.dart::getNotifikasi()` masih membaca struktur respons lama:

```dart
data['notifikasi']['data']
```

Backend fase 24 menggunakan struktur baru `data.records` dan `data.total` tanpa pagination. Parsing daftar perlu diubah menjadi membaca `data['records']`.

**Belum terverifikasi:** apakah field `total_belum_baca` masih tersedia atau sudah berubah nama pada respons listing. Perlu dicocokkan dengan `NotifikasiController.php`.

### Gap desain: API client dan penanganan 401

Belum ada API client terpusat. `auth_service`, `absensi_service`, dan `notifikasi_service` menangani HTTP/header sendiri-sendiri.

Jika token kedaluwarsa, `AbsensiService` dan `NotifikasiService` dapat mengembalikan `null` atau daftar kosong tanpa mengarahkan pengguna kembali ke login. Disarankan membuat `ApiClient` terpusat dengan penanganan respons `401` sebelum menambahkan modul Cuti, Izin, Lembur, Dinas, dan Tukar Jadwal.

### Konfigurasi URL API

- `constants.dart` masih memiliki fallback `http://localhost:8000/api` jika `.env` gagal dimuat.
- Backend development berjalan di port `8001`.
- `.env.example` masih mencantumkan port `8000`.

Tindak lanjut: hapus atau perbaiki fallback yang berpotensi menyamarkan kegagalan konfigurasi, lalu sesuaikan `.env.example` dengan port backend development.

### Bagian yang sudah diperiksa dan dinyatakan aman

- Format `jam_masuk`/`waktu_masuk` sebagai string cocok dengan format backend `H:i` sejak fase 27.
- Penanganan error validasi login cocok dengan struktur `{success, message, errors}` pada fase 24.
- Endpoint notifikasi untuk jumlah, baca, baca-semua, hapus, dan filter `belum_baca` cocok dengan dokumentasi backend.
- `AbsenFlowScreen` dan `AbsenPulangFlowScreen` bertindak sebagai pengatur langkah UI; pemanggilan service berada di `step_wajah.dart`.
- Flow absen pulang menggunakan GPS dan foto tanpa QR, sesuai kontrak backend.
- `step_wajah.dart` menampilkan `result['message']` dari backend saat gagal, sehingga pesan baru backend (misalnya terkait libur rotasi, cuti, atau dinas) dapat ditampilkan tanpa perubahan khusus.
- Logika navigasi di `home_screen.dart` berdasarkan status masuk/pulang sudah benar, dengan catatan status backend untuk shift malam perlu konsisten.

## 4. Bug Backend Shift Malam — Sudah Di-commit

### Masalah

`AbsensiController::status()` sebelumnya hanya mencari absensi dengan `whereDate('tanggal', today())`. Ini tidak mengikuti perbaikan jendela dua hari pada `pulang()` sejak fase 40.

Akibatnya, karyawan shift malam yang absen masuk pada tanggal **T** lalu membuka aplikasi pada **T+1** sebelum absen pulang bisa dianggap belum absen masuk. Frontend kemudian dapat mengarahkan pengguna ke flow absen masuk lagi, sehingga berisiko membuat data absensi duplikat.

### Perbaikan yang diusulkan dan dicatat

- `status()`: prioritaskan sesi terbuka (`waktu_masuk` tidak null dan `waktu_pulang` null) dalam jendela kemarin–hari ini; jika tidak ada sesi terbuka, gunakan data hari ini.
- `masuk()`: tambahkan guard di awal untuk menolak absen masuk baru jika masih ada sesi kemarin yang belum memiliki `waktu_pulang`.
- `pulang()`: tidak diubah karena sudah memiliki penanganan jendela dua hari sejak fase 40.
- `home_screen.dart`: tidak perlu diubah jika kontrak `status()` backend sudah benar.

### Status commit

Perubahan `AbsensiController.php` pada repository backend `absensi-app` sudah di-commit dan commit di-amend:

- **Commit:** `b0dbee6`
- **Pesan:** `fix: status() & masuk() belum ikuti jendela 2 hari shift malam (lanjutan fase 40)`

Catatan: commit sudah dibuat, tetapi pengujian perilaku shift malam tetap perlu dijalankan untuk memastikan sesi T yang masih terbuka dikenali pada T+1 dan absen masuk duplikat ditolak.

## 5. Urutan Pekerjaan Berikutnya

1. **Selesaikan bug notifikasi frontend**
   - Ubah parsing daftar dari `data['notifikasi']['data']` ke `data['records']`.
   - Verifikasi nama dan lokasi field penghitung notifikasi belum dibaca melalui `NotifikasiController.php`.
2. **Uji perbaikan shift malam di backend**
   - Absen masuk pada tanggal T.
   - Periksa status pada T+1 sebelum absen pulang.
   - Pastikan status menunjukkan sesi masih berjalan dan flow pulang yang dipilih.
   - Coba absen masuk lagi pada T+1 dan pastikan guard menolaknya.
   - Jalankan test terkait serta periksa efeknya pada shift biasa.
3. **Bangun `ApiClient` terpusat**
   - Sentralisasi base URL, header, token, dan parsing respons.
   - Tangani `401` secara konsisten, termasuk pengembalian ke layar login.
   - Migrasikan service yang ada secara bertahap dan uji ulang.
4. **Rapikan konfigurasi**
   - Sesuaikan `.env.example` dengan port backend development `8001`.
   - Hapus/perbaiki fallback `localhost:8000` agar konfigurasi yang salah tidak tersembunyi.
5. **Baru mulai modul baru**
   - Rencanakan modul Cuti/Izin/Lembur/Dinas/Tukar Jadwal setelah kontrak API dan penanganan autentikasi stabil.
   - Tambahkan `file_picker` atau `image_picker` saat kebutuhan lampiran Cuti sudah dikerjakan.

## 6. File yang Sudah Ditinjau

- `pubspec.yaml`
- `utils/constants.dart`
- `services/absensi_service.dart`
- `main.dart`
- `models/absensi_model.dart`
- `models/notifikasi_model.dart`
- `models/riwayat_absen_model.dart`
- `models/user_model.dart`
- `services/notifikasi_service.dart`
- `services/auth_service.dart`
- `providers/auth_provider.dart`
- `screens/absen/absen_flow_screen.dart`
- `screens/absen/absen_pulang_flow_screen.dart`
- `screens/absen/step_wajah.dart`
- `screens/home_screen.dart`
- `.env.example`

## Ringkasan Singkat

Audit sinkronisasi API frontend sudah selesai. Dua pekerjaan frontend yang paling penting adalah memperbaiki parsing respons daftar notifikasi dan membuat `ApiClient` dengan penanganan `401` terpusat. Konfigurasi port juga perlu diseragamkan ke `8001`.

Di backend, perbaikan `AbsensiController::status()` dan `masuk()` untuk shift malam sudah di-commit sebagai `b0dbee6`. Langkah berikutnya adalah menguji skenario shift malam secara langsung, lalu melanjutkan perbaikan frontend sebelum menambah modul baru.
