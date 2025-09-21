<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

$id_detail = $_POST['id_detail'] ?? '';

if ($id_detail == '') {
    echo json_encode([
        "status_code" => 400,
        "message" => "ID detail tidak boleh kosong"
    ]);
    exit;
}

// ambil no_retur dulu supaya bisa hitung total setelah delete
$sqlGet = $conn->prepare("SELECT no_retur FROM retur_penjualan_detail WHERE id_detail = ?");
$sqlGet->bind_param("i", $id_detail);
$sqlGet->execute();
$res = $sqlGet->get_result();
$row = $res->fetch_assoc();
$sqlGet->close();

if (!$row) {
    echo json_encode([
        "status_code" => 404,
        "message" => "Data detail retur tidak ditemukan"
    ]);
    exit;
}

$no_retur = $row['no_retur'];

// hapus detail retur
$sqlDel = $conn->prepare("DELETE FROM retur_penjualan_detail WHERE id_detail = ?");
$sqlDel->bind_param("i", $id_detail);
if (!$sqlDel->execute()) {
    echo json_encode([
        "status_code" => 500,
        "message" => "Gagal menghapus detail retur"
    ]);
    exit;
}
$sqlDel->close();

// hitung ulang total retur
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
    "message" => "Detail retur berhasil dihapus",
    "data" => ["total_retur" => $total_retur]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

