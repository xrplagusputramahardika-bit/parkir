<?php
/**
 * Topbar Navigation Template with Voice Announcement PA System
 * Smart Parking Management System
 */
$user = current_user();
?>
<header class="topbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn-toggle-sidebar" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="d-none d-md-flex live-clock-badge">
            <span class="pulse-dot"></span>
            <span id="liveClock">Memuat waktu...</span>
        </div>
        <span class="role-context-badge d-none d-lg-inline-flex">
            <i class="bi <?= $user['role'] === 'owner' ? 'bi-briefcase' : ($user['role'] === 'admin' ? 'bi-shield-check' : 'bi-cone-striped') ?>"></i>
            <?= $user['role'] === 'owner' ? 'Mode Eksekutif' : ($user['role'] === 'admin' ? 'Mode Administrasi' : 'Mode Operasional') ?>
        </span>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($user['role'] === 'admin' || $user['role'] === 'petugas'): ?>
        <!-- Audio Mute / Unmute Toggle Button -->
        <button type="button" id="btnToggleAudioMute" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1" onclick="VoiceAnnouncer.toggleAudio()" title="Aktif / Nonaktifkan Suara Pengumuman">
            <i id="iconAudioMute" class="bi bi-volume-up-fill text-primary"></i>
            <span id="textAudioMute" class="d-none d-sm-inline">Audio Aktif</span>
        </button>

        <!-- Button Buka Pusat Pengumuman Suara (PA System) -->
        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 d-inline-flex align-items-center gap-1 text-dark" data-bs-toggle="modal" data-bs-target="#modalAudioBroadcast" title="Buka Pusat Pengumuman Suara">
            <i class="bi bi-broadcast text-danger"></i>
            <span class="d-none d-md-inline fw-semibold">Pengumuman PA</span>
        </button>
        <?php endif; ?>

        <!-- Quick Action Shortcut Buttons for Staff/Admin -->
        <?php if ($user['role'] === 'admin' || $user['role'] === 'petugas'): ?>
        <a href="<?= base_url('modules/transaksi/masuk.php') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 d-none d-lg-inline-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> <span>Masuk</span>
        </a>
        <a href="<?= base_url('modules/transaksi/keluar.php') ?>" class="btn btn-sm btn-primary-gradient rounded-pill px-3 d-inline-flex align-items-center gap-1">
            <i class="bi bi-qr-code-scan"></i> <span>Scan Keluar</span>
        </a>
        <?php endif; ?>

        <!-- User Dropdown -->
        <div class="dropdown ms-2">
            <button class="btn btn-light rounded-pill border d-flex align-items-center gap-2 py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle role-avatar text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                    <?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="text-start d-none d-md-block pe-1">
                    <div class="fw-semibold small lh-1"><?= htmlspecialchars($user['nama_lengkap'] ?? 'User') ?></div>
                    <small class="text-muted text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($user['role'] ?? '') ?></small>
                </div>
                <i class="bi bi-chevron-down text-muted small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3 mt-2">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold"><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></div>
                    <small class="text-muted">@<?= htmlspecialchars($user['username'] ?? '') ?></small>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= base_url('modules/dashboard/index.php') ?>">
                        <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= base_url('modules/transaksi/index.php') ?>">
                        <i class="bi bi-receipt me-2 text-info"></i> Riwayat Parkir
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= base_url('modules/auth/logout.php') ?>" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<?php if ($user['role'] === 'admin' || $user['role'] === 'petugas'): ?>
<!-- ====================================================================
     MODAL PUSAT PENGUMUMAN AUDIO (PUBLIC ADDRESS PA SYSTEM)
     ==================================================================== -->
<div class="modal fade" id="modalAudioBroadcast" tabindex="-1" aria-labelledby="modalAudioBroadcastLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger p-2 d-flex align-items-center justify-content-center text-white" style="width: 36px; height: 36px;">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="modalAudioBroadcastLabel">Pusat Pengumuman Audio (PA System)</h6>
                        <small class="text-white-50" style="font-size: 0.75rem;">Siarkan pengeras suara otomatis atau manual ke area parkir</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Preset Cepat Pengumuman Populer -->
                <label class="form-label fw-bold small text-secondary mb-2">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Pilih Template Pengumuman Cepat:
                </label>
                <div class="vstack gap-2 mb-4">
                    <button type="button" class="btn btn-outline-primary text-start rounded-3 p-2 small d-flex align-items-center gap-2" onclick="playPresetAnnouncement('keamanan')">
                        <i class="bi bi-shield-lock-fill fs-5 text-primary"></i>
                        <div>
                            <strong class="d-block text-dark">Peringatan Keamanan Kunci Ganda</strong>
                            <span class="text-muted" style="font-size: 0.72rem;">Mengingatkan pengunjung agar mengunci ganda kendaraan dan menjaga barang berharga.</span>
                        </div>
                    </button>

                    <button type="button" class="btn btn-outline-warning text-start rounded-3 p-2 small d-flex align-items-center gap-2" onclick="playPresetAnnouncement('tutup')">
                        <i class="bi bi-clock-history fs-5 text-warning"></i>
                        <div>
                            <strong class="d-block text-dark">Pemberitahuan Jam Tutup (30 Menit)</strong>
                            <span class="text-muted" style="font-size: 0.72rem;">Memberitahu bahwa operasional parkir akan berakhir dalam 30 menit.</span>
                        </div>
                    </button>
                </div>

                <!-- Template Panggilan Pemilik Kendaraan Tertentu -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <label class="form-label fw-bold small text-dark mb-2">
                        <i class="bi bi-megaphone-fill text-danger me-1"></i>Panggilan Pemilik Kendaraan Khusus:
                    </label>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input type="text" id="paPlatNomor" class="form-control form-control-sm font-monospace text-uppercase fw-bold" placeholder="Plat: B 1234 ABC">
                        </div>
                        <div class="col-6">
                            <input type="text" id="paArea" class="form-control form-control-sm" placeholder="Lokasi: Area A">
                        </div>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="paAlasan" class="form-control" placeholder="Keperluan: Menghalangi akses keluar / Lampu menyala">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm w-100 rounded-pill fw-semibold" onclick="playPanggilanKhusus()">
                        <i class="bi bi-volume-up-fill me-1"></i> Siarkan Panggilan Pemilik
                    </button>
                </div>

                <!-- Input Suara Bebas / Kustom -->
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Ketik Teks Pengumuman Kustom:</label>
                    <textarea id="paCustomText" class="form-control rounded-3" rows="2" placeholder="Contoh: Perhatian, mohon kepada seluruh pengunjung..."></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border rounded-pill btn-sm px-3" onclick="VoiceAnnouncer.playChime()">
                        <i class="bi bi-bell-fill me-1 text-primary"></i> Tes Bel Ding-Dong
                    </button>
                    <button type="button" class="btn btn-primary-gradient btn-rounded btn-sm ms-auto px-4" onclick="playCustomAnnouncement()">
                        <i class="bi bi-broadcast me-1"></i> Siarkan Suara
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function playPresetAnnouncement(type) {
    if (type === 'keamanan') {
        VoiceAnnouncer.announceKeamanan();
    } else if (type === 'tutup') {
        VoiceAnnouncer.announceTutup();
    }
}

function playPanggilanKhusus() {
    const plat = document.getElementById('paPlatNomor').value.trim() || 'kendaraan terkait';
    const area = document.getElementById('paArea').value.trim() || 'area parkir';
    const alasan = document.getElementById('paAlasan').value.trim() || 'kendaraan Anda menghalangi jalur';
    VoiceAnnouncer.announcePanggilan(plat, area, alasan);
}

function playCustomAnnouncement() {
    const text = document.getElementById('paCustomText').value.trim();
    if (!text) {
        Swal.fire({ icon: 'info', title: 'Teks Masih Kosong', text: 'Ketik pesan pengumuman suara yang ingin disiarkan.' });
        return;
    }
    VoiceAnnouncer.speak(text);
}
</script>
<?php endif; ?>
