<?php
/**
 * Gerbang Masuk (Check-In Kendaraan)
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
check_role(['admin', 'petugas']);
$user = current_user();

// Selalu sinkronkan kuota awal
sync_area_terisi($pdo);

$error = '';
$successId = null;
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $platNomor = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenisKendaraan = trim($_POST['jenis_kendaraan'] ?? 'motor');
    $warna = trim($_POST['warna'] ?? '');
    $pemilik = trim($_POST['pemilik'] ?? '');
    $idArea = (int)($_POST['id_area'] ?? 0);
    $slotNumber = (int)($_POST['slot_number'] ?? 0);

    if (empty($platNomor)) {
        $error = 'Plat nomor kendaraan wajib diisi!';
    } elseif ($warna === '') {
        $error = 'Warna kendaraan wajib diisi!';
    } elseif ($pemilik === '') {
        $error = 'Nama pemilik kendaraan wajib diisi!';
    } elseif ($idArea <= 0) {
        $error = 'Silakan pilih area parkir yang valid!';
    } elseif ($slotNumber <= 0) {
        $error = 'Silakan pilih slot parkir yang tersedia!';
    } else {
        try {
            $pdo->beginTransaction();

            // Kunci area saat memeriksa slot agar dua check-in tidak memakai slot yang sama.
            $stmtAreaCek = $pdo->prepare("SELECT kapasitas, nama_area FROM tb_area_parkir WHERE id_area = :id FOR UPDATE");
            $stmtAreaCek->execute([':id' => $idArea]);
            $areaData = $stmtAreaCek->fetch();

            if (!$areaData) {
                $error = 'Area parkir tidak ditemukan.';
            } else {
                $stmtOccupied = $pdo->prepare("SELECT slot_number FROM tb_transaksi WHERE id_area = :area AND status = 'masuk' AND slot_number IS NOT NULL");
                $stmtOccupied->execute([':area' => $idArea]);
                $occupiedSlots = array_map('intval', $stmtOccupied->fetchAll(PDO::FETCH_COLUMN));
                $occupiedLookup = array_fill_keys($occupiedSlots, true);

                if ($slotNumber > (int)$areaData['kapasitas'] || isset($occupiedLookup[$slotNumber])) {
                    $error = "Slot {$slotNumber} di area {$areaData['nama_area']} sudah terisi atau tidak tersedia. Silakan pilih slot lain.";
                }
            }

            if (!$error) {
            // Cek apakah kendaraan ini sudah terdaftar di tb_kendaraan
            $stmtK = $pdo->prepare("SELECT * FROM tb_kendaraan WHERE REPLACE(plat_nomor, ' ', '') = REPLACE(:plat, ' ', '') LIMIT 1");
            $stmtK->execute([':plat' => $platNomor]);
            $kendaraan = $stmtK->fetch();

            $idKendaraan = null;

            if ($kendaraan) {
                $idKendaraan = $kendaraan['id_kendaraan'];
                
                // Cek apakah kendaraan ini sedang parkir (belum checkout)
                $stmtCekAktif = $pdo->prepare("SELECT id_parkir FROM tb_transaksi WHERE id_kendaraan = :idk AND status = 'masuk' LIMIT 1");
                $stmtCekAktif->execute([':idk' => $idKendaraan]);
                $parked = $stmtCekAktif->fetch();

                if ($parked) {
                    $error = "Kendaraan dengan plat [{$platNomor}] saat ini SUDAH berada di dalam area parkir (Tiket #{$parked['id_parkir']}).";
                } else {
                    $stmtUpdateK = $pdo->prepare("UPDATE tb_kendaraan SET jenis_kendaraan = :jenis, warna = :warna, pemilik = :pemilik WHERE id_kendaraan = :id");
                    $stmtUpdateK->execute([
                        ':jenis' => $jenisKendaraan,
                        ':warna' => $warna,
                        ':pemilik' => $pemilik,
                        ':id' => $idKendaraan
                    ]);
                }
            } else {
                // Buat data kendaraan baru di tb_kendaraan
                $stmtInsertK = $pdo->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) 
                    VALUES (:plat, :jenis, :warna, :pemilik, :user)");
                $stmtInsertK->execute([
                    ':plat'    => $platNomor,
                    ':jenis'   => $jenisKendaraan,
                    ':warna'   => $warna ?: null,
                    ':pemilik' => $pemilik ?: 'Pengunjung Umum',
                    ':user'    => $user['id_user']
                ]);
                $idKendaraan = $pdo->lastInsertId();
            }

            if (!$error && $idKendaraan) {
                // Ambil tarif sesuai jenis kendaraan
                $stmtT = $pdo->prepare("SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan = :jenis LIMIT 1");
                $stmtT->execute([':jenis' => $jenisKendaraan]);
                $idTarif = $stmtT->fetchColumn();

                if (!$idTarif) {
                    // Fallback tarif pertama jika tidak spesifik
                    $idTarif = 1;
                }

                // Insert transaksi masuk
                $stmtTrx = $pdo->prepare("INSERT INTO tb_transaksi (id_kendaraan, waktu_masuk, waktu_keluar, id_tarif, durasi_jam, biaya_total, status, id_user, id_area, slot_number) 
                    VALUES (:idk, NOW(), NULL, :tarif, NULL, NULL, 'masuk', :user, :area, :slot)");
                $stmtTrx->execute([
                    ':idk'   => $idKendaraan,
                    ':tarif' => $idTarif,
                    ':user'  => $user['id_user'],
                    ':area'  => $idArea,
                    ':slot'  => $slotNumber
                ]);
                $newParkirId = $pdo->lastInsertId();

                $pdo->commit();
                sync_area_terisi($pdo, $idArea);

                // Ambil sisa slot terkini setelah berkurang
                $stmtSisaNow = $pdo->prepare("SELECT nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = :id");
                $stmtSisaNow->execute([':id' => $idArea]);
                $areaNow = $stmtSisaNow->fetch();
                $sisaNow = max(0, $areaNow['kapasitas'] - $areaNow['terisi']);

                // Catat log
                log_aktivitas($pdo, $user['id_user'], "Kendaraan {$platNomor} masuk ke {$areaData['nama_area']} slot {$slotNumber} (Tiket #{$newParkirId})");

                $successId = $newParkirId;
                $successData = [
                    'plat' => $platNomor,
                    'area' => $areaNow['nama_area'],
                    'slot' => $slotNumber,
                    'sisa' => $sisaNow,
                    'kapasitas' => $areaNow['kapasitas']
                ];
                set_flash('success', "Kendaraan <strong>{$platNomor}</strong> berhasil masuk ke <strong>{$areaNow['nama_area']}</strong>, slot <strong>{$slotNumber}</strong>! Slot tersisa di area ini berkurang menjadi <strong>{$sisaNow} slot</strong>.");
            }
            }

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Gagal mencatat slot kendaraan masuk: ' . $exception->getMessage());
            $error = 'Transaksi masuk gagal disimpan. Silakan periksa kembali slot dan data kendaraan.';
        }
    }
}

// Ambil data Area Parkir TERKINI (setelah insert)
$stmtAreas = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");
$areas = $stmtAreas->fetchAll();
$occupiedSlotsByArea = [];
$stmtOccupiedSlots = $pdo->query("SELECT id_area, slot_number FROM tb_transaksi WHERE status = 'masuk' AND slot_number IS NOT NULL");
foreach ($stmtOccupiedSlots->fetchAll() as $occupiedSlot) {
    $occupiedSlotsByArea[(int)$occupiedSlot['id_area']][] = (int)$occupiedSlot['slot_number'];
}
$availableSlotsByArea = [];
$totalSlotTersedia = 0;
foreach ($areas as $area) {
    $areaId = (int)$area['id_area'];
    $capacity = (int)$area['kapasitas'];
    $occupiedLookup = array_fill_keys($occupiedSlotsByArea[$areaId] ?? [], true);
    $availableSlotsByArea[$areaId] = [];
    for ($slot = 1; $slot <= $capacity; $slot++) {
        if (!isset($occupiedLookup[$slot])) {
            $availableSlotsByArea[$areaId][] = $slot;
        }
    }
    $totalSlotTersedia += count($availableSlotsByArea[$areaId]);
}

// Ambil data Tarif
$stmtTarif = $pdo->query("SELECT * FROM tb_tarif");
$tarifs = $stmtTarif->fetchAll();

$page_title = 'Gerbang Masuk Kendaraan';
$active_menu = 'transaksi_masuk';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="gate-console">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-box-arrow-in-right text-primary me-2"></i>Gerbang Masuk</h4>
            <p class="text-muted small mb-0">Isi data pelanggan baru di bawah. Data kendaraan akan tersimpan otomatis saat tiket diterbitkan.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-soft-success"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Jalur aktif</span>
            <a href="<?= base_url('modules/transaksi/index.php') ?>" class="btn btn-light border btn-sm">
                <i class="bi bi-clock-history me-1"></i> Riwayat
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
        <div><?= $error ?></div>
    </div>
    <?php endif; ?>

        <?php if ($successId && $successData): ?>
        <section class="entry-success-banner mb-4">
            <div class="entry-success-icon"><i class="bi bi-check2"></i></div>
            <div class="entry-success-copy">
                <span class="entry-overline">Kendaraan tercatat</span>
                <h2>Check-in berhasil</h2>
                <p><span class="font-monospace fw-bold"><?= htmlspecialchars($successData['plat'], ENT_QUOTES, 'UTF-8') ?></span> · Tiket #<?= str_pad($successId, 5, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($successData['area'], ENT_QUOTES, 'UTF-8') ?> · Slot <?= (int)$successData['slot'] ?></p>
                <small><?= number_format((int)$successData['sisa']) ?> dari <?= number_format((int)$successData['kapasitas']) ?> slot tersisa di area ini</small>
            </div>
            <div class="entry-success-actions">
                <a href="<?= base_url('modules/transaksi/cetak_struk.php?id=' . $successId) ?>" target="_blank" class="btn btn-light btn-rounded px-3" id="autoPrintBtn">
                    <i class="bi bi-printer-fill me-1"></i> Cetak tiket
                </a>
                <button type="button" class="btn btn-outline-light btn-rounded px-3" onclick="VoiceAnnouncer.announceMasuk('<?= addslashes($successData['plat']) ?>', '<?= addslashes($successData['area']) ?>')">
                    <i class="bi bi-volume-up-fill me-1"></i> Pengumuman
                </button>
                <a href="<?= base_url('modules/area/index.php?area_id=' . $idArea . '#daftar-kendaraan-area') ?>" class="btn btn-outline-light btn-rounded px-3">
                    <i class="bi bi-eye-fill me-1"></i> Pantau area
                </a>
                <a href="<?= base_url('modules/transaksi/masuk.php') ?>" class="btn btn-light btn-rounded px-3">
                    <i class="bi bi-plus-lg me-1"></i> Check-in berikutnya
                </a>
            </div>
        </section>
        <script>
            // Buka popup cetak struk otomatis
            window.open('<?= base_url('modules/transaksi/cetak_struk.php?id=' . $successId) ?>', '_blank', 'width=450,height=600');
            // Siarkan pengumuman audio otomatis selamat datang
            setTimeout(() => {
                if (typeof VoiceAnnouncer !== 'undefined') {
                    VoiceAnnouncer.announceMasuk('<?= addslashes($successData['plat']) ?>', '<?= addslashes($successData['area']) ?>');
                }
            }, 600);
        </script>
        <?php endif; ?>

        <form action="" method="POST" id="formMasuk">
            <div class="gate-workflow">
                <section class="gate-form-column card card-custom" aria-labelledby="gateVehicleHeading">
                    <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
                        <h6 class="fw-bold mb-0" id="gateVehicleHeading">Data kendaraan</h6>
                    </div>
                    <div class="gate-form-content p-3 p-md-4 pt-2">
                    <section class="gate-plate-stage">
                        <label class="gate-simple-label" for="platNomor">Nomor plat</label>
                        <div class="gate-license-plate">
                            <input type="text" name="plat_nomor" id="platNomor" placeholder="B 1234 ABC" required autocomplete="off" autofocus aria-label="Nomor plat kendaraan">
                            <button type="button" id="btnToggleCamModal" data-bs-toggle="modal" data-bs-target="#scanCameraModal" title="Pindai dengan kamera">
                                <i class="bi bi-camera"></i><span>Scan</span>
                            </button>
                        </div>
                        <div class="gate-plate-feedback">
                            <span id="plateLookupStatus"></span>
                        </div>
                        <p class="gate-plate-hint mb-0"><i class="bi bi-lightbulb me-1"></i>Plat baru akan didaftarkan otomatis saat tiket diterbitkan.</p>
                    </section>

                    <section class="gate-vehicle-section">
                        <div class="gate-simple-label">Pilih jenis kendaraan</div>
                        <div class="gate-vehicle-picker">
                            <?php foreach ($tarifs as $trf):
                                $icon = $trf['jenis_kendaraan'] === 'motor' ? 'bi-bicycle' : ($trf['jenis_kendaraan'] === 'mobil' ? 'bi-car-front' : 'bi-truck');
                            ?>
                            <label class="gate-vehicle-choice" for="jenis_<?= $trf['jenis_kendaraan'] ?>">
                                <input type="radio" name="jenis_kendaraan" id="jenis_<?= $trf['jenis_kendaraan'] ?>" value="<?= $trf['jenis_kendaraan'] ?>" <?= $trf['jenis_kendaraan'] === 'motor' ? 'checked' : '' ?>>
                                <i class="bi <?= $icon ?>"></i>
                                <span><?= htmlspecialchars(ucfirst($trf['jenis_kendaraan']), ENT_QUOTES, 'UTF-8') ?></span>
                                <small><?= format_rupiah($trf['tarif_per_jam']) ?><em>/jam</em></small>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="gate-extra-details">
                        <div class="gate-extra-heading">
                            <span>Detail pelanggan baru</span>
                            <small>Wajib diisi</small>
                        </div>
                        <div class="gate-extra-fields">
                            <div>
                                <label for="warna">Warna kendaraan <span aria-hidden="true">*</span></label>
                                <input type="text" name="warna" id="warna" class="form-control" placeholder="Contoh: Hitam" autocomplete="off" required>
                            </div>
                            <div class="gate-owner-field">
                                <div class="gate-owner-label">
                                    <label for="pemilik">Nama pemilik / member <span aria-hidden="true">*</span></label>
                                    <button type="button" class="gate-visitor-shortcut" id="btnPengunjungUmum">Pengunjung umum</button>
                                </div>
                                <input type="text" name="pemilik" id="pemilik" class="form-control" placeholder="Nama pelanggan" autocomplete="name" required>
                            </div>
                        </div>
                    </section>
                    </div>
                </section>

                <aside class="gate-area-column card card-custom">
                    <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
                    <div class="gate-area-heading">
                        <strong class="gate-area-title">Area parkir</strong>
                        <div class="gate-total-open"><strong><?= number_format($totalSlotTersedia) ?></strong><span>slot tersedia</span></div>
                    </div>
                    </div>
                    <div class="gate-area-content px-3 px-md-4 pb-3">
                    <div class="gate-area-options">
                        <?php
                        $firstAvailableAreaId = null;
                        foreach ($areas as $area) {
                            if ((int)$area['kapasitas'] > (int)$area['terisi']) {
                                $firstAvailableAreaId = (int)$area['id_area'];
                                break;
                            }
                        }
                        foreach ($areas as $a):
                            $kapasitasArea = (int)$a['kapasitas'];
                            $areaId = (int)$a['id_area'];
                            $availableSlots = $availableSlotsByArea[$areaId] ?? [];
                            $occupiedSlots = $occupiedSlotsByArea[$areaId] ?? [];
                            $terisiArea = $kapasitasArea - count($availableSlots);
                            $sisa = max(0, $kapasitasArea - $terisiArea);
                            $pct = $kapasitasArea > 0 ? min(100, round(($terisiArea / $kapasitasArea) * 100)) : 0;
                            $isFull = $sisa <= 0;
                            $toneArea = $isFull ? 'full' : ($pct >= 70 ? 'busy' : 'open');
                        ?>
                        <label class="gate-area-option <?= $isFull ? 'is-full' : '' ?>" for="area_<?= $areaId ?>">
                            <input type="radio" name="id_area" id="area_<?= $areaId ?>" value="<?= $areaId ?>" data-capacity="<?= $kapasitasArea ?>" data-occupied-slots="<?= htmlspecialchars(json_encode($occupiedSlots), ENT_QUOTES, 'UTF-8') ?>" <?= $isFull ? 'disabled' : 'required' ?> <?= $areaId === $firstAvailableAreaId ? 'checked' : '' ?>>
                            <span class="gate-area-radio"></span>
                            <span class="gate-area-copy">
                                <strong><?= htmlspecialchars($a['nama_area'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= number_format($terisiArea) ?> terisi · <?= $pct ?>% kapasitas</small>
                            </span>
                            <span class="gate-area-availability">
                                <strong><?= number_format($sisa) ?></strong><small>kosong</small>
                            </span>
                            <span class="gate-area-progress"><i class="<?= $toneArea ?>" style="width: <?= $pct ?>%"></i></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($areas)): ?>
                        <div class="gate-no-area"><i class="bi bi-exclamation-circle"></i><span>Belum ada area parkir yang tersedia.</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label fw-semibold small mb-0">Pilih slot parkir</label>
                            <small class="text-muted" id="parkingSlotHint">Pilih area untuk melihat slot.</small>
                        </div>
                        <input type="hidden" id="parkingSlot" name="slot_number" value="">
                        <div class="gate-slot-map" id="parkingSlotMap" aria-live="polite">
                            <span class="text-muted small">Tekan salah satu area di atas untuk menampilkan denah slot.</span>
                        </div>
                        <div class="gate-slot-legend mt-2">
                            <span><i class="is-free"></i>Kosong</span>
                            <span><i class="is-occupied"></i>Terisi</span>
                            <span><i class="is-selected"></i>Dipilih</span>
                        </div>
                    </div>
                    <div class="gate-area-note"><i class="bi bi-info-circle"></i><span>Tarif dibulatkan per jam dengan minimum satu jam. Tiket diperlukan saat keluar.</span></div>
                    </div>
                </aside>
                <div class="gate-form-footer">
                    <button type="submit" class="btn btn-primary-gradient btn-rounded px-4">
                        <i class="bi bi-ticket-perforated me-1"></i> Terbitkan tiket
                    </button>
                </div>
            </div>
        </form>
    </div>

<!-- Modal Scan Kamera Plat / QR -->
<div class="modal fade" id="scanCameraModal" tabindex="-1" aria-labelledby="scanCameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="scanCameraModalLabel"><i class="bi bi-camera-fill text-primary me-2"></i>Pindai Barcode / Plat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopCameraScanner()"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small">Arahkan kamera ke barcode kartu atau tiket kendaraan.</p>
                <div class="scanner-container mb-3" style="min-height: 250px;">
                    <div id="readerMasuk"></div>
                </div>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="btnToggleCamera" onclick="startScannerMasuk()">
                        <i class="bi bi-camera-video me-1"></i> Mulai Kamera
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal" onclick="stopCameraScanner()">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/scanner.js') ?>"></script>
<style>
    .gate-slot-map { display: grid; grid-template-columns: repeat(auto-fill, minmax(48px, 1fr)); gap: .5rem; max-height: 250px; overflow-y: auto; padding: .25rem; }
    .gate-slot-button { min-height: 42px; border: 1px solid #a7f3d0; border-radius: .6rem; background: #ecfdf5; color: #047857; font-size: .8rem; font-weight: 700; transition: .15s ease; }
    .gate-slot-button:hover:not(:disabled), .gate-slot-button.is-selected { border-color: #4361ee; background: #4361ee; color: #fff; }
    .gate-slot-button:disabled { cursor: not-allowed; border-color: #fecaca; background: #fef2f2; color: #b91c1c; opacity: .9; }
    .gate-slot-legend { display: flex; flex-wrap: wrap; gap: .8rem; color: #64748b; font-size: .7rem; }
    .gate-slot-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .gate-slot-legend i { width: .65rem; height: .65rem; border-radius: .2rem; background: #10b981; }
    .gate-slot-legend i.is-occupied { background: #ef4444; }
    .gate-slot-legend i.is-selected { background: #4361ee; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const platInput = document.getElementById('platNomor');
    const statusEl = document.getElementById('plateLookupStatus');
    const ownerInput = document.getElementById('pemilik');
    let typingTimer;

    document.getElementById('btnPengunjungUmum').addEventListener('click', function () {
        ownerInput.value = 'Pengunjung Umum';
        ownerInput.focus();
    });

    // Autocomplete & Check jika plat sudah terdaftar
    platInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
        clearTimeout(typingTimer);
        const plat = this.value.trim();

        if (plat.length < 3) {
            statusEl.innerHTML = '';
            return;
        }

        typingTimer = setTimeout(function () {
            statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Memeriksa data kendaraan...</span>';
            fetch('<?= base_url('modules/kendaraan/ajax_get.php?plat=') ?>' + encodeURIComponent(plat))
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        const d = res.data;
                        document.getElementById('warna').value = d.warna || '';
                        document.getElementById('pemilik').value = d.pemilik || '';
                        
                        const radioJenis = document.getElementById('jenis_' + d.jenis_kendaraan);
                        if (radioJenis) radioJenis.checked = true;

                        if (res.is_parked) {
                            statusEl.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> PERINGATAN: Kendaraan ini sedang parkir (Tiket #${res.id_parkir})!</span>`;
                        } else {
                            statusEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill"></i> Kendaraan terdaftar milik <strong>${d.pemilik || 'Anggota'}</strong></span>`;
                        }
                    } else {
                        statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle"></i> Kendaraan baru / belum terdaftar (Akan otomatis disimpan).</span>';
                    }
                })
                .catch(() => {
                    statusEl.innerHTML = '';
                });
        }, 400);
    });

    const firstAreaOption = document.querySelector('.gate-area-option input[name="id_area"]:not(:disabled)');
    const selectedAreaOption = document.querySelector('.gate-area-option input[name="id_area"]:checked');
    if (firstAreaOption && !selectedAreaOption) {
        firstAreaOption.checked = true;
    }

    const slotInput = document.getElementById('parkingSlot');
    const slotMap = document.getElementById('parkingSlotMap');
    const slotHint = document.getElementById('parkingSlotHint');
    const areaOptions = document.querySelectorAll('.gate-area-option input[name="id_area"]');
    function updateAvailableSlots(areaOption) {
        slotInput.value = '';
        slotMap.innerHTML = '';
        if (!areaOption) {
            slotHint.textContent = 'Pilih area untuk melihat slot.';
            slotMap.innerHTML = '<span class="text-muted small">Tekan salah satu area di atas untuk menampilkan denah slot.</span>';
            return;
        }

        const capacity = Number(areaOption.dataset.capacity || 0);
        const occupiedSlots = JSON.parse(areaOption.dataset.occupiedSlots || '[]').map(Number);
        const occupiedLookup = new Set(occupiedSlots);
        if (capacity <= 0) {
            slotHint.textContent = 'Area ini belum memiliki slot.';
            slotMap.innerHTML = '<span class="text-muted small">Tidak ada slot pada area ini.</span>';
            return;
        }

        if (occupiedSlots.length >= capacity) {
            slotHint.textContent = 'Pilih area lain yang masih memiliki slot kosong.';
        } else {
            slotHint.textContent = (capacity - occupiedSlots.length) + ' slot kosong. Tekan salah satu slot hijau untuk memilih.';
        }

        for (let slotNumber = 1; slotNumber <= capacity; slotNumber++) {
            const occupied = occupiedLookup.has(slotNumber);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'gate-slot-button' + (occupied ? ' is-occupied' : '');
            button.textContent = String(slotNumber).padStart(2, '0');
            button.title = 'Slot ' + String(slotNumber).padStart(2, '0') + (occupied ? ' - Terisi' : ' - Kosong');
            button.setAttribute('aria-label', button.title);
            button.disabled = occupied;
            button.addEventListener('click', function () {
                slotMap.querySelectorAll('.gate-slot-button.is-selected').forEach(function (selected) {
                    selected.classList.remove('is-selected');
                    selected.setAttribute('aria-pressed', 'false');
                });
                button.classList.add('is-selected');
                button.setAttribute('aria-pressed', 'true');
                slotInput.value = String(slotNumber);
                slotHint.textContent = 'Slot ' + String(slotNumber).padStart(2, '0') + ' dipilih.';
            });
            slotMap.appendChild(button);
        }

        if (capacity - occupiedSlots.length <= 0) {
            slotInput.value = '';
            return;
        }
    }
    areaOptions.forEach(function (option) {
        option.addEventListener('change', function () {
            if (this.checked) updateAvailableSlots(this);
        });
    });
    const initialArea = document.querySelector('.gate-area-option input[name="id_area"]:checked');
    if (initialArea) updateAvailableSlots(initialArea);

    document.getElementById('formMasuk').addEventListener('submit', function (event) {
        if (!slotInput.value) {
            event.preventDefault();
            slotHint.textContent = 'Tekan salah satu slot hijau untuk memilih slot parkir.';
            slotMap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});

function startScannerMasuk() {
    initCameraScanner('readerMasuk', function(decodedText) {
        document.getElementById('platNomor').value = decodedText.trim().toUpperCase();
        // Trigger event input
        document.getElementById('platNomor').dispatchEvent(new Event('input'));
        // Tutup modal
        const modalEl = document.getElementById('scanCameraModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        stopCameraScanner();
    });
}

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
