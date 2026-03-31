<?php
include '../../inc/koneksi.php';
session_start();

// validasi session
if (!isset($_SESSION['bengkel_id'])) {
    echo json_encode([
        "draw" => 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Session tidak valid"
    ]);
    exit;
}

$id_bengkel = $_SESSION['bengkel_id'];

// ambil parameter
$start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$draw   = $_POST['draw'] ?? 0;

$startDate = $_POST['startDate'] ?? '';
$endDate   = $_POST['endDate'] ?? '';
$merk      = $_POST['merk'] ?? '';

// sanitasi sederhana
$startDate = mysqli_real_escape_string($conn, $startDate);
$endDate   = mysqli_real_escape_string($conn, $endDate);
$merk      = mysqli_real_escape_string($conn, $merk);
$id_bengkel = mysqli_real_escape_string($conn, $id_bengkel);

// ================== WHERE ==================
$where = "WHERE t.id_bengkel = '$id_bengkel'";

if (!empty($startDate) && !empty($endDate)) {
    $where .= " AND DATE(t.tanggal) BETWEEN '$startDate' AND '$endDate'";
}

if (!empty($merk)) {
    $where .= " AND sp.merk_id = '$merk'";
}

// ================== QUERY DATA ==================
$sql = "
    SELECT 
        sp.kode_sparepart,
        sp.nama_sparepart,
        SUM(d.qty) as terjual
    FROM transaksi_detail_sparepart d
    JOIN transaksi t ON d.no_faktur = t.no_faktur
    JOIN spareparts sp ON d.kode_sparepart = sp.kode_sparepart
    $where
    GROUP BY sp.kode_sparepart, sp.nama_sparepart
    ORDER BY terjual DESC
    LIMIT $start, $length
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($conn)
    ]);
    exit;
}

// ================== DATA ==================
$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($query)) {
    $row['no'] = $no++;
    $row['terjual'] = (int)$row['terjual'];
    $data[] = $row;
}

// ================== TOTAL ==================
$totalSql = "
    SELECT COUNT(DISTINCT sp.kode_sparepart) as total
    FROM transaksi_detail_sparepart d
    JOIN transaksi t ON d.no_faktur = t.no_faktur
    JOIN spareparts sp ON d.kode_sparepart = sp.kode_sparepart
    $where
";

$totalQuery = mysqli_query($conn, $totalSql);

if (!$totalQuery) {
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($conn)
    ]);
    exit;
}

$total = mysqli_fetch_assoc($totalQuery)['total'] ?? 0;

// ================== OUTPUT ==================
echo json_encode([
    "draw" => (int)$draw,
    "recordsTotal" => (int)$total,
    "recordsFiltered" => (int)$total,
    "data" => $data
]);