<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

// ambil parameter
$no_retur = $_GET['no_retur'] ?? '';

if ($no_retur == '') {
    echo json_encode([
        "status_code" => 400,
        "message" => "Parameter no_retur wajib diisi"
    ]);
    exit;
}

$sql = "SELECT id_detail, no_retur, kode_sparepart, nama_sparepart, qty, harga, subtotal, alasan, created_at
        FROM retur_penjualan_detail
        WHERE no_retur = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $no_retur);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

// hitung total retur
$qTotal = $conn->prepare("SELECT SUM(subtotal) as total_retur FROM retur_penjualan_detail WHERE no_retur = ?");
$qTotal->bind_param("s", $no_retur);
$qTotal->execute();
$totalRow = $qTotal->get_result()->fetch_assoc();
$total_retur = $totalRow['total_retur'] ?? 0;
$qTotal->close();

echo json_encode([
    "status_code" => 200,
    "message" => "Data detail retur ditemukan",
    "total_retur" => $total_retur,
    "data" => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
