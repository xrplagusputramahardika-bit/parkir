<?php
/**
 * Sidebar Navigation Template
 * Smart Parking Management System
 */
$current_role = $_SESSION['user']['role'] ?? '';
$active = $active_menu ?? 'dashboard';
?>
<aside id="sidebar">
    <a href="<?= base_url('modules/dashboard/index.php') ?>" class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-p-square-fill"></i>
        </div>
        <div>
            <div class="lh-1">SmartPark</div>
            <small class="text-white-50 fs-8 fw-normal">Sistem Parkir Modern</small>
        </div>
    </a>

    <div class="sidebar-nav">
        <!-- Dashboard -->
        <a href="<?= base_url('modules/dashboard/index.php') ?>" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i>
            <span>Dashboard</span>
        </a>
        <!-- Transaksi & Operasional -->
        <div class="sidebar-heading"><?= $current_role === 'owner' ? 'Pemantauan Bisnis' : ($current_role === 'admin' ? 'Operasional & Kontrol' : 'Tugas Operasional') ?></div>

        <?php if ($current_role === 'admin' || $current_role === 'petugas'): ?>
        <a href="<?= base_url('modules/transaksi/masuk.php') ?>" class="nav-link <?= $active === 'transaksi_masuk' ? 'active' : '' ?>">
            <i class="bi bi-box-arrow-in-right text-success"></i>
            <span>Gerbang Masuk</span>
        </a>
        <a href="<?= base_url('modules/transaksi/keluar.php') ?>" class="nav-link <?= $active === 'transaksi_keluar' ? 'active' : '' ?>">
            <i class="bi bi-upc-scan text-info"></i>
            <span>Gerbang Keluar & Scan</span>
        </a>
        <?php endif; ?>

        <a href="<?= base_url('modules/transaksi/index.php') ?>" class="nav-link <?= $active === 'transaksi' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i>
            <span>Riwayat Transaksi</span>
        </a>

        <!-- Master Data -->
        <div class="sidebar-heading"><?= $current_role === 'owner' ? 'Data & Riwayat' : ($current_role === 'admin' ? 'Master Data' : 'Data Kendaraan') ?></div>

        <a href="<?= base_url('modules/kendaraan/index.php') ?>" class="nav-link <?= $active === 'kendaraan' ? 'active' : '' ?>">
            <i class="bi bi-car-front-fill"></i>
            <span>Data Kendaraan</span>
        </a>

        <?php if ($current_role === 'admin'): ?>
        <a href="<?= base_url('modules/area/index.php') ?>" class="nav-link <?= $active === 'area' ? 'active' : '' ?>">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Area Parkir</span>
        </a>

        <a href="<?= base_url('modules/tarif/index.php') ?>" class="nav-link <?= $active === 'tarif' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Tarif Parkir</span>
        </a>

        <a href="<?= base_url('modules/user/index.php') ?>" class="nav-link <?= $active === 'user' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Kelola User</span>
        </a>

        <a href="<?= base_url('modules/backup/index.php') ?>" class="nav-link <?= $active === 'backup' ? 'active' : '' ?>">
            <i class="bi bi-database-fill-gear"></i>
            <span>Backup & Restore</span>
        </a>
        <?php endif; ?>

        <!-- Laporan & Audit -->
        <div class="sidebar-heading"><?= $current_role === 'owner' ? 'Analitik & Audit' : 'Laporan & Audit' ?></div>

        <a href="<?= base_url('modules/laporan/index.php') ?>" class="nav-link <?= $active === 'laporan' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph-fill"></i>
            <span>Laporan Rekap</span>
        </a>

        <?php if ($current_role === 'admin' || $current_role === 'owner'): ?>
        <a href="<?= base_url('modules/log/index.php') ?>" class="nav-link <?= $active === 'log' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>Log Aktivitas</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Sidebar Footer / User Profile summary -->
    <div class="p-3 border-top border-secondary border-opacity-10 d-flex align-items-center gap-2">
        <div class="rounded-circle bg-primary bg-opacity-25 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
            <i class="bi bi-person-fill fs-5"></i>
        </div>
        <div class="overflow-hidden flex-grow-1">
            <div class="text-white text-truncate fw-semibold small"><?= htmlspecialchars($_SESSION['user']['nama_lengkap'] ?? 'User') ?></div>
            <span class="badge badge-role text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($current_role) ?></span>
        </div>
        <a href="<?= base_url('modules/auth/logout.php') ?>" class="text-white-50 hover-white" title="Logout" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right fs-5"></i>
        </a>
    </div>
</aside>
