-- Jalankan satu kali pada database lama sebelum menerapkan kode terbaru.
ALTER TABLE `tb_user`
  ADD COLUMN `shift_kerja` enum('pagi','malam') DEFAULT NULL;

UPDATE `tb_user`
SET `shift_kerja` = 'pagi',
    `nama_lengkap` = 'Budi Petugas Gerbang - Shift Pagi'
WHERE `username` = 'petugas' AND `role` = 'petugas';

INSERT INTO `tb_user` (`nama_lengkap`, `username`, `password`, `role`, `shift_kerja`, `status_aktif`)
SELECT 'Petugas Gerbang - Shift Malam', 'petugas2',
       '$2y$10$o1FDAWHZZpnDWz1NzULmwOEARW6XU2xL8NYuKMcVNkXxJTFOoV722',
       'petugas', 'malam', 1
WHERE NOT EXISTS (SELECT 1 FROM `tb_user` WHERE `username` = 'petugas2');

ALTER TABLE `tb_transaksi`
  ADD COLUMN `id_user_keluar` int(11) DEFAULT NULL,
  ADD KEY `tb_transaksi_id_user_keluar_foreign` (`id_user_keluar`),
  ADD CONSTRAINT `fk_tb_transaksi_kasir_user`
    FOREIGN KEY (`id_user_keluar`) REFERENCES `tb_user` (`id_user`) ON DELETE RESTRICT;
