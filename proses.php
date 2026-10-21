<?php
/**
 * Handler Aksi User Management
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
check_role(['admin']);

$action = $_GET['action'] ?? '';
$currentUser = current_user();

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $shift = trim($_POST['shift_kerja'] ?? '');
    $status = isset($_POST['status_aktif']) ? (int)$_POST['status_aktif'] : 1;
    $shift = $role === 'petugas' && in_array($shift, ['pagi', 'malam'], true) ? $shift : null;

    if (empty($nama) || empty($username) || empty($password) || !in_array($role, ['admin', 'petugas', 'owner'], true)
        || ($role === 'petugas' && $shift === null) || !in_array($status, [0, 1], true)) {
        set_flash('danger', 'Nama, username, password, role, status, dan shift petugas harus valid.');
    } else {
        // Cek duplikasi username
        $stmtCek = $pdo->prepare("SELECT id_user FROM tb_user WHERE username = :u");
        $stmtCek->execute([':u' => $username]);
        if ($stmtCek->fetch()) {
            set_flash('danger', "Username <strong>@{$username}</strong> sudah digunakan. Silakan gunakan username lain.");
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO tb_user (nama_lengkap, username, password, role, shift_kerja, status_aktif) VALUES (:n, :u, :p, :r, :shift, :s)");
            $stmt->execute([
                ':n' => $nama,
                ':u' => $username,
                ':p' => $hash,
                ':r' => $role,
                ':shift' => $shift,
                ':s' => $status
            ]);
            log_aktivitas($pdo, $currentUser['id_user'], "Membuat akun pengguna baru: @{$username} ({$role})");
            set_flash('success', "Akun <strong>@{$username}</strong> berhasil dibuat.");
        }
    }
    header('Location: ' . base_url('modules/user/index.php'));
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_user'] ?? 0);
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $shift = trim($_POST['shift_kerja'] ?? '');
    $status = isset($_POST['status_aktif']) ? (int)$_POST['status_aktif'] : 1;
    $shift = $role === 'petugas' && in_array($shift, ['pagi', 'malam'], true) ? $shift : null;

    if ($id <= 0 || empty($nama) || empty($username) || !in_array($role, ['admin', 'petugas', 'owner'], true)
        || ($role === 'petugas' && $shift === null) || !in_array($status, [0, 1], true)) {
        set_flash('danger', 'Data pengguna tidak valid!');
    } else {
        // Cek duplikasi username untuk user lain
        $stmtCek = $pdo->prepare("SELECT id_user FROM tb_user WHERE username = :u AND id_user != :id");
        $stmtCek->execute([':u' => $username, ':id' => $id]);
        if ($stmtCek->fetch()) {
            set_flash('danger', "Username <strong>@{$username}</strong> sudah dipakai oleh pengguna lain.");
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE tb_user SET nama_lengkap = :n, username = :u, password = :p, role = :r, shift_kerja = :shift, status_aktif = :s WHERE id_user = :id");
                $stmt->execute([':n' => $nama, ':u' => $username, ':p' => $hash, ':r' => $role, ':shift' => $shift, ':s' => $status, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tb_user SET nama_lengkap = :n, username = :u, role = :r, shift_kerja = :shift, status_aktif = :s WHERE id_user = :id");
                $stmt->execute([':n' => $nama, ':u' => $username, ':r' => $role, ':shift' => $shift, ':s' => $status, ':id' => $id]);
            }

            // Jika mengupdate diri sendiri, perbarui data session
            if ($id == $currentUser['id_user']) {
                $_SESSION['user']['nama_lengkap'] = $nama;
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['role'] = $role;
            }

            log_aktivitas($pdo, $currentUser['id_user'], "Mengupdate data pengguna: @{$username}");
            set_flash('success', "Data pengguna <strong>@{$username}</strong> berhasil diperbarui.");
        }
    }
    header('Location: ' . base_url('modules/user/index.php'));
    exit;
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id == $currentUser['id_user']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan!');
    } else {
        $stmtCashier = $pdo->prepare("SELECT COUNT(*) FROM tb_transaksi WHERE id_user_keluar = :id");
        $stmtCashier->execute([':id' => $id]);
        if ((int)$stmtCashier->fetchColumn() > 0) {
            set_flash('danger', 'Akun petugas sudah tercatat sebagai kasir pada transaksi. Nonaktifkan akun agar riwayat pendapatannya tetap tersimpan.');
            header('Location: ' . base_url('modules/user/index.php'));
            exit;
        }

        $stmtGet = $pdo->prepare("SELECT username FROM tb_user WHERE id_user = :id");
        $stmtGet->execute([':id' => $id]);
        $un = $stmtGet->fetchColumn();

        if ($un) {
            $stmt = $pdo->prepare("DELETE FROM tb_user WHERE id_user = :id");
            $stmt->execute([':id' => $id]);
            log_aktivitas($pdo, $currentUser['id_user'], "Menghapus akun pengguna: @{$un}");
            set_flash('success', "Akun @{$un} berhasil dihapus.");
        }
    }
    header('Location: ' . base_url('modules/user/index.php'));
    exit;
}

header('Location: ' . base_url('modules/user/index.php'));
exit;
