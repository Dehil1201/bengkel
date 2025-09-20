<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// Ambil parameter filter dari GET
$tanggal_dari = $_GET['tanggal_dari'] ?? null;
$tanggal_sampai = $_GET['tanggal_sampai'] ?? null;
$status = $_GET['status'] ?? null;
$id_supplier = $_GET['id_supplier'] ?? null;

// Base query
$query = "SELECT 
            h.id_hutang,
            h.no_faktur,
            h.tanggal_hutang,
            h.jumlah,
            h.status,
            h.tanggal_pelunasan,
            pl.nama_supplier,
            t.id_supplier
          FROM hutang h
          LEFT JOIN transaksi t ON h.no_faktur = t.no_faktur
          LEFT JOIN suppliers pl ON t.id_supplier = pl.id_supplier";

// Filter dinamis
$where = [];

if ($tanggal_dari) {
    $where[] = "h.tanggal_hutang >= '$tanggal_dari'";
}
if ($tanggal_sampai) {
    $where[] = "h.tanggal_hutang <= '$tanggal_sampai'";
}
if ($status) {
    $where[] = "h.status = '$status'";
}
if ($id_supplier) {
    $where[] = "t.id_supplier = '$id_supplier'";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY h.tanggal_hutang DESC";

$result = mysqli_query($conn, $query);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $id_hutang = $row['id_hutang'];

    // Hitung jumlah cicilan yang sudah dibayar
    $qCicilan = mysqli_query($conn, "SELECT SUM(jumlah_bayar) AS total_bayar FROM cicilan_hutang WHERE id_hutang = '$id_hutang'");
    $cicilan = mysqli_fetch_assoc($qCicilan);
    $total_dibayar = floatval($cicilan['total_bayar'] ?? 0);

    $data[] = [
        "id_hutang" => $row['id_hutang'],
        "no_faktur" => $row['no_faktur'],
        "tanggal_hutang" => $row['tanggal_hutang'],
        "jumlah" => floatval($row['jumlah']),
        "dibayar" => $total_dibayar,
        "status" => $row['status'],
        "tanggal_pelunasan" => $row['tanggal_pelunasan'],
        "nama_supplier" => $row['nama_supplier']
    ];
}

echo json_encode($data);
