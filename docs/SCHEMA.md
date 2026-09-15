# SCHEMA — absensi-app

> Sumber: export langsung dari database (Navicat dump), bukan ditulis manual —
> jadi ini SELALU representasi paling akurat, termasuk migrate baru yang lupa
> direkap di PROJECT_CONTEXT.md. Kalau ada perbedaan antara file ini dan
> penjelasan naratif di PROJECT_CONTEXT.md, **file ini yang benar** soal
> struktur kolom/tipe/constraint — tapi PROJECT_CONTEXT.md tetap sumber utama
> untuk *alasan* desain (kenapa kolom itu ada, kenapa nullable, dst).

**Update terakhir:** 28 Juli 2026 (dari dump 28/07/2026 15:08)

> Cara update file ini: export ulang dump dari Navicat/`mysqldump`, minta
> Claude bandingkan sama versi lama, lalu tulis ulang bagian yang berubah.

---

## Master data

**instansi** — nama, kode_instansi (unique), latitude/longitude, radius_meter (default 100), alamat, telepon, is_active

**qr_instansi** — instansi_id, kode_qr (unique), is_active, expired_at (null = permanen)

**karyawan** — instansi_id, nip (unique), nama, email (unique), password, nomor_telepon, foto_profil, foto_wajah, status_pegawai (tetap/kontrak/orientasi/magang), role (admin/karyawan), **tipe_jadwal (umum/rotasi)**, unit_kerja, jabatan, tanggal_bergabung, is_active
- Catatan: `role` di sini beda dari sistem approval (lihat PROJECT_CONTEXT.md Known Gap #4 — approval pakai `users`, bukan `karyawan.role`)

**shift** — instansi_id, nama_shift, jam_masuk, jam_pulang, toleransi_menit (default 15), mode_toleransi (harian/akumulasi_bulanan), hari_kerja (json), is_active

**karyawan_shift** — karyawan_id, shift_id, tanggal_berlaku, tanggal_berakhir (null = berlaku sampai diganti). Index: (karyawan_id, tanggal_berlaku)

**hari_liburs** — instansi_id, tanggal, nama, keterangan, is_cuti_bersama. Unique(instansi_id, tanggal)

**pola_rotasis** — instansi_id, unit_kerja, nama_pola, langkah (json), berlaku_saat_libur_nasional (default true), is_active

**karyawan_pola_rotasis** — karyawan_id, pola_rotasi_id, tanggal_mulai (anchor siklus), tanggal_berakhir (nullable)

## Transaksi absensi

**absensi** — karyawan_id, shift_id (nullable), qr_instansi_id (nullable), tanggal, waktu_masuk, menit_terlambat (default 0), melebihi_toleransi_bulanan (default false), latitude_masuk/longitude_masuk, foto_masuk, waktu_pulang, latitude_pulang/longitude_pulang, foto_pulang, **status enum: tepat_waktu/terlambat/alpha/izin/**sakit**/cuti/dinas/libur** (default alpha), keterangan
- Unique(karyawan_id, tanggal). Index tambahan: tanggal, (karyawan_id, status)
- ⚠️ **`status='sakit'` ada di enum tapi belum ada modul/controller yang eksplisit menanganinya** di dokumentasi manapun (QUICK_CONTEXT/PROJECT_CONTEXT/CHANGELOG). Kemungkinan reserved untuk fitur belum jalan, atau sisa desain awal. Perlu diklarifikasi — apakah rencana ke depan ada modul terpisah, atau ini dead enum value yang aman dibiarkan.

## Cuti/Izin/Lembur/Dinas (approval: pending/approved/rejected, approved_by → users)

**jenis_cutis** — nama, is_tahunan, default_kuota (default 12), perlu_lampiran, potong_kuota, is_active

**kuota_cutis** — karyawan_id, jenis_cuti_id, tahun, kuota, terpakai (default 0). Unique(karyawan_id, jenis_cuti_id, tahun)

**cutis** — karyawan_id, jenis_cuti_id, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, lampiran (nullable), status, approved_by, approved_at, catatan_approval

**izins** — karyawan_id, tanggal, jam_keluar, jam_kembali (nullable), keperluan, status, approved_by, approved_at, catatan_approval
- Tidak sync ke Absensi (sengaja, lihat QUICK_CONTEXT Larangan)

**lemburs** — karyawan_id, tanggal, jam_mulai, jam_selesai, alasan (nullable), status, approved_by, approved_at, catatan_approval
- Tidak sync ke Absensi

**dinas** — karyawan_id, tanggal_mulai, tanggal_selesai, tujuan, keperluan, status, approved_by, approved_at, catatan_approval
- Sync ke Absensi (status='dinas'), tanpa kuota

## Jadwal & tukar jadwal

**jadwals** — karyawan_id, shift_id (nullable), tanggal, jenis (varchar bebas: reguler/piket/libur/cuti/dinas — bukan native ENUM), sumber (generate/manual, default generate), keterangan. Unique(karyawan_id, tanggal)

**tukar_jadwals** — jadwal_id, karyawan_pengaju_id (nullable), tanggal_asal, shift_asal_id (nullable), jadwal_tujuan_id (nullable), karyawan_tujuan_id (nullable), direspon_oleh_rekan_id (nullable → karyawan), direspon_rekan_at, catatan_penolakan_rekan, tanggal_tujuan (nullable), shift_tujuan_id (nullable), tanggal_baru (nullable, mode pindah), alasan, **status enum: menunggu_rekan/menunggu_admin/ditolak_rekan/approved/rejected** (default menunggu_admin), approved_by, approved_at, catatan_approval

## Sistem (Laravel bawaan)
cache, cache_locks, failed_jobs, job_batches, jobs, migrations, notifications (notifiable_type/id), password_reset_tokens, personal_access_tokens (Sanctum), sessions, users (admin Filament — terpisah dari karyawan)

---

## Ringkasan Foreign Key & Delete Behavior yang perlu diperhatikan
- Semua FK ke `karyawan_id` → `ON DELETE CASCADE` (hapus karyawan = hapus semua histori terkait)
- `approved_by` → `users.id` → `ON DELETE SET NULL` (admin dihapus, histori approval tetap ada tanpa approver)
- `tukar_jadwals` FK ke `karyawan` (pengaju/tujuan/direspon) → `ON DELETE SET NULL`, bukan CASCADE — beda perlakuan dari tabel lain, karena histori tukar jadwal tetap perlu dipertahankan walau salah satu karyawan dihapus
- `absensi.qr_instansi_id` dan `absensi.shift_id` → `ON DELETE RESTRICT` (tidak bisa hapus QR/shift kalau masih direferensikan histori absensi)

## Perbedaan dengan penjelasan lama di PROJECT_CONTEXT.md (per 28 Juli 2026)
Tidak ditemukan perbedaan struktural signifikan — skema di dump ini konsisten
dengan yang dinarasikan di PROJECT_CONTEXT.md versi 4. Satu catatan baru:
enum `absensi.status` punya opsi `'sakit'` yang belum disebut eksplisit di
dokumentasi manapun (lihat catatan ⚠️ di atas).
