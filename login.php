<?php
/**
 * Login Page
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';

// Jika sudah login, langsung ke dashboard
if (is_logged_in()) {
    header('Location: ' . base_url('modules/dashboard/index.php'));
    exit;
}

$stmtPublicAreas = $pdo->query("SELECT a.id_area, a.nama_area, a.kapasitas, COUNT(t.id_parkir) AS terisi
    FROM tb_area_parkir a
    LEFT JOIN tb_transaksi t ON t.id_area = a.id_area AND t.status = 'masuk'
    GROUP BY a.id_area, a.nama_area, a.kapasitas
    ORDER BY a.id_area ASC");
$publicAreas = $stmtPublicAreas->fetchAll();
$publicCapacity = 0;
$publicOccupied = 0;

foreach ($publicAreas as $publicArea) {
    $publicCapacity += (int)$publicArea['kapasitas'];
    $publicOccupied += (int)$publicArea['terisi'];
}

$publicAvailable = max(0, $publicCapacity - $publicOccupied);
$publicOccupancy = $publicCapacity > 0 ? round(($publicOccupied / $publicCapacity) * 100, 1) : 0;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tb_user WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            // Cek status aktif
            if ((int)$user['status_aktif'] !== 1) {
                $error = 'Akun Anda dinonaktifkan oleh Administrator.';
            } elseif (password_verify($password, $user['password']) || $password === $user['password']) {
                // Login sukses!
                $_SESSION['user'] = [
                    'id_user'      => $user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'username'     => $user['username'],
                    'role'         => $user['role'],
                ];

                // Catat log login
                log_aktivitas($pdo, $user['id_user'], "User {$user['nama_lengkap']} berhasil login");

                set_flash('success', "Selamat datang kembali, <strong>{$user['nama_lengkap']}</strong>!");
                header('Location: ' . base_url('modules/dashboard/index.php'));
                exit;
            } else {
                $error = 'Password yang Anda masukkan salah!';
            }
        } else {
            $error = 'Username tidak ditemukan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Smart Parking System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
            width: 100%;
            box-sizing: border-box;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #1e293b;
        }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-layout {
            width: calc(100vw - 3rem);
            max-width: 1160px;
            flex: 0 0 calc(100vw - 3rem);
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(360px, 0.8fr);
            align-items: stretch;
            gap: 1.25rem;
            margin: auto;
        }
        .availability-panel {
            position: relative;
            align-self: center;
            padding: clamp(1.25rem, 2.7vw, 2.2rem) 0;
            color: #fff;
        }
        .availability-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #a5b4d0;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .availability-heading {
            max-width: 560px;
            margin: 0.9rem 0 1.35rem;
            color: #fff;
            font-size: clamp(1.6rem, 3vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.045em;
            line-height: 1.12;
        }
        .availability-heading span {
            color: #6ee7b7;
        }
        .availability-live {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            color: #cbd5e1;
            font-size: 0.7rem;
            white-space: nowrap;
        }
        .availability-summary {
            display: flex;
            align-items: center;
            gap: clamp(1rem, 3vw, 2.25rem);
            margin: 1.75rem 0;
            padding: 1.3rem 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            background: linear-gradient(115deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.035));
        }
        .availability-metric {
            min-width: 0;
        }
        .availability-metric span {
            display: block;
            color: #aab8ce;
            font-size: 0.72rem;
        }
        .availability-metric strong {
            display: block;
            margin-top: 0.25rem;
            color: #fff;
            font-size: clamp(1.1rem, 2.2vw, 1.55rem);
            letter-spacing: -0.03em;
        }
        .availability-metric:first-child {
            flex: 1;
        }
        .availability-metric:first-child strong {
            color: #6ee7b7;
            font-size: clamp(2.3rem, 5vw, 3.35rem);
            line-height: 1;
        }
        .availability-metric:first-child strong small {
            color: #d6e2f0;
        }
        .availability-metric + .availability-metric {
            min-width: 100px;
            padding-left: clamp(0.8rem, 2vw, 1.6rem);
            border-left: 1px solid rgba(255, 255, 255, 0.14);
        }
        .availability-section-heading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            margin: 0 0 0.7rem;
            color: #fff;
        }
        .availability-section-heading h3 {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
        }
        .availability-section-heading span {
            color: #94a3b8;
            font-size: 0.72rem;
        }
        .availability-area-list {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.97);
            color: #27364d;
        }
        .availability-area {
            --area-color: #10b981;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(110px, 0.55fr) auto auto;
            align-items: center;
            gap: 0.85rem;
            min-height: 76px;
            padding: 0.8rem 1.1rem;
            border-bottom: 1px solid #e9edf3;
        }
        .availability-area:last-child {
            border-bottom: 0;
        }
        .availability-area-main {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
        }
        .availability-area-marker {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 11px;
            background: color-mix(in srgb, var(--area-color) 12%, white);
            color: var(--area-color);
            font-size: 0.78rem;
            font-weight: 800;
        }
        .availability-area-name {
            overflow: hidden;
            color: #27364d;
            font-size: 0.8rem;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .availability-area-detail {
            margin-top: 0.12rem;
            color: #8491a3;
            font-size: 0.68rem;
        }
        .availability-area-occupancy {
            min-width: 0;
        }
        .availability-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.3rem;
            color: #718096;
            font-size: 0.65rem;
        }
        .availability-slots {
            min-width: 74px;
            color: #17243b;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.1;
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .availability-slots span {
            display: block;
            margin-top: 0.2rem;
            color: #64748b;
            font-size: 0.64rem;
            font-weight: 500;
        }
        .availability-status {
            flex: 0 0 auto;
            padding: 0.3rem 0.65rem;
            border-radius: 999px;
            background: #e7f8f1;
            color: #087f5b;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .availability-status.is-warning {
            background: #fff4dc;
            color: #a15c00;
        }
        .availability-status.is-full {
            background: #feeceb;
            color: #c2352b;
        }
        .availability-progress {
            height: 6px;
            overflow: hidden;
            border-radius: 999px;
            background: #f1f4f8;
        }
        .availability-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--area-color);
        }
        .availability-empty {
            padding: 1.25rem;
            color: #64748b;
            text-align: center;
        }
        .login-card {
            max-width: none;
            align-self: center;
        }
        .login-header {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: #ffffff;
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
            position: relative;
        }
        .brand-badge {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
        }
        .btn-submit {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            font-weight: 700;
            padding: 0.8rem;
            border-radius: 12px;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            opacity: 0.95;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
        }
        .demo-chip {
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 10px;
            font-size: 0.8rem;
            padding: 0.4rem 0.75rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .demo-chip:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        @media (max-width: 900px) {
            body {
                align-items: flex-start;
            }
            .login-layout {
                width: calc(100vw - 3rem);
                max-width: 620px;
                flex-basis: calc(100vw - 3rem);
                grid-template-columns: 1fr;
            }
            .login-card {
                order: 0;
            }
            .availability-panel {
                order: -1;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 0.85rem;
            }
            .login-layout {
                width: 100%;
                flex-basis: 100%;
            }
            .login-card {
                border-radius: 18px;
            }
            .availability-summary {
                gap: 0.5rem;
                margin-block: 1.25rem;
                padding: 0.8rem;
            }
            .availability-metric + .availability-metric {
                min-width: 62px;
                padding-left: 0.5rem;
            }
            .availability-metric span {
                font-size: 0.62rem;
            }
            .availability-metric:first-child strong {
                font-size: 2.35rem;
            }
            .availability-area {
                grid-template-columns: minmax(0, 1fr) auto auto;
                gap: 0.5rem;
                padding: 0.75rem;
            }
            .availability-area-occupancy {
                grid-column: 1 / -1;
                grid-row: 2;
            }
            .availability-area-main {
                gap: 0.5rem;
            }
            .availability-area-marker {
                flex-basis: 30px;
                width: 30px;
                height: 30px;
            }
            .availability-status {
                padding-inline: 0.5rem;
                font-size: 0.63rem;
            }
            .availability-slots {
                min-width: 54px;
                font-size: 1.05rem;
            }
            .availability-slots span {
                font-size: 0.59rem;
            }
            .availability-live {
                font-size: 0.62rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-layout">
        <section class="availability-panel" aria-labelledby="availabilityTitle">
            <div class="availability-eyebrow"><span class="pulse-dot"></span> Informasi parkir publik</div>
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2 mt-2">
                <div>
                    <h2 id="availabilityTitle" class="availability-heading">Parkir lebih mudah,<br><span>cek ruang tersedia.</span></h2>
                </div>
                <span class="availability-live">
                    <i class="bi bi-arrow-clockwise"></i> Diperbarui saat halaman dibuka
                </span>
            </div>

            <div class="availability-summary">
                <div class="availability-metric">
                    <span>SLOT TERSEDIA SEKARANG</span>
                    <strong><?= number_format($publicAvailable) ?> <small class="fs-6 fw-normal">slot</small></strong>
                </div>
                <div class="availability-metric">
                    <span>Kapasitas total</span>
                    <strong><?= number_format($publicCapacity) ?> <small class="fs-6 fw-normal">slot</small></strong>
                </div>
                <div class="availability-metric">
                    <span>Okupansi</span>
                    <strong><?= $publicOccupancy ?>%</strong>
                </div>
            </div>

            <div class="availability-section-heading">
                <h3>Ketersediaan per area</h3>
                <span><?= count($publicAreas) ?> area parkir</span>
            </div>
            <div class="availability-area-list">
                <?php if (empty($publicAreas)): ?>
                <div class="availability-empty">
                    <i class="bi bi-info-circle me-1"></i> Informasi area parkir belum tersedia.
                </div>
                <?php else: ?>
                    <?php foreach ($publicAreas as $area):
                        $capacity = (int)$area['kapasitas'];
                        $occupied = (int)$area['terisi'];
                        $available = max(0, $capacity - $occupied);
                        $percent = $capacity > 0 ? min(100, round(($occupied / $capacity) * 100, 1)) : 0;
                        $areaColor = $available <= 0 ? '#ef4444' : ($percent >= 90 ? '#ef4444' : ($percent >= 70 ? '#f59e0b' : '#10b981'));
                        $status = $available <= 0 ? 'Penuh' : ($percent >= 90 ? 'Hampir penuh' : ($percent >= 70 ? 'Mulai padat' : 'Tersedia'));
                        $statusClass = $available <= 0 ? 'is-full' : ($percent >= 70 ? 'is-warning' : '');
                        $areaInitial = preg_match('/\bArea\s+([A-Z0-9]+)/i', $area['nama_area'], $matches) ? strtoupper($matches[1]) : strtoupper(substr($area['nama_area'], 0, 1));
                    ?>
                    <article class="availability-area" style="--area-color: <?= $areaColor ?>;">
                        <div class="availability-area-main">
                            <span class="availability-area-marker"><?= htmlspecialchars($areaInitial, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="min-w-0">
                                <div class="availability-area-name" title="<?= htmlspecialchars($area['nama_area'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($area['nama_area'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="availability-area-detail">Terisi <?= number_format($occupied) ?> dari <?= number_format($capacity) ?> slot</div>
                            </div>
                        </div>
                        <div class="availability-area-occupancy">
                            <div class="availability-progress" role="progressbar" aria-label="Okupansi <?= htmlspecialchars($area['nama_area'], ENT_QUOTES, 'UTF-8') ?>" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                <span style="width: <?= $percent ?>%;"></span>
                            </div>
                            <div class="availability-meta">
                                <span><?= $percent ?>% terpakai</span>
                                <span><?= number_format($occupied) ?> / <?= number_format($capacity) ?> terisi</span>
                            </div>
                        </div>
                        <div class="availability-slots" aria-label="<?= number_format($available) ?> slot tersedia di <?= htmlspecialchars($area['nama_area'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= number_format($available) ?>
                            <span>slot kosong</span>
                        </div>
                        <span class="availability-status <?= $statusClass ?>"><?= $status ?></span>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="login-card" aria-label="Masuk ke SmartPark">
            <div class="login-header">
                <div class="brand-badge">
                    <i class="bi bi-p-square-fill"></i>
                </div>
                <h3 class="fw-bold mb-1">SmartPark System</h3>
                <p class="mb-0 text-white-50 small">Sistem Manajemen & Gate Parkir Cerdas</p>
            </div>

            <div class="p-4 p-sm-4">
            <?php 
            $flash = get_flash();
            if ($flash): 
            ?>
            <div class="alert alert-<?= $flash['type'] ?> rounded-3 small py-2 d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-info-circle-fill"></i>
                <div><?= $flash['message'] ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger rounded-3 small py-2 d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="username" id="inputUsername" class="form-control rounded-end-3" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold small text-secondary mb-0">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" name="password" id="inputPassword" class="form-control rounded-end-3" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
                </button>
            </form>

            <div class="mt-4 pt-3 border-top">
                <small class="text-muted d-block mb-2 fw-semibold text-center"><i class="bi bi-magic me-1"></i> Akun Cepat (Quick Demo Login):</small>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <span class="demo-chip" onclick="fillLogin('admin', 'admin123')">
                        <i class="bi bi-shield-lock text-primary"></i> <strong>Admin</strong>
                    </span>
                    <span class="demo-chip" onclick="fillLogin('petugas', 'petugas123')">
                        <i class="bi bi-person-badge text-success"></i> <strong>Petugas</strong>
                    </span>
                    <span class="demo-chip" onclick="fillLogin('petugas2', 'petugas2123')">
                        <i class="bi bi-person-badge text-info"></i> <strong>Petugas 2 (Shift Malam)</strong>
                    </span>
                    <span class="demo-chip" onclick="fillLogin('owner', 'owner123')">
                        <i class="bi bi-briefcase text-warning"></i> <strong>Owner</strong>
                    </span>
                </div>
            </div>
            </div>
        </section>
    </div>

    <script>
        function fillLogin(u, p) {
            document.getElementById('inputUsername').value = u;
            document.getElementById('inputPassword').value = p;
        }
    </script>
</body>
</html>
