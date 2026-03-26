<?php
include '../../inc/koneksi.php';

$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

$startDate = $_POST['startDate'] ?? '';
$endDate   = $_POST['endDate'] ?? '';
$merk      = $_POST['merk'] ?? '';

// filter dinamis
$where = "WHERE 1=1";

if (!empty($startDate) && !empty($endDate)) {
    $where .= " AND DATE(tanggal) BETWEEN '$startDate' AND '$endDate'";
}

if (!empty($merk)) {
    $where .= " AND sp.merk_id = '$merk'";
}

// query utama
$query = mysqli_query($conn, "
    SELECT 
        sp.kode_sparepart,
        sp.nama_sparepart,
        SUM(d.qty) as terjual
    FROM transaksi_detail_sparepart d
    JOIN transaksi t ON d.no_faktur = t.no_faktur
    JOIN spareparts sp ON d.kode_sparepart = sp.kode_sparepart
    $where
    GROUP BY sp.kode_sparepart
    ORDER BY terjual DESC
    LIMIT $start, $length
");

$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($query)) {
    $row['no'] = $no++;
    $data[] = $row;
}

// total data
$totalQuery = mysqli_query($conn, "
    SELECT COUNT(DISTINCT sp.id_sparepart) as total
    FROM transaksi_detail_sparepart d
    JOIN transaksi t ON d.no_faktur = t.no_faktur
    JOIN spareparts sp ON d.kode_sparepart = sp.kode_sparepart
    $where
");

$total = mysqli_fetch_assoc($totalQuery)['total'];

echo json_encode([
    "draw" => $_POST['draw'],
    "recordsTotal" => $total,
    "recordsFiltered" => $total,
    "data" => $data
]);