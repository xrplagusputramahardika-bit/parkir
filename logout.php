<?php
/**
 * Logout Handler
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';

if (is_logged_in()) {
    $user = current_user();
    log_aktivitas($pdo, $user['id_user'], "User {$user['nama_lengkap']} berhasil logout");
    unset($_SESSION['user']);
    session_destroy();
}

session_start();
set_flash('info', 'Anda telah berhasil logout dari sistem.');
header('Location: ' . base_url('modules/auth/login.php'));
exit;

