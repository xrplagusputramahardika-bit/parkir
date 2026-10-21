<?php
// Seeder for Smart Parking System
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db   = 'parkiran';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[OK] Connected to database '$db'\n";

    // 1. Seed Users (password: username123)
    $users = [
        [
            'id' => 1,
            'nama' => 'Administrator Parkir',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'shift' => null,
            'status' => 1
        ],
        [
            'id' => 2,
            'nama' => 'Budi Santoso (Petugas Lapangan)',
            'username' => 'petugas',
            'password' => password_hash('petugas123', PASSWORD_DEFAULT),
            'role' => 'petugas',
            'shift' => 'pagi',
            'status' => 1
        ],
        [
            'id' => 4,
            'nama' => 'Petugas Gerbang (Shift Malam)',
            'username' => 'petugas2',
            'password' => password_hash('petugas2123', PASSWORD_DEFAULT),
            'role' => 'petugas',
            'shift' => 'malam',
            'status' => 1
        ],
        [
            'id' => 3,
            'nama' => 'Ir. Hendra Gunawan (Owner/Manajemen)',
            'username' => 'owner',
            'password' => password_hash('owner123', PASSWORD_DEFAULT),
            'role' => 'owner',
            'shift' => null,
            'status' => 1
        ],
    ];

    $stmtUser = $pdo->prepare("INSERT INTO tb_user (id_user, nama_lengkap, username, password, role, shift_kerja, status_aktif) 
        VALUES (:id, :nama, :username, :password, :role, :shift, :status) 
        ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), password = VALUES(password), role = VALUES(role), shift_kerja = VALUES(shift_kerja), status_aktif = VALUES(status_aktif)");
    
    foreach ($users as $u) {
        $stmtUser->execute([
            ':id' => $u['id'],
            ':nama' => $u['nama'],
            ':username' => $u['username'],
            ':password' => $u['password'],
            ':role' => $u['role'],
            ':shift' => $u['shift'],
            ':status' => $u['status']
        ]);
    }
    echo "[OK] Users seeded (admin / admin123, petugas / petugas123, petugas2 / petugas2123, owner / owner123)\n";

    // 2. Seed Tarif
    $tarif = [
        ['id' => 1, 'jenis' => 'motor', 'tarif' => 2000],
        ['id' => 2, 'jenis' => 'mobil', 'tarif' => 5000],
        ['id' => 3, 'jenis' => 'lainnya', 'tarif' => 10000],
    ];
    $stmtTarif = $pdo->prepare("INSERT INTO tb_tarif (id_tarif, jenis_kendaraan, tarif_per_jam)
        VALUES (:id, :jenis, :tarif)
        ON DUPLICATE KEY UPDATE jenis_kendaraan = VALUES(jenis_kendaraan), tarif_per_jam = VALUES(tarif_per_jam)");
    foreach ($tarif as $t) {
        $stmtTarif->execute([':id' => $t['id'], ':jenis' => $t['jenis'], ':tarif' => $t['tarif']]);
    }
    echo "[OK] Tarif seeded\n";

    // 3. Seed Area Parkir
    $areas = [
        ['id' => 1, 'nama' => 'Area A - Lobby Depan', 'kapasitas' => 40, 'terisi' => 0],
        ['id' => 2, 'nama' => 'Area B - Basement Mobil', 'kapasitas' => 60, 'terisi' => 0],
        ['id' => 3, 'nama' => 'Area C - Khusus Roda Dua', 'kapasitas' => 100, 'terisi' => 0],
        ['id' => 4, 'nama' => 'Area D - Parkir VIP / Khusus', 'kapasitas' => 15, 'terisi' => 0],
    ];
    $stmtArea = $pdo->prepare("INSERT INTO tb_area_parkir (id_area, nama_area, kapasitas, terisi)
        VALUES (:id, :nama, :kapasitas, :terisi)
        ON DUPLICATE KEY UPDATE nama_area = VALUES(nama_area), kapasitas = VALUES(kapasitas)");
    foreach ($areas as $a) {
        $stmtArea->execute([':id' => $a['id'], ':nama' => $a['nama'], ':kapasitas' => $a['kapasitas'], ':terisi' => $a['terisi']]);
    }
    echo "[OK] Area Parkir seeded\n";

    // 4. Seed Kendaraan
    $kendaraan = [
        ['id' => 1, 'plat' => 'B 1234 ABC', 'jenis' => 'motor', 'warna' => 'Hitam', 'pemilik' => 'Ahmad Fajar', 'user' => 1],
        ['id' => 2, 'plat' => 'B 5678 DEF', 'jenis' => 'mobil', 'warna' => 'Putih', 'pemilik' => 'Dewi Lestari', 'user' => 1],
        ['id' => 3, 'plat' => 'D 9999 BOS', 'jenis' => 'mobil', 'warna' => 'Hitam Metalik', 'pemilik' => 'Hendra Setiawan', 'user' => 2],
        ['id' => 4, 'plat' => 'B 3321 XYZ', 'jenis' => 'motor', 'warna' => 'Merah', 'pemilik' => 'Rian Pratama', 'user' => 2],
        ['id' => 5, 'plat' => 'B 8877 TRK', 'jenis' => 'lainnya', 'warna' => 'Kuning', 'pemilik' => 'PT Ekspedisi Kilat', 'user' => 1],
    ];
    $stmtK = $pdo->prepare("INSERT INTO tb_kendaraan (id_kendaraan, plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
        VALUES (:id, :plat, :jenis, :warna, :pemilik, :user)
        ON DUPLICATE KEY UPDATE plat_nomor = VALUES(plat_nomor), jenis_kendaraan = VALUES(jenis_kendaraan), warna = VALUES(warna), pemilik = VALUES(pemilik)");
    foreach ($kendaraan as $k) {
        $stmtK->execute([
            ':id' => $k['id'],
            ':plat' => $k['plat'],
            ':jenis' => $k['jenis'],
            ':warna' => $k['warna'],
            ':pemilik' => $k['pemilik'],
            ':user' => $k['user'],
        ]);
    }
    echo "[OK] Kendaraan seeded\n";

    // 5. Seed Transaksi (Sample past completed and active)
    $now = date('Y-m-d H:i:s');
    $trx = [
        // Selesai (keluar)
        [
            'id' => 1,
            'id_k' => 1,
            'masuk' => date('Y-m-d H:i:s', strtotime('-5 hours')),
            'keluar' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'tarif' => 1, // motor (2000)
            'durasi' => 3,
            'biaya' => 6000,
            'status' => 'keluar',
            'user' => 2,
            'area' => 3
        ],
        [
            'id' => 2,
            'id_k' => 2,
            'masuk' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'keluar' => date('Y-m-d H:i:s', strtotime('-1 hours')),
            'tarif' => 2, // mobil (5000)
            'durasi' => 3,
            'biaya' => 15000,
            'status' => 'keluar',
            'user' => 2,
            'area' => 2
        ],
        // Masih parkir (masuk)
        [
            'id' => 3,
            'id_k' => 3,
            'masuk' => date('Y-m-d H:i:s', strtotime('-1 hours 30 minutes')),
            'keluar' => null,
            'tarif' => 2, // mobil
            'durasi' => null,
            'biaya' => null,
            'status' => 'masuk',
            'user' => 2,
            'area' => 1
        ],
        [
            'id' => 4,
            'id_k' => 4,
            'masuk' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
            'keluar' => null,
            'tarif' => 1, // motor
            'durasi' => null,
            'biaya' => null,
            'status' => 'masuk',
            'user' => 2,
            'area' => 3
        ]
    ];

    $stmtTrx = $pdo->prepare("INSERT INTO tb_transaksi (id_parkir, id_kendaraan, waktu_masuk, waktu_keluar, id_tarif, durasi_jam, biaya_total, status, id_user, id_area)
        VALUES (:id, :id_k, :masuk, :keluar, :tarif, :durasi, :biaya, :status, :user, :area)
        ON DUPLICATE KEY UPDATE waktu_masuk=VALUES(waktu_masuk), waktu_keluar=VALUES(waktu_keluar), status=VALUES(status), durasi_jam=VALUES(durasi_jam), biaya_total=VALUES(biaya_total)");
    foreach ($trx as $t) {
        $stmtTrx->execute([
            ':id' => $t['id'],
            ':id_k' => $t['id_k'],
            ':masuk' => $t['masuk'],
            ':keluar' => $t['keluar'],
            ':tarif' => $t['tarif'],
            ':durasi' => $t['durasi'],
            ':biaya' => $t['biaya'],
            ':status' => $t['status'],
            ':user' => $t['user'],
            ':area' => $t['area'],
        ]);
    }
    echo "[OK] Transaksi seeded\n";

    // Update terisi pada area parkir berdasarkan transaksi 'masuk'
    $pdo->query("UPDATE tb_area_parkir a SET terisi = (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk')");
    echo "[OK] Area terisi count synchronized\n";

    // 6. Log aktivitas
    $pdo->query("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES 
        (1, 'Inisialisasi sistem parkir dan pengaturan tarif awal', NOW()),
        (2, 'Kendaraan B 1234 ABC masuk ke Area C', '".date('Y-m-d H:i:s', strtotime('-5 hours'))."'),
        (2, 'Kendaraan B 5678 DEF masuk ke Area B', '".date('Y-m-d H:i:s', strtotime('-4 hours'))."'),
        (2, 'Kendaraan B 1234 ABC keluar - Bayar Rp 6.000', '".date('Y-m-d H:i:s', strtotime('-2 hours'))."'),
        (2, 'Kendaraan B 5678 DEF keluar - Bayar Rp 15.000', '".date('Y-m-d H:i:s', strtotime('-1 hours'))."')
    ");
    echo "[OK] Log aktivitas seeded\n";

    echo "\n=== SEEDING COMPLETED SUCCESSFULLY ===\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
