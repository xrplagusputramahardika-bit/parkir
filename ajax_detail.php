<?php
/**
 * AJAX Endpoint - Lookup Active Parking Transaction
 * Smart Parking Management System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query pencarian kosong']);
    exit;
}

// Format query: jika hasil scan QR berupa "TIKET-00004" atau "4" atau plat "B 1234 ABC"
$cleanQuery = str_ireplace(['TIKET-', 'TRX-', '#'], '', $query);
$isNumeric = is_numeric($cleanQuery);

try {
    if ($isNumeric) {
        $stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik, a.nama_area, trf.tarif_per_jam, u.nama_lengkap as nama_petugas_masuk
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            JOIN tb_tarif trf ON t.id_tarif = trf.id_tarif
            JOIN tb_user u ON t.id_user = u.id_user
            WHERE (t.id_parkir = :id OR k.plat_nomor = :plat) AND t.status = 'masuk'
            ORDER BY t.id_parkir DESC LIMIT 1");
        $stmt->execute([':id' => (int)$cleanQuery, ':plat' => $query]);
    } else {
        $stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik, a.nama_area, trf.tarif_per_jam, u.nama_lengkap as nama_petugas_masuk
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            JOIN tb_tarif trf ON t.id_tarif = trf.id_tarif
            JOIN tb_user u ON t.id_user = u.id_user
            WHERE REPLACE(k.plat_nomor, ' ', '') = REPLACE(:plat, ' ', '') AND t.status = 'masuk'
            ORDER BY t.id_parkir DESC LIMIT 1");
        $stmt->execute([':plat' => $query]);
    }

    $trx = $stmt->fetch();

    if (!$trx) {
        // Cek apakah transaksi sudah pernah keluar
        $stmtCek = $pdo->prepare("SELECT t.id_parkir, t.status, k.plat_nomor FROM tb_transaksi t 
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan 
            WHERE (t.id_parkir = :id OR REPLACE(k.plat_nomor, ' ', '') = REPLACE(:plat, ' ', ''))
            ORDER BY t.id_parkir DESC LIMIT 1");
        $stmtCek->execute([':id' => is_numeric($cleanQuery) ? (int)$cleanQuery : 0, ':plat' => $query]);
        $cek = $stmtCek->fetch();

        if ($cek && $cek['status'] === 'keluar') {
            echo json_encode([
                'success' => false,
                'message' => "Kendaraan dengan Plat [{$cek['plat_nomor']}] (Tiket #{$cek['id_parkir']}) sudah selesai/keluar sebelumnya."
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Data transaksi aktif tidak ditemukan untuk kata kunci '{$query}'."
            ]);
        }
        exit;
    }

    // Hitung estimasi durasi dan biaya real-time
    $waktuKeluarSekarang = date('Y-m-d H:i:s');
    $calc = hitung_biaya($trx['waktu_masuk'], $waktuKeluarSekarang, $trx['tarif_per_jam']);

    echo json_encode([
        'success' => true,
        'data' => [
            'id_parkir'          => $trx['id_parkir'],
            'plat_nomor'         => $trx['plat_nomor'],
            'jenis_kendaraan'    => $trx['jenis_kendaraan'],
            'warna'              => $trx['warna'] ?: '-',
            'pemilik'            => $trx['pemilik'] ?: 'Pengunjung Umum',
            'nama_area'          => $trx['nama_area'],
            'tarif_per_jam'      => (float)$trx['tarif_per_jam'],
            'tarif_per_jam_fmt'  => format_rupiah($trx['tarif_per_jam']),
            'waktu_masuk'        => $trx['waktu_masuk'],
            'waktu_masuk_fmt'    => format_tanggal($trx['waktu_masuk']),
            'waktu_keluar'       => $waktuKeluarSekarang,
            'waktu_keluar_fmt'   => format_tanggal($waktuKeluarSekarang),
            'durasi_jam'         => $calc['durasi_jam'],
            'durasi_teks'        => $calc['durasi_teks'],
            'biaya_total'        => $calc['biaya_total'],
            'biaya_total_fmt'    => format_rupiah($calc['biaya_total']),
            'petugas_masuk'      => $trx['nama_petugas_masuk'],
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

