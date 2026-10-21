<?php
/**
 * Helper Functions & Application Utilities
 * Smart Parking Management System
 */

// Start Output Buffering to prevent 'headers already sent' issues
if (ob_get_level() == 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mendapatkan base URL aplikasi
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Auto-detect project folder path relative to DocumentRoot
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs'));
    $appRoot = str_replace('\\', '/', dirname(__DIR__));
    $relative = trim(str_replace($docRoot, '', $appRoot), '/');
    
    $base = $protocol . $host . ($relative ? '/' . $relative : '/tempat_parkir');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect aman (mencegah error header already sent)
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    } else {
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        exit;
    }
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id_user']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Proteksi halaman login (redirect jika belum login)
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses sistem.');
        redirect(base_url('modules/auth/login.php'));
    }
}

/**
 * Proteksi akses berdasarkan role
 */
function check_role($allowed_roles = []) {
    require_login();
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, (array)$allowed_roles)) {
        set_flash('danger', 'Akses ditolak! Anda tidak memiliki izin untuk halaman ini.');
        redirect(base_url('modules/dashboard/index.php'));
    }
}

/**
 * Format angka ke mata uang Rupiah
 */
function format_rupiah($angka) {
    if ($angka === null || $angka === '') return 'Rp 0';
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format tanggal dalam bahasa Indonesia
 */
function format_tanggal($datetime, $with_time = true) {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $tgl = date('d', $timestamp);
    $bln = $bulanIndo[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);
    
    if ($with_time) {
        $jam = date('H:i:s', $timestamp);
        return "$tgl $bln $thn, $jam WIB";
    }
    return "$tgl $bln $thn";
}

/**
 * Pencatatan riwayat aktivitas ke tabel tb_log_aktivitas
 */
function log_aktivitas($pdo, $id_user, $aktivitas) {
    try {
        $stmt = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (:user, :act, NOW())");
        $stmt->execute([
            ':user' => $id_user,
            ':act'  => substr($aktivitas, 0, 100)
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Gagal log aktivitas: " . $e->getMessage());
        return false;
    }
}

/**
 * Sinkronisasi jumlah kendaraan terisi di area parkir
 */
function sync_area_terisi($pdo, $id_area = null) {
    try {
        if ($id_area) {
            $stmt = $pdo->prepare("UPDATE tb_area_parkir a 
                SET terisi = (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk')
                WHERE a.id_area = :area");
            $stmt->execute([':area' => $id_area]);
        } else {
            $pdo->query("UPDATE tb_area_parkir a 
                SET terisi = (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk')");
        }
        return true;
    } catch (PDOException $e) {
        error_log("Gagal sinkron area: " . $e->getMessage());
        return false;
    }
}

/**
 * Hitung durasi jam dan total biaya parkir
 * Menggunakan standar pembulatan parkir ke atas (misal 1 jam 5 menit dihitung 2 jam, minimal 1 jam)
 */
function hitung_biaya($waktu_masuk, $waktu_keluar = null, $tarif_per_jam = 0) {
    $tMasuk = strtotime($waktu_masuk);
    $tKeluar = $waktu_keluar ? strtotime($waktu_keluar) : time();
    
    $selisihDetik = max(0, $tKeluar - $tMasuk);
    
    $jamPenuh = floor($selisihDetik / 3600);
    $sisaDetik = $selisihDetik % 3600;
    $menit = floor($sisaDetik / 60);
    $detik = $sisaDetik % 60;
    
    // Aturan pembulatan durasi (jam)
    // Jika lewat 0 menit sekalipun, dihitung minimal 1 jam
    // Jika 1 jam 1 menit, dibulatkan ke 2 jam
    if ($selisihDetik <= 0) {
        $durasiJam = 1;
    } else {
        $durasiJam = (int)ceil($selisihDetik / 3600);
        if ($durasiJam < 1) $durasiJam = 1;
    }
    
    $biayaTotal = $durasiJam * (float)$tarif_per_jam;
    
    return [
        'durasi_jam'     => $durasiJam,
        'biaya_total'    => $biayaTotal,
        'selisih_detik'  => $selisihDetik,
        'jam_aktual'     => $jamPenuh,
        'menit_aktual'   => $menit,
        'detik_aktual'   => $detik,
        'durasi_teks'    => "{$jamPenuh} jam {$menit} mnt"
    ];
}

/**
 * Set flash message (notifikasi)
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Ambil dan bersihkan flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize input data
 */
function clean($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}
