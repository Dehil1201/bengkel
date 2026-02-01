<?php
// =====================================================
// DEBUG (matikan saat production)
// =====================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =====================================================
// SESSION & DEPENDENCY
// =====================================================
if (session_status() === PHP_SESSION_NONE) session_start();

require '../../inc/koneksi.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// =====================================================
// HELPER
// =====================================================
function stop($msg)
{
    http_response_code(400);
    die($msg);
}

// =====================================================
// AUTH
// =====================================================
if (empty($_SESSION['id_user'])) stop('Session user tidak valid');
$id_user = $_SESSION['id_user'];

// =====================================================
// AMBIL BENGKEL
// =====================================================
$q = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
if (!$q) stop(mysqli_error($conn));

$d = mysqli_fetch_assoc($q);
$id_bengkel = $d['bengkel_id'] ?? null;
if (!$id_bengkel) stop('Bengkel tidak ditemukan');

// =====================================================
// INPUT (DROPDOWN)
// =====================================================
$jenis  = $_POST['jenis'] ?? '';
$format = $_POST['format'] ?? '';

if (!$jenis)  stop('Jenis data belum dipilih');
if (!in_array($format, ['sql', 'excel'])) stop('Format tidak valid');

// =====================================================
// MAP DATA
// =====================================================
$map = [
    'sparepart' => ['spareparts'],
    'transaksi' => ['transaksi', 'transaksi_detail'],
    'hutang'    => ['hutang'],
    'piutang'   => ['piutang']
];

if (!isset($map[$jenis])) stop('Jenis data tidak dikenal');

// =====================================================
// ======================= SQL ==========================
// =====================================================
if ($format === 'sql') {

    $filename = "backup_{$jenis}_bengkel_{$id_bengkel}_" . date('Ymd_His') . ".sql";

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    echo "-- Backup {$jenis}\n";
    echo "-- Bengkel ID: {$id_bengkel}\n";
    echo "-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($map[$jenis] as $t) {

        $q = mysqli_query($conn, "SELECT * FROM `$t` WHERE bengkel_id='$id_bengkel'");
        if (!$q) die(mysqli_error($conn));

        while ($r = mysqli_fetch_assoc($q)) {

            $cols = array_keys($r);
            $vals = array_map(
                fn($v) => is_null($v) ? "NULL" : "'" . mysqli_real_escape_string($conn, $v) . "'",
                array_values($r)
            );

            echo "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }

        echo "\n";
    }

    exit;
}

// =====================================================
// ====================== EXCEL =========================
// =====================================================
if ($format === 'excel') {

    $spreadsheet = new Spreadsheet();
    $sheetIndex = 0;

    foreach ($map[$jenis] as $t) {

        $sheet = ($sheetIndex === 0)
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet();

        $sheet->setTitle(substr($t, 0, 31));

        $q = mysqli_query($conn, "SELECT * FROM `$t` WHERE bengkel_id='$id_bengkel'");
        if (!$q) stop(mysqli_error($conn));

        $rowNum = 1;

        while ($row = mysqli_fetch_assoc($q)) {

            if ($rowNum === 1) {
                $col = 'A';
                foreach (array_keys($row) as $h) {
                    $sheet->setCellValue($col++.'1', $h);
                }
            }

            $col = 'A';
            foreach ($row as $v) {
                $sheet->setCellValue($col++.($rowNum+1), $v);
            }

            $rowNum++;
        }

        $sheetIndex++;
    }

    $filename = "backup_{$jenis}_bengkel_{$id_bengkel}_" . date('Ymd_His') . ".xlsx";

    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    $spreadsheet->disconnectWorksheets();
    exit;
}
