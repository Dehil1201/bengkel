<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

$no_retur       = $_POST['no_retur'] ?? '';
$kode_sparepart = $_POST['kode_sparepart'] ?? '';
$nama_sparepart = $_POST['nama_sparepart'] ?? '';
$qty            = intval($_POST['qty'] ?? 0);
$harga          = intval($_POST['harga'] ?? 0);
$alasan         = $_POST['alasan'] ?? '';

if ($no_retur == '' || $kode_sparepart == '' || $qty <= 0) {
    echo json_encode(["status_code" => 400, "message" => "Data tidak lengkap"]);
    exit;
}

$subtotal = $qty * $harga;

// cek apakah sparepart sudah ada di retur yang sama
$sql = "SELECT id_detail, qty FROM retur_penjualan_detail 
        WHERE no_retur = ? AND kode_sparepart = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $no_retur, $kode_sparepart);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // UPDATE qty + subtotal
    $newQty = $row['qty'] + $qty;
    $newSubtotal = $newQty * $harga;

    $update = $conn->prepare("UPDATE retur_penjualan_detail 
                              SET qty = ?, subtotal = ?, alasan = ? 
                              WHERE id_detail = ?");
    $update->bind_param("iisi", $newQty, $newSubtotal, $alasan, $row['id_detail']);
    $update->execute();
    $update->close();
} else {
    // INSERT baru
    $insert = $conn->prepare("INSERT INTO retur_penjualan_detail 
        (no_retur, kode_sparepart, nama_sparepart, qty, harga, subtotal, alasan) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("sssiiis", $no_retur, $kode_sparepart, $nama_sparepart, $qty, $harga, $subtotal, $alasan);
    $insert->execute();
    $insert->close();
}
$stmt->close();

// hitung total retur untuk no_retur ini
$qTotal = $conn->prepare("SELECT SUM(subtotal) AS total_retur 
                          FROM retur_penjualan_detail 
                          WHERE no_retur = ?");
$qTotal->bind_param("s", $no_retur);
$qTotal->execute();
$totalRow = $qTotal->get_result()->fetch_assoc();
$total_retur = $totalRow['total_retur'] ?? 0;
$qTotal->close();

echo json_encode([
    "status_code" => 200,
    "message" => "Detail retur berhasil disimpan",
    "data" => ["total_retur" => $total_retur]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
