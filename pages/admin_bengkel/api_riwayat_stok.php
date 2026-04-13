<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include "../../inc/koneksi.php";

// ========================
// PARAMETER DATATABLES
// ========================
$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? "";

// ========================
// FILTER
// ========================
$minDate = $_GET['minDate'] ?? "";
$maxDate = $_GET['maxDate'] ?? "";
$jenis   = $_GET['jenis'] ?? "pembelian"; // pembelian / penjualan
$id_bengkel = $_SESSION['id_bengkel'];

// ========================
// WHERE CONDITION
// ========================
$where = " WHERE t.jenis = '$jenis' and t.id_bengkel = '$id_bengkel' ";

if ($search != "") {
    $where .= " AND (
        sp.nama_sparepart LIKE '%$search%' OR
        td.no_faktur LIKE '%$search%'
    )";
}

if ($minDate != "" && $maxDate != "") {
    $where .= " AND DATE(t.tanggal) BETWEEN '$minDate' AND '$maxDate'";
}

// ========================
// TOTAL DATA
// ========================
$totalData = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM transaksi_detail_sparepart td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    JOIN spareparts sp ON td.kode_sparepart = sp.kode_sparepart
    WHERE t.jenis = '$jenis'
"))['total'];

$totalFiltered = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM transaksi_detail_sparepart td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    JOIN spareparts sp ON td.kode_sparepart = sp.kode_sparepart
    $where
"))['total'];

// ========================
// DATA UTAMA
// ========================
$query = "
    SELECT 
        t.tanggal,
        sp.nama_sparepart,
        td.qty,
        td.no_faktur
    FROM transaksi_detail_sparepart td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    JOIN spareparts sp ON td.kode_sparepart = sp.kode_sparepart
    $where
    ORDER BY t.tanggal DESC
    LIMIT $start, $length
";

$result = mysqli_query($conn, $query);

$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        $no++,
        date('d-m-Y', strtotime($row['tanggal'])),
        $row['nama_sparepart'],
        $row['qty'],
        $row['no_faktur']
    ];
}

// ========================
// RESPONSE JSON
// ========================
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
