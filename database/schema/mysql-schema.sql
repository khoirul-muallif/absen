/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `absensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `qr_instansi_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` datetime DEFAULT NULL,
  `menit_terlambat` int unsigned NOT NULL DEFAULT '0',
  `melebihi_toleransi_bulanan` tinyint(1) NOT NULL DEFAULT '0',
  `latitude_masuk` decimal(10,7) DEFAULT NULL,
  `longitude_masuk` decimal(10,7) DEFAULT NULL,
  `foto_masuk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot wajah saat absen masuk',
  `waktu_pulang` datetime DEFAULT NULL,
  `latitude_pulang` decimal(10,7) DEFAULT NULL,
  `longitude_pulang` decimal(10,7) DEFAULT NULL,
  `foto_pulang` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot wajah saat absen pulang',
  `status` enum('tepat_waktu','terlambat','alpha','izin','cuti','dinas','libur') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alpha',
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `absensi_karyawan_id_tanggal_unique` (`karyawan_id`,`tanggal`),
  KEY `absensi_shift_id_foreign` (`shift_id`),
  KEY `absensi_qr_instansi_id_foreign` (`qr_instansi_id`),
  KEY `absensi_tanggal_index` (`tanggal`),
  KEY `absensi_karyawan_id_status_index` (`karyawan_id`,`status`),
  CONSTRAINT `absensi_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `absensi_qr_instansi_id_foreign` FOREIGN KEY (`qr_instansi_id`) REFERENCES `qr_instansi` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `absensi_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cutis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cutis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `jenis_cuti_id` bigint unsigned NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `jumlah_hari` int unsigned NOT NULL,
  `alasan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lampiran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cutis_karyawan_id_foreign` (`karyawan_id`),
  KEY `cutis_jenis_cuti_id_foreign` (`jenis_cuti_id`),
  KEY `cutis_approved_by_foreign` (`approved_by`),
  KEY `cutis_status_index` (`status`),
  CONSTRAINT `cutis_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cutis_jenis_cuti_id_foreign` FOREIGN KEY (`jenis_cuti_id`) REFERENCES `jenis_cutis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cutis_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dinas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dinas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `tujuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keperluan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dinas_karyawan_id_foreign` (`karyawan_id`),
  KEY `dinas_approved_by_foreign` (`approved_by`),
  KEY `dinas_status_index` (`status`),
  CONSTRAINT `dinas_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dinas_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hari_liburs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hari_liburs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `is_cuti_bersama` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hari_liburs_instansi_id_tanggal_unique` (`instansi_id`,`tanggal`),
  CONSTRAINT `hari_liburs_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `informasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `informasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_terbit` date NOT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `informasi_created_by_foreign` (`created_by`),
  KEY `informasi_instansi_id_is_aktif_tanggal_terbit_index` (`instansi_id`,`is_aktif`,`tanggal_terbit`),
  CONSTRAINT `informasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `informasi_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `instansi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `instansi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_instansi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius_meter` int unsigned NOT NULL DEFAULT '100',
  `alamat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `instansi_kode_instansi_unique` (`kode_instansi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `izins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `izins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `jam_keluar` time NOT NULL,
  `jam_kembali_rencana` time DEFAULT NULL,
  `jam_kembali_aktual` time DEFAULT NULL,
  `keperluan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `izins_karyawan_id_foreign` (`karyawan_id`),
  KEY `izins_approved_by_foreign` (`approved_by`),
  KEY `izins_status_index` (`status`),
  CONSTRAINT `izins_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `izins_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jadwals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reguler',
  `libur_nasional` tinyint(1) NOT NULL DEFAULT '0',
  `sumber` enum('generate','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generate',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jadwals_karyawan_id_tanggal_unique` (`karyawan_id`,`tanggal`),
  KEY `jadwals_shift_id_foreign` (`shift_id`),
  CONSTRAINT `jadwals_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwals_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_cutis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_cutis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_tahunan` tinyint(1) NOT NULL DEFAULT '1',
  `default_kuota` int unsigned NOT NULL DEFAULT '12',
  `periode_kuota` enum('tahunan','semesteran') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tahunan',
  `perlu_lampiran` tinyint(1) NOT NULL DEFAULT '0',
  `potong_kuota` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `karyawan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `karyawan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `nip` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `harus_ganti_password` tinyint(1) NOT NULL DEFAULT '0',
  `nomor_telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_profil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path foto profil',
  `foto_wajah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path foto referensi untuk face recognition',
  `status_pegawai` enum('tetap','kontrak','orientasi','magang') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'orientasi',
  `role` enum('admin','karyawan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'karyawan',
  `tipe_jadwal` enum('umum','rotasi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'umum' COMMENT 'umum = pakai KaryawanShift (assignment periode), rotasi = jadwal harian manual di tabel jadwals, wajib eksplisit tiap hari',
  `unit_kerja` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `karyawan_nip_unique` (`nip`),
  UNIQUE KEY `karyawan_username_unique` (`username`),
  UNIQUE KEY `karyawan_email_unique` (`email`),
  KEY `karyawan_instansi_id_foreign` (`instansi_id`),
  CONSTRAINT `karyawan_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `karyawan_pola_rotasis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `karyawan_pola_rotasis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `pola_rotasi_id` bigint unsigned NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_berakhir` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `karyawan_pola_rotasis_karyawan_id_foreign` (`karyawan_id`),
  KEY `karyawan_pola_rotasis_pola_rotasi_id_foreign` (`pola_rotasi_id`),
  CONSTRAINT `karyawan_pola_rotasis_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `karyawan_pola_rotasis_pola_rotasi_id_foreign` FOREIGN KEY (`pola_rotasi_id`) REFERENCES `pola_rotasis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `karyawan_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `karyawan_shift` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `shift_id` bigint unsigned NOT NULL,
  `tanggal_berlaku` date NOT NULL COMMENT 'Shift aktif mulai tanggal ini',
  `tanggal_berakhir` date DEFAULT NULL COMMENT 'Null = berlaku sampai diganti',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `karyawan_shift_shift_id_foreign` (`shift_id`),
  KEY `karyawan_shift_karyawan_id_tanggal_berlaku_index` (`karyawan_id`,`tanggal_berlaku`),
  CONSTRAINT `karyawan_shift_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `karyawan_shift_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kuota_cutis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kuota_cutis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `jenis_cuti_id` bigint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `semester` tinyint unsigned NOT NULL DEFAULT '0',
  `kuota` int unsigned NOT NULL,
  `terpakai` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kuota_cutis_karyawan_jenis_tahun_semester_unique` (`karyawan_id`,`jenis_cuti_id`,`tahun`,`semester`),
  KEY `kuota_cutis_jenis_cuti_id_foreign` (`jenis_cuti_id`),
  CONSTRAINT `kuota_cutis_jenis_cuti_id_foreign` FOREIGN KEY (`jenis_cuti_id`) REFERENCES `jenis_cutis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuota_cutis_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lemburs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lemburs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `karyawan_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `alasan` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lemburs_karyawan_id_foreign` (`karyawan_id`),
  KEY `lemburs_approved_by_foreign` (`approved_by`),
  KEY `lemburs_status_index` (`status`),
  CONSTRAINT `lemburs_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lemburs_karyawan_id_foreign` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pola_rotasis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pola_rotasis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `unit_kerja` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_pola` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `langkah` json NOT NULL,
  `berlaku_saat_libur_nasional` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pola_rotasis_instansi_id_foreign` (`instansi_id`),
  CONSTRAINT `pola_rotasis_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qr_instansi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_instansi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `kode_qr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expired_at` timestamp NULL DEFAULT NULL COMMENT 'Null = QR statis permanen',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qr_instansi_kode_qr_unique` (`kode_qr`),
  KEY `qr_instansi_instansi_id_foreign` (`instansi_id`),
  CONSTRAINT `qr_instansi_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instansi_id` bigint unsigned NOT NULL,
  `nama_shift` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `toleransi_menit` int unsigned NOT NULL DEFAULT '15' COMMENT 'Batas menit terlambat sebelum dianggap alpha',
  `mode_toleransi` enum('harian','akumulasi_bulanan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'harian',
  `hari_kerja` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shift_instansi_id_foreign` (`instansi_id`),
  CONSTRAINT `shift_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `instansi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukar_jadwals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukar_jadwals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jadwal_id` bigint unsigned NOT NULL,
  `karyawan_pengaju_id` bigint unsigned DEFAULT NULL,
  `tanggal_asal` date DEFAULT NULL,
  `shift_asal_id` bigint unsigned DEFAULT NULL,
  `jadwal_tujuan_id` bigint unsigned DEFAULT NULL,
  `karyawan_tujuan_id` bigint unsigned DEFAULT NULL,
  `direspon_oleh_rekan_id` bigint unsigned DEFAULT NULL,
  `direspon_rekan_at` timestamp NULL DEFAULT NULL,
  `catatan_penolakan_rekan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_tujuan` date DEFAULT NULL,
  `shift_tujuan_id` bigint unsigned DEFAULT NULL,
  `tanggal_baru` date DEFAULT NULL,
  `alasan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu_rekan','menunggu_admin','ditolak_rekan','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_admin',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tukar_jadwals_jadwal_id_foreign` (`jadwal_id`),
  KEY `tukar_jadwals_jadwal_tujuan_id_foreign` (`jadwal_tujuan_id`),
  KEY `tukar_jadwals_approved_by_foreign` (`approved_by`),
  KEY `tukar_jadwals_karyawan_pengaju_id_foreign` (`karyawan_pengaju_id`),
  KEY `tukar_jadwals_karyawan_tujuan_id_foreign` (`karyawan_tujuan_id`),
  KEY `tukar_jadwals_shift_asal_id_foreign` (`shift_asal_id`),
  KEY `tukar_jadwals_shift_tujuan_id_foreign` (`shift_tujuan_id`),
  KEY `tukar_jadwals_direspon_oleh_rekan_id_foreign` (`direspon_oleh_rekan_id`),
  KEY `tukar_jadwals_status_index` (`status`),
  CONSTRAINT `tukar_jadwals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukar_jadwals_direspon_oleh_rekan_id_foreign` FOREIGN KEY (`direspon_oleh_rekan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukar_jadwals_jadwal_id_foreign` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tukar_jadwals_jadwal_tujuan_id_foreign` FOREIGN KEY (`jadwal_tujuan_id`) REFERENCES `jadwals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tukar_jadwals_karyawan_pengaju_id_foreign` FOREIGN KEY (`karyawan_pengaju_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukar_jadwals_karyawan_tujuan_id_foreign` FOREIGN KEY (`karyawan_tujuan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukar_jadwals_shift_asal_id_foreign` FOREIGN KEY (`shift_asal_id`) REFERENCES `shift` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tukar_jadwals_shift_tujuan_id_foreign` FOREIGN KEY (`shift_tujuan_id`) REFERENCES `shift` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_01_053743_create_instansis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_07_01_053743_create_qr_instansis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_01_053744_create_karyawans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_01_053745_create_shifts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_01_053746_create_karyawan_shifts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_01_053747_create_absensis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_06_013704_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_07_082037_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_10_081436_create_jenis_cutis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_10_081437_create_kuota_cutis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_10_081438_create_cutis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_10_081438_create_izins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_07_10_081439_create_dinas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_07_10_081439_create_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_07_10_081439_create_lemburs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_07_10_081440_create_tukar_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_07_10_131625_make_shift_id_nullable_in_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_07_10_132045_create_hari_liburs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_07_10_133941_add_hari_kerja_to_shift_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_07_10_135209_add_mode_toleransi_to_shift_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_07_10_135826_add_menit_terlambat_to_absensi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_07_13_075558_add_melebihi_toleransi_to_absensi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_07_13_130341_make_shift_qr_nullable_in_absensi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_07_14_121530_make_jadwal_tujuan_nullable_add_tanggal_baru_to_tukar_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_16_074310_add_snapshot_columns_to_tukar_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_07_16_124626_add_shift_dan_karyawan_tujuan_snapshot_to_tukar_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_07_16_124627_add_tipe_jadwal_to_karyawan_table_Code',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_07_21_105925_create_pola_rotasis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_07_21_110017_create_karyawan_pola_rotasis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_07_21_110046_add_sumber_to_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_07_27_141955_alter_status_enum_and_add_respon_rekan_columns_to_tukar_jadwals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_07_30_081828_make_alasan_nullable_in_lemburs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_10_08_085329_add_semester_to_kuota_cutis_and_periode_kuota_to_jenis_cutis',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_11_09_085341_create_informasis_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_11_09_111642_hapus_sakit_dari_enum_status_absensi',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_11_09_123123_tambah_index_status_di_tabel_approval',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_11_09_125349_tambah_username_ke_tabel_karyawan',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_11_10_075919_ubah_email_di_tabel_karyawan',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_11_23_125522_add_logo_to_instansi_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_11_24_083017_pisah_jam_kembali_rencana_dan_aktual_di_izins',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_11_26_080233_tambah_harus_ganti_password_di_karyawans',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_11_27_133433_add_libur_nasional_to_jadwals_table',10);
