-- Jalankan satu kali pada database yang sudah memiliki tb_transaksi.
-- Slot kendaraan yang masih parkir akan diberi nomor otomatis per area,
-- diurutkan berdasarkan nomor tiket yang lebih dahulu dibuat.
ALTER TABLE `tb_transaksi`
  ADD COLUMN `slot_number` int(11) DEFAULT NULL AFTER `id_area`,
  ADD KEY `tb_transaksi_area_status_slot_index` (`id_area`, `status`, `slot_number`);

UPDATE `tb_transaksi` t
JOIN (
  SELECT `id_parkir`, `id_area`,
         ROW_NUMBER() OVER (PARTITION BY `id_area` ORDER BY `id_parkir`) AS `nomor_slot`
  FROM `tb_transaksi`
  WHERE `status` = 'masuk'
) active ON active.`id_parkir` = t.`id_parkir`
SET t.`slot_number` = active.`nomor_slot`;
