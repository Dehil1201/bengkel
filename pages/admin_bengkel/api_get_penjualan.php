<?php
session_start();
include "../../inc/koneksi.php";

header('Content-Type: application/json; charset=utf-8');

$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// Ambil bengkel user
$qBengkel = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$dBengkel = mysqli_fetch_assoc($qBengkel);
$id_bengkel = $dBengkel['bengkel_id'] ?? null;

// Parameter DataTables
$draw   = intval($_GET['draw'] ?? 0);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? "";

// Hitung total data
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_bengkel = '$id_bengkel'");
$totalData = mysqli_fetch_assoc($totalQuery)['total'] ?? 0;

// Query dasar
$baseQuery = "
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN transaksi_detail_sparepart d ON t.no_faktur = d.no_faktur
    LEFT JOIN spareparts s ON d.kode_sparepart = s.kode_sparepart
    WHERE t.id_bengkel = '$id_bengkel'
";

// Tambahkan search
if (!empty($searchValue)) {
    $searchValue = mysqli_real_escape_string($conn, $searchValue);
    $baseQuery .= " AND (
        t.no_faktur LIKE '%$searchValue%' OR
        p.nama_pelanggan LIKE '%$searchValue%' OR
        u.nama_lengkap LIKE '%$searchValue%' OR
        s.nama_sparepart LIKE '%$searchValue%'
    ) ";
}

// Hitung total setelah filter
$totalFilteredQuery = mysqli_query($conn, "SELECT COUNT(DISTINCT t.no_faktur) as total ".$baseQuery);
$totalFiltered = mysqli_fetch_assoc($totalFilteredQuery)['total'] ?? 0;

// Order
$orderColumnIndex = $_GET['order'][0]['column'] ?? 0;
$orderColumnDir   = $_GET['order'][0]['dir'] ?? 'asc';
$columns = [
    0 => 't.no_faktur',
    1 => 't.tanggal',
    2 => 'p.nama_pelanggan',
    3 => 'u.nama_lengkap',
    4 => 't.status',
    5 => 't.metode_bayar',
    6 => 't.total',
    7 => 't.discount',
    8 => 't.total_bayar',
    9 => 't.uang_bayar',
    10 => 't.kembalian'
];
$orderBy = $columns[$orderColumnIndex] ?? 't.tanggal';

// Ambil data transaksi
$sql = "
    SELECT 
        t.no_faktur,
        t.tanggal,
        p.nama_pelanggan,
        u.nama_lengkap,
        t.status,
        t.metode_bayar,
        t.total,
        t.discount,
        t.total_bayar,
        t.uang_bayar,
        t.kembalian,
        GROUP_CONCAT(CONCAT(d.qty, 'x ', s.nama_sparepart) SEPARATOR ', ') AS daftar_barang
    $baseQuery
    GROUP BY t.no_faktur
    ORDER BY $orderBy $orderColumnDir
    LIMIT $start, $length
";
$query = mysqli_query($conn, $sql);

// Siapkan data
$data = [];
while ($row = mysqli_fetch_assoc($query)) {
    $data[] = [
        "no_faktur"     => $row['no_faktur'],
        "tanggal"       => date('d-m-Y', strtotime($row['tanggal'])),
        "nama_pelanggan"=> $row['nama_pelanggan'] ?? "-",
        "nama_lengkap"  => $row['nama_lengkap'] ?? "-",
        "status"        => $row['status'],
        "metode_bayar"  => $row['metode_bayar'],
        "total"         => number_format($row['total'], 0, ',', '.'),
        "discount"      => number_format($row['discount'], 0, ',', '.'),
        "total_bayar"   => number_format($row['total_bayar'], 0, ',', '.'),
        "uang_bayar"    => number_format($row['uang_bayar'], 0, ',', '.'),
        "kembalian"     => number_format($row['kembalian'], 0, ',', '.'),
        "daftar_barang" => $row['daftar_barang'] ?? "-"
    ];
}

// Response JSON
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
