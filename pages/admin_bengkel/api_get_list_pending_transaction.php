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
$jenis = $_GET['jenis_faktur'] ?? '';

if (!$id_bengkel) {
    echo json_encode([
        "status_code" => 403,
        "message" => "Bengkel tidak ditemukan untuk user.",
        "data" => []
    ]);
    exit;
}

$sql = "SELECT t.*, 
            p.nama_pelanggan, 
            te.nama_teknisi,
            s.nama_supplier
        FROM transaksi t
        LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
        LEFT JOIN teknisis te ON t.id_teknisi = te.id_teknisi
        LEFT JOIN suppliers s on t.id_supplier = s.id_supplier
        WHERE t.status = 'pending'
          AND t.id_bengkel = '$id_bengkel'
          AND t.no_faktur LIKE '%$jenis%'
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
        'supplier' => $row['nama_supplier'] ?? '-',
        'teknisi' => $row['nama_teknisi'] ?? '-',
        'kendaraan' => $row['kendaraan'] ?? '-',
        'no_polisi' => $row['no_polisi'] ?? '-'
    ];
}

echo json_encode([
    "status_code" => 200,
    "data" => $data
], JSON_PRETTY_PRINT);
