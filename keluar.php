<?php
/**
 * Gerbang Keluar & Kasir Scan Pembayaran
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
check_role(['admin', 'petugas']);
$user = current_user();

$error = '';
$prefillId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Proses Simpan Pembayaran Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_keluar'])) {
    $idParkir = (int)($_POST['id_parkir'] ?? 0);
    $uangBayar = (float)($_POST['uang_bayar'] ?? 0);

    if ($idParkir <= 0) {
        $error = 'ID Transaksi Parkir tidak valid!';
    } else {
        // Ambil data transaksi aktif
        $stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, trf.tarif_per_jam, a.id_area, a.nama_area 
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            JOIN tb_tarif trf ON t.id_tarif = trf.id_tarif
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            WHERE t.id_parkir = :id AND t.status = 'masuk'");
        $stmt->execute([':id' => $idParkir]);
        $trx = $stmt->fetch();

        if (!$trx) {
            $error = 'Transaksi tidak ditemukan atau sudah diselesaikan sebelumnya!';
        } else {
            $waktuKeluar = date('Y-m-d H:i:s');
            $calc = hitung_biaya($trx['waktu_masuk'], $waktuKeluar, $trx['tarif_per_jam']);
            $durasiJam = $calc['durasi_jam'];
            $biayaTotal = $calc['biaya_total'];

            if ($uangBayar < $biayaTotal) {
                $error = 'Jumlah uang bayar (' . format_rupiah($uangBayar) . ') kurang dari total biaya (' . format_rupiah($biayaTotal) . ')!';
            } else {
                $kembalian = $uangBayar - $biayaTotal;

                // Update Transaksi
                $stmtUp = $pdo->prepare("UPDATE tb_transaksi SET 
                    waktu_keluar = :keluar,
                    durasi_jam = :durasi,
                    biaya_total = :biaya,
                    status = 'keluar',
                    id_user_keluar = :kasir
                    WHERE id_parkir = :id");
                $stmtUp->execute([
                    ':keluar' => $waktuKeluar,
                    ':durasi' => $durasiJam,
                    ':biaya'  => $biayaTotal,
                    ':kasir'  => $user['id_user'],
                    ':id'     => $idParkir
                ]);

                // Update kapasitas terisi pada area
                sync_area_terisi($pdo, $trx['id_area']);

                // Log aktivitas
                log_aktivitas($pdo, $user['id_user'], "Kendaraan {$trx['plat_nomor']} keluar dari {$trx['nama_area']} - Bayar " . format_rupiah($biayaTotal));

                // Buka struk keluar dan redirect dengan pesan sukses
                set_flash('success', "Pembayaran parkir <strong>{$trx['plat_nomor']}</strong> sukses! Total: <strong>" . format_rupiah($biayaTotal) . "</strong>, Kembalian: <strong>" . format_rupiah($kembalian) . "</strong>");
                
                // Simpan parameter untuk auto print di halaman
                redirect(base_url("modules/transaksi/keluar.php?selesai=$idParkir&kembalian=$kembalian"));
            }
        }
    }
}

$page_title = 'Gerbang Keluar & Kasir Pembayaran';
$active_menu = 'transaksi_keluar';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-upc-scan text-primary me-2"></i>Gerbang Keluar & Kasir Scan</h4>
                <p class="text-muted small mb-0">Scan barcode tiket parkir melalui kamera / scanner USB atau cari plat nomor kendaraan.</p>
            </div>
            <a href="<?= base_url('modules/transaksi/masuk.php') ?>" class="btn btn-outline-success rounded-pill btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ke Gerbang Masuk
            </a>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <div><?= $error ?></div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['selesai'])): 
            $selesaiId = (int)$_GET['selesai'];
            $kembaliVal = (float)($_GET['kembalian'] ?? 0);

            // Ambil data plat & biaya untuk pengumuman suara
            $stmtSelesaiTrx = $pdo->prepare("SELECT k.plat_nomor, t.biaya_total FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan WHERE t.id_parkir = :id");
            $stmtSelesaiTrx->execute([':id' => $selesaiId]);
            $infoSelesai = $stmtSelesaiTrx->fetch();
            $platSelesai = $infoSelesai['plat_nomor'] ?? 'Kendaraan';
            $biayaSelesaiFmt = format_rupiah($infoSelesai['biaya_total'] ?? 0);
        ?>
        <div class="card card-custom border-success mb-4 text-center p-4">
            <div class="text-success mb-2">
                <i class="bi bi-check-circle-fill display-4"></i>
            </div>
            <h4 class="fw-bold">Transaksi Selesai & Barrier Gate Terbuka!</h4>
            <p class="text-muted mb-2">Nomor Tiket: <strong class="text-dark">#<?= str_pad($selesaiId, 5, '0', STR_PAD_LEFT) ?></strong> • Plat: <strong class="text-dark"><?= htmlspecialchars($platSelesai) ?></strong></p>
            <h5 class="fw-bold text-success mb-3">Kembalian: <?= format_rupiah($kembaliVal) ?></h5>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= base_url('modules/transaksi/cetak_struk.php?id=' . $selesaiId . '&type=keluar') ?>" target="_blank" class="btn btn-primary-gradient btn-rounded px-4">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Struk Pembayaran
                </a>
                <button type="button" class="btn btn-outline-danger btn-rounded px-3" onclick="VoiceAnnouncer.announceKeluar('<?= addslashes($platSelesai) ?>', '<?= addslashes($biayaSelesaiFmt) ?>')">
                    <i class="bi bi-volume-up-fill me-1"></i> Suara Gerbang
                </button>
                <a href="<?= base_url('modules/transaksi/keluar.php') ?>" class="btn btn-light border btn-rounded px-4">
                    <i class="bi bi-arrow-clockwise me-1"></i> Transaksi Baru
                </a>
            </div>
        </div>
        <script>
            // Auto open receipt print popup
            window.open('<?= base_url('modules/transaksi/cetak_struk.php?id=' . $selesaiId . '&type=keluar') ?>', '_blank', 'width=450,height=600');
            // Auto announce voice checkout
            setTimeout(() => {
                if (typeof VoiceAnnouncer !== 'undefined') {
                    VoiceAnnouncer.announceKeluar('<?= addslashes($platSelesai) ?>', '<?= addslashes($biayaSelesaiFmt) ?>');
                }
            }, 600);
        </script>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Kolom Pencarian & Scanner Kamera -->
            <div class="col-md-5">
                <!-- Box Pencarian Cepat -->
                <div class="card card-custom mb-3">
                    <div class="card-header bg-white border-0 pt-3 pb-1">
                        <h6 class="fw-bold mb-0"><i class="bi bi-search text-primary me-2"></i>Cari Tiket / Plat Nomor</h6>
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light"><i class="bi bi-upc"></i></span>
                            <input type="text" id="queryInput" class="form-control scanner-input form-control-lg font-monospace text-uppercase" placeholder="Scan Barcode / No Tiket / Plat" autocomplete="off" autofocus>
                            <button class="btn btn-primary" type="button" id="btnCari">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block">
                            <i class="bi bi-info-circle me-1"></i> Mendukung <strong>USB Barcode Scanner</strong> langsung tanpa klik mouse!
                        </small>
                    </div>
                </div>

                <!-- Box Kamera Pemindai (QR & Barcode) -->
                <div class="card card-custom">
                    <div class="card-header bg-white border-0 pt-3 pb-1 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-camera-video-fill text-danger me-2"></i>Kamera Pemindai Tiket</h6>
                        <button type="button" id="btnToggleCamera" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="toggleCamera()">
                            <i class="bi bi-camera-video me-1"></i> Buka Kamera
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="scanner-container" id="scannerWrapper">
                            <div id="readerKeluar"></div>
                            <div id="scannerPlaceholder" class="text-center p-4 text-white-50">
                                <i class="bi bi-qr-code-scan display-4 d-block mb-2 text-primary opacity-50"></i>
                                <span>Kamera sedang mati.<br>Klik tombol <strong>Buka Kamera</strong> untuk memindai QR code tiket.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Rincian Transaksi & Kalkulator Kasir -->
            <div class="col-md-7">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-1">
                        <h5 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff text-success me-2"></i>Rincian Tagihan & Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <!-- State Kosong / Belum Ada Data -->
                        <div id="emptyState" class="text-center py-5 text-muted">
                            <i class="bi bi-ticket-perforated display-3 text-secondary opacity-50 mb-3 d-block"></i>
                            <h5>Belum ada tiket yang dipindai</h5>
                            <p class="small text-muted mb-0">Silakan scan QR code tiket atau masukkan nomor plat di kolom pencarian.</p>
                        </div>

                        <!-- Panel Detail Transaksi Terisi -->
                        <div id="trxDetailPanel" class="d-none">
                            <div class="bg-light rounded-4 p-3 mb-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-primary fs-6 font-monospace" id="lblTiket">#00000</span>
                                    <span class="badge badge-soft-info text-capitalize" id="lblJenis">Motor</span>
                                </div>
                                <div class="text-center my-2">
                                    <div class="fs-2 fw-bold font-monospace bg-dark text-white rounded-3 py-1 px-3 d-inline-block" id="lblPlat">B 1234 ABC</div>
                                </div>
                                <div class="row g-2 small text-muted mt-2 border-top pt-2">
                                    <div class="col-6">
                                        <div>Pemilik: <strong class="text-dark" id="lblPemilik">-</strong></div>
                                        <div>Warna: <strong class="text-dark" id="lblWarna">-</strong></div>
                                        <div>Area: <strong class="text-dark" id="lblArea">-</strong></div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <div>Masuk: <strong class="text-dark" id="lblWaktuMasuk">-</strong></div>
                                        <div>Keluar: <strong class="text-dark" id="lblWaktuKeluar">-</strong></div>
                                        <div>Petugas Masuk: <strong class="text-dark" id="lblPetugasMasuk">-</strong></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kalkulasi Durasi & Tarif -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="border rounded-3 p-2 text-center bg-white">
                                        <small class="text-muted d-block">Durasi Parkir</small>
                                        <div class="fw-bold fs-5 text-dark" id="lblDurasiTeks">0 Jam 0 Menit</div>
                                        <small class="badge bg-warning-subtle text-warning-emphasis border" id="lblDurasiJamDihitung">Dihitung: 1 Jam</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded-3 p-2 text-center bg-white">
                                        <small class="text-muted d-block">Tarif per Jam</small>
                                        <div class="fw-bold fs-5 text-dark" id="lblTarifPerJam">Rp 0</div>
                                        <small class="text-muted">Standar Area</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Biaya Box -->
                            <div class="alert alert-primary rounded-4 py-3 text-center mb-3">
                                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Total Tagihan Parkir</small>
                                <div class="display-6 fw-bold text-primary my-1" id="lblTotalBiaya">Rp 0</div>
                            </div>

                            <!-- Form Kasir Pembayaran -->
                            <form action="" method="POST" id="formCheckout">
                                <input type="hidden" name="proses_keluar" value="1">
                                <input type="hidden" name="id_parkir" id="hiddenIdParkir" value="">

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">Nominal Uang Diterima (Rp)</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-white fw-bold">Rp</span>
                                        <input type="number" name="uang_bayar" id="inputUangBayar" class="form-control form-control-lg fw-bold fs-4 text-success" placeholder="0" min="0" required>
                                    </div>
                                </div>

                                <!-- Preset Nominal Cepat -->
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominalPas()">Uang Pas</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominal(5000)">5.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominal(10000)">10.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominal(20000)">20.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominal(50000)">50.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setNominal(100000)">100.000</button>
                                </div>

                                <!-- Kembalian Display -->
                                <div class="border rounded-3 p-3 bg-light d-flex justify-content-between align-items-center mb-4">
                                    <span class="fw-semibold text-muted">Kembalian:</span>
                                    <span class="fs-4 fw-bold text-danger" id="lblKembalian">Rp 0</span>
                                </div>

                                <button type="submit" id="btnSubmitBayar" class="btn btn-success btn-rounded w-100 py-3 fs-5 fw-bold shadow-sm" disabled>
                                    <i class="bi bi-check2-circle me-1"></i> Selesaikan Transaksi & Buka Gate
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/scanner.js') ?>"></script>
<script>
let currentTrxData = null;
let cameraActive = false;

document.addEventListener('DOMContentLoaded', function () {
    const queryInput = document.getElementById('queryInput');
    const btnCari = document.getElementById('btnCari');
    const inputUangBayar = document.getElementById('inputUangBayar');

    // Pencarian manual via klik atau Enter
    btnCari.addEventListener('click', function () {
        cariTransaksi(queryInput.value);
    });

    queryInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            cariTransaksi(queryInput.value);
        }
    });

    // Hardware Scanner Listener (Otomatis tangkap tanpa fokus di input)
    listenHardwareScanner(function (barcode) {
        queryInput.value = barcode;
        cariTransaksi(barcode);
    });

    // Perhitungan kembalian otomatis saat kasir mengetik uang bayar
    inputUangBayar.addEventListener('input', hitungKembalian);

    // Jika ada prefill ID dari URL (misal klik dari tabel dashboard)
    <?php if ($prefillId): ?>
        cariTransaksi('<?= $prefillId ?>');
    <?php endif; ?>
});

function cariTransaksi(keyword) {
    if (!keyword || !keyword.trim()) return;

    Swal.fire({
        title: 'Mencari Tiket...',
        text: 'Mengambil data parkir kendaraan',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('<?= base_url('modules/transaksi/ajax_detail.php?q=') ?>' + encodeURIComponent(keyword.trim()))
        .then(r => r.json())
        .then(res => {
            Swal.close();
            if (res.success && res.data) {
                renderTransaksi(res.data);
            } else {
                playScanBeep(false);
                Swal.fire({
                    icon: 'warning',
                    title: 'Tiket Tidak Ditemukan',
                    text: res.message || 'Kendaraan tidak sedang parkir atau nomor tiket salah.',
                    confirmButtonColor: '#4361ee'
                });
            }
        })
        .catch(err => {
            Swal.close();
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: 'Gagal menghubungi server.' });
        });
}

function renderTransaksi(d) {
    currentTrxData = d;
    document.getElementById('emptyState').classList.add('d-none');
    document.getElementById('trxDetailPanel').classList.remove('d-none');

    document.getElementById('hiddenIdParkir').value = d.id_parkir;
    document.getElementById('lblTiket').textContent = '#' + String(d.id_parkir).padStart(5, '0');
    document.getElementById('lblPlat').textContent = d.plat_nomor;
    document.getElementById('lblJenis').textContent = d.jenis_kendaraan;
    document.getElementById('lblPemilik').textContent = d.pemilik;
    document.getElementById('lblWarna').textContent = d.warna;
    document.getElementById('lblArea').textContent = d.nama_area;
    document.getElementById('lblWaktuMasuk').textContent = d.waktu_masuk_fmt;
    document.getElementById('lblWaktuKeluar').textContent = d.waktu_keluar_fmt;
    document.getElementById('lblPetugasMasuk').textContent = d.petugas_masuk;

    document.getElementById('lblDurasiTeks').textContent = d.durasi_teks;
    document.getElementById('lblDurasiJamDihitung').textContent = 'Dihitung: ' + d.durasi_jam + ' Jam';
    document.getElementById('lblTarifPerJam').textContent = d.tarif_per_jam_fmt;
    document.getElementById('lblTotalBiaya').textContent = d.biaya_total_fmt;

    // Reset input bayar
    const inputBayar = document.getElementById('inputUangBayar');
    inputBayar.value = '';
    inputBayar.focus();
    hitungKembalian();
}

function hitungKembalian() {
    if (!currentTrxData) return;
    const total = parseFloat(currentTrxData.biaya_total) || 0;
    const bayar = parseFloat(document.getElementById('inputUangBayar').value) || 0;
    const kembali = bayar - total;
    const lblKembali = document.getElementById('lblKembalian');
    const btnSubmit = document.getElementById('btnSubmitBayar');

    if (bayar >= total && total >= 0) {
        lblKembali.textContent = 'Rp ' + kembali.toLocaleString('id-ID');
        lblKembali.classList.remove('text-danger');
        lblKembali.classList.add('text-success');
        btnSubmit.disabled = false;
    } else {
        lblKembali.textContent = 'Kurang Rp ' + Math.abs(kembali).toLocaleString('id-ID');
        lblKembali.classList.remove('text-success');
        lblKembali.classList.add('text-danger');
        btnSubmit.disabled = true;
    }
}

function setNominalPas() {
    if (!currentTrxData) return;
    document.getElementById('inputUangBayar').value = currentTrxData.biaya_total;
    hitungKembalian();
}

function setNominal(val) {
    document.getElementById('inputUangBayar').value = val;
    hitungKembalian();
}

function toggleCamera() {
    const placeholder = document.getElementById('scannerPlaceholder');
    if (!cameraActive) {
        placeholder.classList.add('d-none');
        initCameraScanner('readerKeluar', function (decodedText) {
            playScanBeep(true);
            cariTransaksi(decodedText);
        });
        cameraActive = true;
        document.getElementById('btnToggleCamera').innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Matikan Kamera';
        document.getElementById('btnToggleCamera').classList.replace('btn-outline-primary', 'btn-outline-danger');
    } else {
        stopCameraScanner();
        cameraActive = false;
        placeholder.classList.remove('d-none');
        document.getElementById('btnToggleCamera').innerHTML = '<i class="bi bi-camera-video me-1"></i> Buka Kamera';
        document.getElementById('btnToggleCamera').classList.replace('btn-outline-danger', 'btn-outline-primary');
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
