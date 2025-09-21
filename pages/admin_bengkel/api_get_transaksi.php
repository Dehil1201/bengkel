<?php
session_start();
include "../../inc/koneksi.php"; // koneksi db

header('Content-Type: application/json');

// Validasi session user
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode([
        "status_code" => 401,
        "message" => "Unauthorized. User belum login.",
        "data" => []
    ]);
    exit;
}

// Ambil id_bengkel user
$q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user = '$id_user'");
$user = mysqli_fetch_assoc($q_user);
$id_bengkel = $user['bengkel_id'] ?? null;

if (!$id_bengkel) {
    echo json_encode([
        "status_code" => 403,
        "message" => "Bengkel tidak ditemukan untuk user.",
        "data" => []
    ]);
    exit;
}

// Parameter filter
$no_faktur = $_GET['no_faktur'] ?? '';
$jenis     = $_GET['jenis'] ?? ''; // filter baru

// ===== Kalau no_faktur kosong -> ambil list transaksi =====
if ($no_faktur == '') {
    $where = "WHERE id_bengkel = '$id_bengkel'";
    
    if ($jenis != '') {
        $where .= " AND jenis = '$jenis'";
    }

    $q = mysqli_query($conn, "SELECT * FROM transaksi $where ORDER BY tanggal DESC");
    $list_transaksi = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $list_transaksi[] = $row;
    }

    echo json_encode([
        "status_code" => 200,
        "message" => "Berhasil mengambil semua transaksi",
        "data" => $list_transaksi
    ], JSON_PRETTY_PRINT);
    exit;
}

// ===== Kalau no_faktur ada -> ambil detail transaksi =====
$where_faktur = "WHERE no_faktur='$no_faktur' AND id_bengkel = '$id_bengkel'";
if ($jenis != '') {
    $where_faktur .= " AND jenis = '$jenis'";
}

$sql_transaksi = mysqli_query($conn, "SELECT * FROM transaksi $where_faktur LIMIT 1");
$transaksi = mysqli_fetch_assoc($sql_transaksi);

// if (!$transaksi) {
//     echo json_encode([
//         "status_code" => 404,
//         "message" => "Transaksi tidak ditemukan.",
//         "data" => []
//     ], JSON_PRETTY_PRINT);
//     exit;
// }

// Ambil detail servis
$sql_detail_servis = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");
$detail_servis = [];
$total_servis = 0;
while ($row = mysqli_fetch_assoc($sql_detail_servis)) {
    $detail_servis[] = $row;
    $total_servis += $row['biaya'];
}

// Ambil detail sparepart
$sql_detail_sparepart = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
$detail_sparepart = [];
$total_sparepart = 0;
while ($row = mysqli_fetch_assoc($sql_detail_sparepart)) {
    $detail_sparepart[] = $row;
    $total_sparepart += $row['subtotal'];
}

// Total keseluruhan
$total = $total_servis + $total_sparepart;

// Output JSON
echo json_encode([
    "status_code" => 200,
    "message" => "Detail transaksi ditemukan",
    "data" => [
        "transaksi"       => $transaksi,
        "detail_servis"   => $detail_servis,
        "detail_sparepart"=> $detail_sparepart,
        "total_servis"    => $total_servis,
        "total_sparepart" => $total_sparepart,
        "total"           => $total
    ]
], JSON_PRETTY_PRINT);
