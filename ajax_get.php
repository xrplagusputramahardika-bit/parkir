<?php
/**
 * AJAX Endpoint - Lookup Vehicle by License Plate
 * Smart Parking Management System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$plat = trim($_GET['plat'] ?? '');
if (empty($plat)) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tb_kendaraan WHERE REPLACE(plat_nomor, ' ', '') = REPLACE(:plat, ' ', '') LIMIT 1");
    $stmt->execute([':plat' => $plat]);
    $k = $stmt->fetch();

    if ($k) {
        // Cek juga apakah kendaraan ini saat ini sedang parkir (status = 'masuk')
        $stmtCek = $pdo->prepare("SELECT id_parkir FROM tb_transaksi WHERE id_kendaraan = :idk AND status = 'masuk' LIMIT 1");
        $stmtCek->execute([':idk' => $k['id_kendaraan']]);
        $isParked = $stmtCek->fetch();

        echo json_encode([
            'success'   => true,
            'data'      => $k,
            'is_parked' => $isParked ? true : false,
            'id_parkir' => $isParked ? $isParked['id_parkir'] : null
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

