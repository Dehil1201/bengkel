<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// Ambil parameter filter dari GET
$tanggal_dari = $_GET['tanggal_dari'] ?? null;
$tanggal_sampai = $_GET['tanggal_sampai'] ?? null;
$status = $_GET['status'] ?? null;
$id_pelanggan = $_GET['id_pelanggan'] ?? null;

// Base query
$query = "SELECT 
            p.id_piutang,
            p.no_faktur,
            p.tanggal_piutang,
            p.jumlah,
            p.status,
            p.tanggal_pelunasan,
            pl.nama_pelanggan,
            t.id_pelanggan
          FROM piutang p
          LEFT JOIN transaksi t ON p.no_faktur = t.no_faktur
          LEFT JOIN pelanggans pl ON t.id_pelanggan = pl.id_pelanggan";

// Filter dinamis
$where = [];

if ($tanggal_dari) {
    $where[] = "p.tanggal_piutang >= '$tanggal_dari'";
}
if ($tanggal_sampai) {
    $where[] = "p.tanggal_piutang <= '$tanggal_sampai'";
}
if ($status) {
    $where[] = "p.status = '$status'";
}
if ($id_pelanggan) {
    $where[] = "t.id_pelanggan = '$id_pelanggan'";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY p.tanggal_piutang DESC";

$result = mysqli_query($conn, $query);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $id_piutang = $row['id_piutang'];

    // Hitung jumlah cicilan yang sudah dibayar
    $qCicilan = mysqli_query($conn, "SELECT SUM(jumlah_bayar) AS total_bayar FROM cicilan_piutang WHERE id_piutang = '$id_piutang'");
    $cicilan = mysqli_fetch_assoc($qCicilan);
    $total_dibayar = floatval($cicilan['total_bayar'] ?? 0);

    $data[] = [
        "id_piutang" => $row['id_piutang'],
        "no_faktur" => $row['no_faktur'],
        "tanggal_piutang" => $row['tanggal_piutang'],
        "jumlah" => floatval($row['jumlah']),
        "dibayar" => $total_dibayar,
        "status" => $row['status'],
        "tanggal_pelunasan" => $row['tanggal_pelunasan'],
        "nama_pelanggan" => $row['nama_pelanggan']
    ];
}

echo json_encode($data);
