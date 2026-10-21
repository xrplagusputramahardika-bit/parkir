-- Database Schema & Initial Data for Smart Parking System (tempat_parkir)
-- Generated for MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS `parkiran` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `parkiran`;

-- 1. Table User
CREATE TABLE IF NOT EXISTS `tb_user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('admin','petugas','owner') NOT NULL,
  `shift_kerja` enum('pagi','malam') DEFAULT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `tb_user_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Table Tarif
CREATE TABLE IF NOT EXISTS `tb_tarif` (
  `id_tarif` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_kendaraan` enum('motor','mobil','lainnya') NOT NULL,
  `tarif_per_jam` decimal(10,0) NOT NULL,
  PRIMARY KEY (`id_tarif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Table Area Parkir
CREATE TABLE IF NOT EXISTS `tb_area_parkir` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `nama_area` varchar(50) NOT NULL,
  `kapasitas` int(5) NOT NULL,
  `terisi` int(5) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Table Kendaraan
CREATE TABLE IF NOT EXISTS `tb_kendaraan` (
  `id_kendaraan` int(11) NOT NULL AUTO_INCREMENT,
  `plat_nomor` varchar(15) NOT NULL,
  `jenis_kendaraan` varchar(20) NOT NULL,
  `warna` varchar(20) DEFAULT NULL,
  `pemilik` varchar(100) DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  PRIMARY KEY (`id_kendaraan`),
  KEY `tb_kendaraan_id_user_foreign` (`id_user`),
  CONSTRAINT `tb_kendaraan_id_user_foreign` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Table Transaksi
CREATE TABLE IF NOT EXISTS `tb_transaksi` (
  `id_parkir` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `waktu_masuk` datetime NOT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `id_tarif` int(11) NOT NULL,
  `durasi_jam` int(5) DEFAULT NULL,
  `biaya_total` decimal(10,0) DEFAULT NULL,
  `status` enum('masuk','keluar') NOT NULL DEFAULT 'masuk',
  `id_user` int(11) NOT NULL,
  `id_user_keluar` int(11) DEFAULT NULL,
  `id_area` int(11) NOT NULL,
  `slot_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_parkir`),
  KEY `tb_transaksi_id_tarif_foreign` (`id_tarif`),
  KEY `tb_transaksi_id_kendaraan_foreign` (`id_kendaraan`),
  KEY `tb_transaksi_id_area_foreign` (`id_area`),
  KEY `tb_transaksi_area_status_slot_index` (`id_area`,`status`,`slot_number`),
  KEY `tb_transaksi_id_user_foreign` (`id_user`),
  KEY `tb_transaksi_id_user_keluar_foreign` (`id_user_keluar`),
  CONSTRAINT `tb_transaksi_id_area_foreign` FOREIGN KEY (`id_area`) REFERENCES `tb_area_parkir` (`id_area`) ON DELETE RESTRICT,
  CONSTRAINT `tb_transaksi_id_kendaraan_foreign` FOREIGN KEY (`id_kendaraan`) REFERENCES `tb_kendaraan` (`id_kendaraan`) ON DELETE CASCADE,
  CONSTRAINT `tb_transaksi_id_tarif_foreign` FOREIGN KEY (`id_tarif`) REFERENCES `tb_tarif` (`id_tarif`) ON DELETE RESTRICT,
  CONSTRAINT `tb_transaksi_id_user_foreign` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tb_transaksi_kasir_user` FOREIGN KEY (`id_user_keluar`) REFERENCES `tb_user` (`id_user`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Table Log Aktivitas
CREATE TABLE IF NOT EXISTS `tb_log_aktivitas` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `aktivitas` varchar(100) NOT NULL,
  `waktu_aktivitas` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `tb_log_aktivitas_id_user_foreign` (`id_user`),
  CONSTRAINT `tb_log_aktivitas_id_user_foreign` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Data
INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `shift_kerja`, `status_aktif`) VALUES
(1, 'Administrator SmartPark', 'admin', '$2y$10$tZ2O1sYgN0jM57P8j.8k7eUjYmG0jO57P8j.8k7eUjYmG0jO57P8m', 'admin', NULL, 1),
(2, 'Budi Petugas Gerbang - Shift Pagi', 'petugas', '$2y$10$tZ2O1sYgN0jM57P8j.8k7eUjYmG0jO57P8j.8k7eUjYmG0jO57P8m', 'petugas', 'pagi', 1),
(3, 'H. Rahmat Santoso', 'owner', '$2y$10$tZ2O1sYgN0jM57P8j.8k7eUjYmG0jO57P8j.8k7eUjYmG0jO57P8m', 'owner', NULL, 1),
(4, 'Petugas Gerbang - Shift Malam', 'petugas2', '$2y$10$o1FDAWHZZpnDWz1NzULmwOEARW6XU2xL8NYuKMcVNkXxJTFOoV722', 'petugas', 'malam', 1)
ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`);

INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`) VALUES
(1, 'motor', 2000),
(2, 'mobil', 5000),
(3, 'lainnya', 10000)
ON DUPLICATE KEY UPDATE `tarif_per_jam`=VALUES(`tarif_per_jam`);

INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`) VALUES
(1, 'Area A - Depan Gedung Utama', 50, 0),
(2, 'Area B - Gedung Parkir Basement', 80, 0),
(3, 'Area C - Khusus Sepeda Motor', 150, 0),
(4, 'Area D - Parkir Khusus VIP / Bus', 20, 0)
ON DUPLICATE KEY UPDATE `nama_area`=VALUES(`nama_area`);
