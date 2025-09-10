<?php
include "../../inc/koneksi.php"; // koneksi db
header('Content-Type: application/json');

$sql = "SELECT t.*, 
            p.nama_pelanggan, 
            te.nama_teknisi
        FROM transaksi t
        LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
        LEFT JOIN teknisis te ON t.id_teknisi = te.id_teknisi
        WHERE t.status='pending'
        ORDER BY t.tanggal DESC";

$result = mysqli_query($conn, $sql);
$data = [];

while($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id_transaksi' => $row['id_transaksi'],
        'no_faktur' => $row['no_faktur'],
        'tanggal' => $row['tanggal'],
        'total' => $row['total'],
        'pelanggan' => $row['nama_pelanggan'] ?? '-',
        'teknisi' => $row['nama_teknisi'] ?? '-',
        'kendaraan' => $row['kendaraan'] ?? '-',
        'no_polisi' => $row['no_polisi'] ?? '-'
    ];
}

echo json_encode([
    "status_code" => 200,
    "data" => $data
], JSON_PRETTY_PRINT);
