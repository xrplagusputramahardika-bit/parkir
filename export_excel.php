<?php
/**
 * Export Laporan ke format Microsoft Excel XLSX
 * Smart Parking Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helper.php';
require_login();

$tanggal = $_GET['tanggal'] ?? $_GET['tgl_mulai'] ?? date('Y-m-d');
$jenis     = $_GET['jenis'] ?? '';
$area      = $_GET['area'] ?? '';

$sql = "SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik, a.nama_area, trf.tarif_per_jam,
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

$rows = [[
    'No Tiket',
    'Plat Nomor',
    'Jenis Kendaraan',
    'Warna',
    'Pemilik',
    'Area Parkir',
    'Waktu Masuk',
    'Waktu Keluar',
    'Durasi (Jam)',
    'Tarif/Jam (Rp)',
    'Total Biaya (Rp)',
    'Status',
    'Petugas Masuk',
    'Kasir Keluar'
]];

foreach ($data as $row) {
    $rows[] = [
        '#' . str_pad($row['id_parkir'], 5, '0', STR_PAD_LEFT),
        $row['plat_nomor'],
        strtoupper($row['jenis_kendaraan']),
        $row['warna'] ?: '-',
        $row['pemilik'] ?: 'Umum',
        $row['nama_area'],
        $row['waktu_masuk'],
        $row['waktu_keluar'] ?: '-',
        $row['durasi_jam'] ?? '-',
        (int)$row['tarif_per_jam'],
        (int)($row['biaya_total'] ?? 0),
        strtoupper($row['status']),
        $row['nama_petugas_masuk'],
        $row['nama_kasir_keluar'] ?: '-'
    ];
}

$columnName = static function (int $index): string {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
};

$xmlRows = '';
foreach ($rows as $rowIndex => $row) {
    $excelRow = $rowIndex + 1;
    $xmlRows .= '<row r="' . $excelRow . '">';

    foreach ($row as $columnIndex => $value) {
        $cellReference = $columnName($columnIndex + 1) . $excelRow;
        if (is_int($value) || is_float($value)) {
            $xmlRows .= '<c r="' . $cellReference . '"><v>' . $value . '</v></c>';
        } else {
            $escapedValue = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xmlRows .= '<c r="' . $cellReference . '" t="inlineStr"><is><t xml:space="preserve">' . $escapedValue . '</t></is></c>';
        }
    }

    $xmlRows .= '</row>';
}

$worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetData>' . $xmlRows . '</sheetData></worksheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Laporan Parkir" sheetId="1" r:id="rId1"/></sheets></workbook>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '</Types>';

$packageRelationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbookRelationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '</Relationships>';

$tempFile = tempnam(sys_get_temp_dir(), 'smartpark-xlsx-');
if ($tempFile === false) {
    throw new RuntimeException('Gagal menyiapkan file ekspor Excel.');
}

$xlsx = new ZipArchive();
$openResult = $xlsx->open($tempFile, ZipArchive::OVERWRITE);
if ($openResult !== true) {
    unlink($tempFile);
    throw new RuntimeException('Gagal membuat file Excel XLSX.');
}

$packageFiles = [
    '[Content_Types].xml' => $contentTypes,
    '_rels/.rels' => $packageRelationships,
    'xl/workbook.xml' => $workbook,
    'xl/_rels/workbook.xml.rels' => $workbookRelationships,
    'xl/worksheets/sheet1.xml' => $worksheet
];
foreach ($packageFiles as $path => $contents) {
    if (!$xlsx->addFromString($path, $contents)) {
        $xlsx->close();
        unlink($tempFile);
        throw new RuntimeException('Gagal menambahkan data ke file Excel XLSX.');
    }
}

if (!$xlsx->close()) {
    unlink($tempFile);
    throw new RuntimeException('Gagal menyelesaikan file Excel XLSX.');
}

$filename = 'Laporan_Parkir_' . str_replace('-', '', $tanggal) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tempFile);
unlink($tempFile);
exit;
