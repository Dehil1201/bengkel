<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// =============================
// FUNCTION GENERATE FAKTUR
// =============================
function generateNoFaktur($conn, $tanggal_input) {
    $id_user = $_SESSION['id_user'];

    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $prefix = "PJ." . date("Ymd", strtotime($tanggal_input)) . "." . $id_user . "." . $id_bengkel;

    $q = mysqli_query($conn, "
        SELECT COUNT(*) as total 
        FROM transaksi 
        WHERE DATE(tanggal) = '$tanggal_input'
        AND id_user = '$id_user'
        AND id_bengkel = '$id_bengkel'
        AND jenis = 'penjualan'
    ");

    $row = mysqli_fetch_assoc($q);
    $no_urut = str_pad($row['total'] + 1, 4, "0", STR_PAD_LEFT);

    return $prefix . "." . $no_urut;
}

// =============================
// PROSES REQUEST
// =============================
$tanggal = $_POST['tanggal'] ?? date('Y-m-d');

$no_faktur = generateNoFaktur($conn, $tanggal);

echo json_encode([
    "status_code" => 200,
    "no_faktur" => $no_faktur
]);
