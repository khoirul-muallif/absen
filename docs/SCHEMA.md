# SCHEMA — absensi-app

> Sumber: export langsung dari database (Navicat dump), bukan ditulis manual —
> jadi ini SELALU representasi paling akurat, termasuk migrate baru yang lupa
> direkap di PROJECT_CONTEXT.md. Kalau ada perbedaan antara file ini dan
> penjelasan naratif di PROJECT_CONTEXT.md, **file ini yang benar** soal
> struktur kolom/tipe/constraint — tapi PROJECT_CONTEXT.md tetap sumber utama
> untuk *alasan* desain (kenapa kolom itu ada, kenapa nullable, dst).

**Update terakhir:** 9 November 2026 (dari dump 09/11/2026 09:22)

> Cara update file ini: export ulang dump dari Navicat/`mysqldump`, minta
> Claude bandingkan sama versi lama, lalu tulis ulang bagian yang berubah.

---

## Master data

**instansi** — nama, kode_instansi (unique), latitude/longitude, radius_meter (default 100), alamat, telepon, is_active

**qr_instansi** — instansi_id, kode_qr (unique), is_active, expired_at (null = permanen)

**karyawan** — instansi_id, nip (unique), nama,username (unique), email, password, nomor_telepon, foto_profil, foto_wajah, status_pegawai (tetap/kontrak/orientasi/magang), role (admin/karyawan), **tipe_jadwal (umum/rotasi)**, unit_kerja, jabatan, tanggal_bergabung, is_active, remember_token
- Catatan: `role` di sini beda dari sistem approval (lihat PROJECT_CONTEXT.md Known Gap #4 — approval pakai `users`, bukan `karyawan.role`)

**shift** — instansi_id, nama_shift, jam_masuk, jam_pulang, toleransi_menit (default 15), mode_toleransi (harian/akumulasi_bulanan), hari_kerja (json), is_active

**karyawan_shift** — karyawan_id, shift_id, tanggal_berlaku, tanggal_berakhir (null = berlaku sampai diganti). Index: (karyawan_id, tanggal_berlaku)

**hari_liburs** — instansi_id, tanggal, nama, keterangan, is_cuti_bersama. Unique(instansi_id, tanggal)

**pola_rotasis** — instansi_id, unit_kerja, nama_pola, langkah (json), berlaku_saat_libur_nasional (default true), is_active

**karyawan_pola_rotasis** — karyawan_id, pola_rotasi_id, tanggal_mulai (anchor siklus), tanggal_berakhir (nullable)

## Transaksi absensi

**absensi** — karyawan_id, shift_id (nullable), qr_instansi_id (nullable), tanggal, waktu_masuk, menit_terlambat (default 0), melebihi_toleransi_bulanan (default false), latitude_masuk/longitude_masuk, foto_masuk, waktu_pulang, latitude_pulang/longitude_pulang, foto_pulang, **status enum: tepat_waktu/terlambat/alpha/izin/cuti/dinas/libur** (default alpha), keterangan
- Unique(karyawan_id, tanggal). Index tambahan: tanggal, (karyawan_id, status)

## Cuti/Izin/Lembur/Dinas (approval: pending/approved/rejected, approved_by → users)

**jenis_cutis** — nama, is_tahunan, default_kuota (default 12), **periode_kuota (tahunan/semesteran, default tahunan)**, perlu_lampiran, potong_kuota, is_active

**kuota_cutis** — karyawan_id, jenis_cuti_id, tahun, **semester (tinyint, default 0 = bukan semesteran)**, kuota, terpakai (default 0). Unique(karyawan_id, jenis_cuti_id, tahun, **semester**)

**cutis** — karyawan_id, jenis_cuti_id, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, lampiran (nullable), status, approved_by, approved_at, catatan_approval
- Index: `status` (dipakai `getNavigationBadge()`, fase 77)

**izins** — karyawan_id, tanggal, jam_keluar, jam_kembali (nullable), keperluan, status, approved_by, approved_at, catatan_approval
- Tidak sync ke Absensi (sengaja, lihat QUICK_CONTEXT Larangan)

**lemburs** — karyawan_id, tanggal, jam_mulai, jam_selesai, alasan (nullable), status, approved_by, approved_at, catatan_approval
- Tidak sync ke Absensi

**dinas** — karyawan_id, tanggal_mulai, tanggal_selesai, tujuan, keperluan, status, approved_by, approved_at, catatan_approval
- Sync ke Absensi (status='dinas'), tanpa kuota

## Jadwal & tukar jadwal

**jadwals** — karyawan_id, shift_id (nullable), tanggal, jenis (varchar bebas: reguler/piket/libur/cuti/dinas — bukan native ENUM), sumber (generate/manual, default generate), keterangan. Unique(karyawan_id, tanggal)

**tukar_jadwals** — jadwal_id, karyawan_pengaju_id (nullable), tanggal_asal, shift_asal_id (nullable), jadwal_tujuan_id (nullable), karyawan_tujuan_id (nullable), direspon_oleh_rekan_id (nullable → karyawan), direspon_rekan_at, catatan_penolakan_rekan, tanggal_tujuan (nullable), shift_tujuan_id (nullable), tanggal_baru (nullable, mode pindah), alasan, **status enum: menunggu_rekan/menunggu_admin/ditolak_rekan/approved/rejected** (default menunggu_admin), approved_by, approved_at, catatan_approval

## Informasi / Pengumuman

**informasi** — instansi_id, judul, isi (text), tanggal_terbit (date), is_aktif (default true), created_by (nullable → users). Index: (instansi_id, is_aktif, tanggal_terbit)
- Nama tabel **singular** (`informasi`, bukan `informasis`) — override `$table` di model, ikut pola instansi/shift/absensi
- `created_by` diisi otomatis dari admin Filament yang login, tidak ada di form
- API karyawan auto-scoped ke `instansi_id` miliknya; baris `is_aktif=false` tidak pernah muncul

## Sistem (Laravel bawaan)
cache, cache_locks, failed_jobs, job_batches, jobs, migrations, notifications (notifiable_type/id), password_reset_tokens, personal_access_tokens (Sanctum — punya `expires_at` + index), sessions, users (admin Filament — terpisah dari karyawan)

---

## Ringkasan Foreign Key & Delete Behavior yang perlu diperhatikan
- Semua FK ke `karyawan_id` → `ON DELETE CASCADE` (hapus karyawan = hapus semua histori terkait)
- `approved_by` dan `informasi.created_by` → `users.id` → `ON DELETE SET NULL` (admin dihapus, histori tetap ada tanpa approver/pembuat)
- `tukar_jadwals` FK ke `karyawan` (pengaju/tujuan/direspon) → `ON DELETE SET NULL`, bukan CASCADE — beda perlakuan dari tabel lain, karena histori tukar jadwal tetap perlu dipertahankan walau salah satu karyawan dihapus
- `absensi.qr_instansi_id` dan `absensi.shift_id` → `ON DELETE RESTRICT` (tidak bisa hapus QR/shift kalau masih direferensikan histori absensi)
- ⚠️ **`jadwals.shift_id` → `ON DELETE CASCADE`, beda dari `absensi.shift_id` yang RESTRICT.** Hapus satu shift berarti semua baris `jadwals` yang memakainya ikut terhapus diam-diam, sementara absensi justru menahan penghapusan — jadi penghapusan shift bisa gagal di tengah setelah jadwal terlanjur hilang? Tidak, RESTRICT absensi dicek lebih dulu dalam transaksi yang sama, tapi perbedaan perlakuan ini belum pernah diputuskan secara sadar. Dicatat 9 Nov 2026, belum ditelusuri.

## Perubahan sejak versi 28 Juli 2026
Lima selisih ditemukan saat membandingkan dump 9 Nov:
1. **`jenis_cutis.periode_kuota`** (enum tahunan/semesteran) — kolom baru, dari fase KuotaCuti semesteran, tidak pernah terekap.
2. **`kuota_cutis.semester`** + unique index berubah dari 3 kolom jadi 4 — dari fase yang sama, juga tidak terekap.
3. **`informasi`** — tabel baru (9 Nov 2026).
4. `karyawan.remember_token` — kecil, kemungkinan sudah ada sejak awal tapi tidak tercatat.
5. `personal_access_tokens.expires_at` + index — bawaan Laravel/Sanctum versi baru.

Nomor 1 dan 2 adalah contoh persis dari apa yang diperingatkan QUICK_CONTEXT
("ingetkan update SCHEMA.md kalau ada migration baru") — terlewat dan baru
ketahuan tiga bulan kemudian. Pertimbangkan export dump rutin tiap akhir
sesi yang menyentuh migration, bukan hanya saat teringat.
