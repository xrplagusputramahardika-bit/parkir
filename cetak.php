<?php
/**
 * Cetak Laporan Formal (PDF / Print Ready)
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
require_login();

$tanggal = $_GET['tanggal'] ?? $_GET['tgl_mulai'] ?? date('Y-m-d');
$jenis     = $_GET['jenis'] ?? '';
$area      = $_GET['area'] ?? '';

$sql = "SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area, trf.tarif_per_jam,
        entry_user.nama_lengkap as nama_petugas_masuk, cashier.nama_lengkap as nama_kasir_keluar
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_tarif trf ON t.id_tarif = trf.id_tarif
    JOIN tb_user entry_user ON t.id_user = entry_user.id_user
    LEFT JOIN tb_user cashier ON t.id_user_keluar = cashier.id_user
    WHERE DATE(t.waktu_masuk) = :tanggal";
$params = [
    ':tanggal' => $tanggal
];

if (!empty($jenis)) {
    $sql .= " AND k.jenis_kendaraan = :jenis";
    $params[':jenis'] = $jenis;
}

if (!empty($area)) {
    $sql .= " AND t.id_area = :area";
    $params[':area'] = $area;
}

$sql .= " ORDER BY t.id_parkir ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();
$dataPages = $data ? array_chunk($data, 15) : [[]];

$totalPendapatan = 0;
$totalSelesai = 0;
$totalSedangParkir = 0;
foreach ($data as $r) {
    if ($r['status'] === 'keluar') {
        $totalPendapatan += (float)$r['biaya_total'];
        $totalSelesai++;
    } else {
        $totalSedangParkir++;
    }
}
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Parkir - SmartPark</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        body {
            background: #eef3f5;
            color: #19313b;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            padding: 28px 18px;
        }
        body > * { max-width: 1200px; margin-right: auto; margin-left: auto; }
        .report-content {
            width: 100%;
            max-width: 990px;
            margin: 0 auto;
        }
        .no-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 14px 18px !important;
            background: #fff !important;
            border: 1px solid #e0e8eb !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 22px rgba(25, 49, 59, .06);
        }
        .header-kop {
            padding: 24px 4px 18px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dce7e8;
        }
        .header-kop h3 { color: #087f69 !important; font-size: 21px; letter-spacing: .04em; }
        .header-kop p { color: #526970 !important; margin-top: 4px; }
        .header-kop small { color: #71838a !important; }
        .row.g-2.mb-3 {
            width: 100%;
            margin-right: 0 !important;
            margin-bottom: 22px !important;
            margin-left: 0 !important;
        }
        .row.g-2.mb-3 > div > div {
            height: 100%;
            padding: 14px 10px !important;
            border-color: #e2eaec !important;
            border-radius: 10px !important;
            background: #f8fafb !important;
        }
        .row.g-2.mb-3 small { color: #71838a !important; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .row.g-2.mb-3 strong { color: #19313b; font-size: 17px !important; }
        .row.g-2.mb-3 strong.text-success { color: #087f69 !important; }
        .table {
            --bs-table-bg: #fff;
            margin-bottom: 0;
            border-color: #e2eaec;
            font-size: 10px;
        }
        .table th {
            padding: 9px 7px;
            background: #edf4f5 !important;
            color: #35525b;
            font-size: 9px;
            letter-spacing: .035em;
            white-space: nowrap;
        }
        .table td { padding: 8px 7px; color: #435b63; }
        .table tbody tr:nth-child(even) { --bs-table-bg: #fbfcfc; }
        .table tfoot td { border-top: 2px solid #dce7e8; font-weight: 800; }
        .report-data-page + .report-data-page {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px dashed #cbd8dc;
        }
        .report-page-label { color: #71838a; font-size: 10px; }
        .row.mt-5 {
            width: 100%;
            margin-top: 34px !important;
            margin-right: 0 !important;
            margin-left: 0 !important;
            padding-top: 20px !important;
            border-top: 1px solid #e5ecee;
        }
        .row.mt-5 p { min-height: 36px; margin-bottom: 22px !important; color: #526970; line-height: 1.6; }
        .row.mt-5 > div { text-align: center; }
        .row.mt-5 strong {
            display: table;
            min-width: 180px;
            margin-right: auto;
            margin-left: auto;
            padding-top: 7px;
            border-top: 1px solid #8a9a9f;
            color: #19313b;
            text-decoration: none !important;
        }
        @page { size: landscape; margin: 11mm; }
        body {
            background: #eef3f5;
        }
        @media print {
            .no-print { display: none !important; }
            body {
                max-width: none;
                padding: 0;
                background: #fff;
                font-size: 10px;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            body > * { max-width: none; }
            .report-content { max-width: none; }
            .header-kop { padding-top: 0; }
            .table { font-size: 8px; }
            .table th { padding: 6px 4px; font-size: 7px; }
            .table td { padding: 5px 4px; }
            .report-data-page + .report-data-page {
                margin-top: 0;
                padding-top: 0;
                border-top: 0;
                break-before: page;
                page-break-before: always;
            }
            .report-page-label { display: none; }
            .row.mt-5 { margin-top: 24px !important; }
            tr { break-inside: avoid; }
        }
        @media (max-width: 700px) {
            body { padding: 12px 8px; }
            .no-print { align-items: flex-start; flex-direction: column; }
            .header-kop { align-items: flex-start !important; flex-direction: column; gap: 12px; }
            .header-kop .text-end { text-align: left !important; }
            .table { min-width: 940px; }
            body > table { overflow-x: auto; display: block; }
        }
    </style>
</head>
<body>

    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded-3 border">
        <div>
            <strong>Pratinjau Laporan Operasional Parkir</strong>
            <div class="small text-muted">Cetak laporan atau pilih “Simpan sebagai PDF” pada dialog printer.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-3" onclick="window.print()">
                <i class="bi bi-printer"></i> Cetak / Simpan PDF
            </button>
            <button class="btn btn-secondary btn-sm px-3" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <main class="report-content">
    <!-- Kop Laporan -->
    <div class="header-kop d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0 text-primary">SMARTPARK SYSTEM</h3>
            <p class="mb-0 text-muted small">Laporan Rekapitulasi Operasional & Pendapatan Parkir</p>
            <small class="text-secondary">Sistem Informasi Manajemen Parkir</small>
        </div>
        <div class="text-end small">
            <div><strong>Tanggal laporan:</strong> <?= format_tanggal($tanggal, false) ?> (00.00–23.59)</div>
            <div><strong>Waktu Cetak:</strong> <?= date('d/m/Y H:i') ?> WIB</div>
            <div><strong>Dicetak Oleh:</strong> <?= htmlspecialchars($currentUser['nama_lengkap']) ?></div>
        </div>
    </div>

    <!-- Ringkasan Box -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Total Kendaraan</small>
                <strong class="fs-5"><?= count($data) ?> Unit</strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Transaksi Selesai</small>
                <strong class="fs-5"><?= $totalSelesai ?> Kendaraan</strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Sedang parkir</small>
                <strong class="fs-5"><?= number_format($totalSedangParkir) ?> Kendaraan</strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Total Pendapatan</small>
                <strong class="fs-5 text-success"><?= format_rupiah($totalPendapatan) ?></strong>
            </div>
        </div>
    </div>

    <?php $no = 1; foreach ($dataPages as $pageIndex => $pageData): ?>
    <section class="report-data-page">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Rincian transaksi</strong>
            <span class="report-page-label">Halaman <?= $pageIndex + 1 ?> dari <?= count($dataPages) ?></span>
        </div>
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th class="text-center" width="40">No</th>
                    <th>Tiket #</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Biaya Total</th>
                    <th>Status</th>
                    <th>Petugas Masuk</th>
                    <th>Kasir Keluar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pageData)): ?>
                <tr>
                    <td colspan="12" class="text-center py-3 text-muted">Tidak ada data transaksi pada periode ini.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pageData as $r): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="font-monospace">#<?= str_pad($r['id_parkir'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td><strong><?= htmlspecialchars($r['plat_nomor'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td class="text-capitalize"><?= htmlspecialchars($r['jenis_kendaraan'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['nama_area'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= date('d/m/y H:i', strtotime($r['waktu_masuk'])) ?></td>
                        <td><?= $r['waktu_keluar'] ? date('d/m/y H:i', strtotime($r['waktu_keluar'])) : '-' ?></td>
                        <td><?= $r['durasi_jam'] ? $r['durasi_jam'] . ' Jam' : '-' ?></td>
                        <td><?= $r['biaya_total'] ? format_rupiah($r['biaya_total']) : '-' ?></td>
                        <td>
                            <span class="badge rounded-pill <?= $r['status'] === 'keluar' ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                <?= $r['status'] === 'keluar' ? 'SELESAI' : 'PARKIR' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['nama_petugas_masuk'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['nama_kasir_keluar'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($pageIndex === count($dataPages) - 1): ?>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td colspan="9" class="text-end">TOTAL PENDAPATAN RESMI:</td>
                    <td colspan="3" class="text-success"><?= format_rupiah($totalPendapatan) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </section>
    <?php endforeach; ?>

    <!-- Tanda Tangan Pengesahan -->
    <div class="row mt-5 pt-3">
        <div class="col-6 text-center">
            <p class="mb-5">Petugas Pengelola,</p>
            <strong class="text-decoration-underline"><?= htmlspecialchars($currentUser['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong>
            <div class="small text-muted text-uppercase"><?= htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="col-6 text-center">
            <p class="mb-5">Mengetahui,<br>Pimpinan Manajemen Parkir,</p>
            <strong class="text-decoration-underline">Ir. Hendra Gunawan</strong>
            <div class="small text-muted">Pimpinan Manajemen Parkir</div>
        </div>
    </div>
    </main>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Uncomment jika ingin langsung auto-print:
            // window.print();
        });
    </script>
</body>
</html>
