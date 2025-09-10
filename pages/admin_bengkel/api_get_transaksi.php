<?php
include "../../inc/koneksi.php"; // koneksi db

header('Content-Type: application/json');

$no_faktur = isset($_GET['no_faktur']) ? $_GET['no_faktur'] : '';

if ($no_faktur == '') {
    echo json_encode([
        "status_code" => 200,
        "data" => []
    ], JSON_PRETTY_PRINT);
    exit;
}

// Ambil transaksi header (opsional jika perlu)
$sql_transaksi = mysqli_query($conn, "SELECT * FROM transaksi WHERE no_faktur='$no_faktur' LIMIT 1");
$transaksi = mysqli_fetch_assoc($sql_transaksi);

// Ambil detail servis
$sql_detail_servis = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");
$detail_servis = [];
$total_servis = 0;
while ($row = mysqli_fetch_assoc($sql_detail_servis)) {
    $detail_servis[] = $row;
    $total_servis += $row['biaya']; // jumlah total servis
}

// Ambil detail sparepart
$sql_detail_sparepart = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
$detail_sparepart = [];
$total_sparepart = 0;
while ($row = mysqli_fetch_assoc($sql_detail_sparepart)) {
    $detail_sparepart[] = $row;
    $total_sparepart += $row['subtotal']; // jumlah total sparepart
}

// Total keseluruhan
$total = $total_servis + $total_sparepart;

// Output JSON
echo json_encode([
    "status_code" => 200,
    "data" => [
        "transaksi" => $transaksi,
        "detail_servis" => $detail_servis,
        "detail_sparepart" => $detail_sparepart,
        "total_sparepart" => $total_sparepart,
        "total_servis" => $total_servis,
        "total" => $total
    ]
], JSON_PRETTY_PRINT);
