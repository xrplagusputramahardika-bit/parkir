<?php
/**
 * Cetak Struk Tiket Parkir (Thermal Printer 58mm / 80mm Ready)
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
require_login();

$idParkir = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'masuk'; // 'masuk' atau 'keluar'

if ($idParkir <= 0) {
    die("ID Parkir tidak valid.");
}

$stmt = $pdo->prepare("SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik, a.nama_area, trf.tarif_per_jam, u.nama_lengkap as nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_tarif trf ON t.id_tarif = trf.id_tarif
    JOIN tb_user u ON t.id_user = u.id_user
    WHERE t.id_parkir = :id");
$stmt->execute([':id' => $idParkir]);
$t = $stmt->fetch();

if (!$t) {
    die("Data parkir tidak ditemukan.");
}

$barcodeValue = "TIKET-" . str_pad($t['id_parkir'], 5, '0', STR_PAD_LEFT);
$qrValue = $t['id_parkir']; // simple numeric ID for ultra reliable camera scanning
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Tiket #<?= str_pad($t['id_parkir'], 5, '0', STR_PAD_LEFT) ?> - SmartPark</title>
    <!-- JsBarcode -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #17243b;
            background: #f2f5fa;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin: 0;
            padding: 28px 16px;
            background: #f2f5fa;
        }

        .ticket-box {
            width: min(100%, 420px);
            padding: 18px;
            border: 1px solid #dbe2ea;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(24, 41, 72, 0.07);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 0 13px;
            border-bottom: 1px solid #e5eaf0;
        }

        .brand-mark {
            display: grid;
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 9px;
            background: #eef2ff;
            color: #344fc0;
            font-size: 17px;
        }

        .brand-copy {
            min-width: 0;
        }

        .title {
            color: #17243b;
            font-size: 15px;
            font-weight: 800;
        }

        .sub {
            margin-top: 2px;
            color: #718096;
            font-size: 11px;
        }

        .ticket-content {
            padding: 14px 0 0;
        }

        .ticket-kicker {
            display: none;
        }

        .ticket-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 0 10px;
            padding: 5px 9px;
            border-radius: 6px;
            background: <?= ($t['status'] === 'keluar' || $type === 'keluar') ? '#edf7f1' : '#eef3ff' ?>;
            color: <?= ($t['status'] === 'keluar' || $type === 'keluar') ? '#198754' : '#3c58c8' ?>;
            font-size: 11px;
            font-weight: 700;
        }

        .ticket-number {
            margin-left: auto;
            padding: 5px 8px;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            background: #f8fafc;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .plate-box {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #17243b;
            font-family: "Courier New", monospace;
            font-size: clamp(25px, 7vw, 32px);
            font-weight: 800;
            letter-spacing: 0.1em;
            text-align: center;
        }

        .ticket-section-title {
            margin: 16px 0 6px;
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .details-list {
            display: grid;
            gap: 0;
        }

        .row-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 7px 0;
            border-bottom: 1px solid #edf1f6;
            font-size: 12px;
            line-height: 1.4;
        }

        .row-item:last-child {
            border-bottom: 0;
        }

        .row-item > span:first-child {
            flex: 0 0 auto;
            color: #718096;
        }

        .row-item > :last-child {
            max-width: 62%;
            color: #24324a;
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .divider {
            height: 1px;
            margin: 8px 0;
            border-top: 1px solid #e5eaf0;
        }

        .row-item.total {
            align-items: center;
            margin-top: 4px;
            padding: 10px;
            border: 0;
            border-radius: 7px;
            background: #f1f5f9;
            font-size: 13px;
        }

        .row-item.total > span:first-child {
            color: #334155;
            font-weight: 700;
        }

        .row-item.total > :last-child {
            color: #17243b;
            font-size: 16px;
            font-weight: 800;
        }

        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin: 16px 0 12px;
            padding: 10px 0;
            border-top: 1px dashed #cbd5e1;
            border-bottom: 1px dashed #cbd5e1;
        }

        #qrcode {
            display: grid;
            min-width: 96px;
            min-height: 96px;
            place-items: center;
        }

        #qrcode img,
        #qrcode canvas {
            display: block;
            max-width: 100%;
        }

        .barcode-svg {
            display: block;
            width: min(100%, 240px);
            height: 36px;
        }

        .scan-instruction {
            color: #64748b;
            font-size: 10px;
            text-align: center;
        }

        .footer {
            padding-top: 12px;
            border-top: 0;
            color: #64748b;
            font-size: 10px;
            line-height: 1.5;
            text-align: center;
        }

        .footer p + p {
            margin-top: 3px;
        }

        .action-bar {
            display: flex;
            width: min(100%, 420px);
            gap: 10px;
        }

        .action-bar button {
            min-height: 44px;
            border: 0;
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-print {
            flex: 1;
            background: #4361ee;
        }

        .btn-close-win {
            padding: 0 20px;
            background: #64748b;
        }

        @media (max-width: 480px) {
            body {
                padding: 14px 10px;
            }

            .ticket-box {
                width: min(100%, 420px);
                padding: 16px;
            }
        }

        @page {
            size: 58mm auto;
            margin: 2mm;
        }

        @media print {
            :root,
            body {
                min-height: 0;
                background: #fff;
            }

            body {
                display: block;
                padding: 0;
                color: #000;
            }

            .ticket-box {
                width: 100%;
                max-width: 54mm;
                margin: 0 auto;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .header {
                padding: 0 0 3mm;
                border-bottom: 1px dashed #777;
            }

            .brand-mark {
                background: #f1f5f9;
                color: #000;
            }

            .ticket-content {
                padding: 2.5mm 0 0;
            }

            .ticket-status,
            .plate-box,
            .qr-section,
            .row-item.total {
                color: #000;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .plate-box {
                border-color: #333;
                border-radius: 2mm;
                background: #fff;
                font-size: 20pt;
            }

            .ticket-section-title {
                margin-top: 3.5mm;
                color: #000;
            }

            .row-item {
                padding: 1.6mm 0;
                border-color: #ccc;
                font-size: 9.5pt;
            }

            .row-item > span:first-child,
            .row-item > :last-child,
            .footer,
            .scan-instruction {
                color: #000;
            }

            .row-item.total {
                border: 1px solid #999;
                border-radius: 1.5mm;
                background: #f2f2f2;
            }

            .row-item.total > :last-child {
                font-size: 12pt;
            }

            .qr-section {
                margin: 3mm 0 2mm;
                padding: 2.5mm 0;
                border-color: #777;
            }

            #qrcode {
                min-width: 25mm;
                min-height: 25mm;
            }

            .barcode-svg {
                width: 100%;
                max-width: 48mm;
                height: 9mm;
            }

            .scan-instruction,
            .footer {
                font-size: 8pt;
            }

            .title,
            .sub,
            .ticket-number {
                color: #000;
            }

            .header,
            .plate-box,
            .qr-section,
            .footer {
                break-inside: avoid;
            }

            .action-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <?php $isExitTicket = $t['status'] === 'keluar' || $type === 'keluar'; ?>
    <main class="ticket-box" id="printableArea">
        <header class="header">
            <div class="brand-mark"><i class="bi bi-p-square-fill"></i></div>
            <div class="brand-copy">
                <div class="title">SMARTPARK</div>
                <div class="sub">Tiket parkir</div>
            </div>
            <div class="ticket-number">#<?= str_pad($t['id_parkir'], 5, '0', STR_PAD_LEFT) ?></div>
        </header>

        <div class="ticket-content">
        <div class="ticket-status">
            <i class="bi <?= $isExitTicket ? 'bi-check-circle-fill' : 'bi-circle-fill' ?>"></i>
            <?= $isExitTicket ? 'Parkir selesai' : 'Kendaraan masuk' ?>
        </div>

        <div class="plate-box"><?= htmlspecialchars($t['plat_nomor'], ENT_QUOTES, 'UTF-8') ?></div>

        <div class="ticket-section-title">Informasi kendaraan</div>
        <div class="details-list">
        <div class="row-item">
            <span>Jenis kendaraan</span>
            <strong style="text-transform: capitalize;"><?= htmlspecialchars($t['jenis_kendaraan'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="row-item">
            <span>Area parkir</span>
            <strong><?= htmlspecialchars($t['nama_area'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <?php if (!empty($t['slot_number'])): ?>
        <div class="row-item">
            <span>Nomor slot</span>
            <strong><?= str_pad((string)$t['slot_number'], 2, '0', STR_PAD_LEFT) ?></strong>
        </div>
        <?php endif; ?>
        <?php if (!empty($t['pemilik'])): ?>
        <div class="row-item">
            <span>Pemilik</span>
            <strong><?= htmlspecialchars($t['pemilik'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <?php endif; ?>
        <div class="row-item">
            <span>Waktu masuk</span>
            <strong><?= date('d/m/Y H:i', strtotime($t['waktu_masuk'])) ?></strong>
        </div>
        </div>

        <?php if ($isExitTicket): ?>
        <div class="divider"></div>
        <div class="ticket-section-title">Rincian pembayaran</div>
        <div class="details-list">
        <div class="row-item">
            <span>Waktu keluar</span>
            <strong><?= date('d/m/Y H:i', strtotime($t['waktu_keluar'] ?? date('Y-m-d H:i:s'))) ?></strong>
        </div>
        <div class="row-item">
            <span>Durasi parkir</span>
            <strong><?= $t['durasi_jam'] ?? 1 ?> Jam</strong>
        </div>
        <div class="row-item">
            <span>Tarif per jam</span>
            <strong><?= format_rupiah($t['tarif_per_jam']) ?></strong>
        </div>
        <div class="row-item total">
            <span>Total pembayaran</span>
            <strong><?= format_rupiah($t['biaya_total']) ?></strong>
        </div>
        </div>
        <?php else: ?>
        <div class="row-item rate-row">
            <span>Tarif parkir</span>
            <strong><?= format_rupiah($t['tarif_per_jam']) ?> / jam</strong>
        </div>
        <?php endif; ?>

        <section class="qr-section" aria-label="Kode tiket untuk dipindai">
            <div id="qrcode"></div>
            <svg id="barcode" class="barcode-svg"></svg>
            <div class="scan-instruction">Pindai kode ini saat kendaraan keluar</div>
        </section>

        <footer class="footer">
            <p>Simpan tiket ini sampai proses keluar selesai.</p>
            <p>Jika tiket hilang, hubungi petugas dan tunjukkan STNK.</p>
            <p><?= date('d/m/Y H:i') ?></p>
        </footer>
        </div>
    </main>

    <div class="action-bar">
        <button type="button" class="btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Tiket
        </button>
        <button type="button" class="btn-close-win" onclick="window.close()">Tutup</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Generate QR Code
            new QRCode(document.getElementById("qrcode"), {
                text: "<?= $qrValue ?>",
                width: 90,
                height: 90,
                correctLevel : QRCode.CorrectLevel.M
            });

            // Generate Barcode
            JsBarcode("#barcode", "<?= $barcodeValue ?>", {
                format: "CODE128",
                width: 1.5,
                height: 38,
                displayValue: false
            });

            // Auto-trigger print
            setTimeout(function () {
                window.print();
            }, 600);
        });
    </script>
</body>
</html>
