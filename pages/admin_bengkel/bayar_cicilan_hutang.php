<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// Ambil data POST
$id_hutang     = $_POST['id_hutang'] ?? null;
$tanggal_bayar  = $_POST['tanggal_bayar'] ?? null;
$jumlah_bayar   = floatval($_POST['jumlah_bayar'] ?? 0);
$metode_bayar   = $_POST['metode_bayar'] ?? 'Tunai';
$keterangan     = $_POST['keterangan'] ?? null;

// Validasi wajib
if (!$id_hutang || !$tanggal_bayar || $jumlah_bayar <= 0) {
    echo json_encode([
        "status_code" => 400,
        "message" => "Data tidak lengkap atau jumlah bayar tidak valid."
    ]);
    exit;
}

// Ambil data hutang terkait
$q = mysqli_query($conn, "SELECT jumlah FROM hutang WHERE id_hutang = '$id_hutang'");
if (mysqli_num_rows($q) == 0) {
    echo json_encode([
        "status_code" => 404,
        "message" => "Data hutang tidak ditemukan."
    ]);
    exit;
}

$hutang = mysqli_fetch_assoc($q);
$total_hutang = floatval($hutang['jumlah']);

// Hitung total yang sudah dibayar sebelumnya
$qCicilan = mysqli_query($conn, "SELECT SUM(jumlah_bayar) AS total_dibayar FROM cicilan_hutang WHERE id_hutang = '$id_hutang'");
$dibayar = floatval(mysqli_fetch_assoc($qCicilan)['total_dibayar'] ?? 0);

$sisa_hutang = $total_hutang - $dibayar;

// Cek apakah jumlah_bayar melebihi sisa
if ($jumlah_bayar > $sisa_hutang) {
    echo json_encode([
        "status_code" => 400,
        "message" => "Jumlah bayar melebihi sisa hutang."
    ]);
    exit;
}

// Simpan ke tabel cicilan_hutang
$insert = mysqli_query($conn, "INSERT INTO cicilan_hutang 
    (id_hutang, tanggal_bayar, jumlah_bayar, metode_bayar, keterangan) VALUES (
    '$id_hutang', '$tanggal_bayar', '$jumlah_bayar', '$metode_bayar', ".($keterangan ? "'$keterangan'" : "NULL")."
)");

if (!$insert) {
    echo json_encode([
        "status_code" => 500,
        "message" => "Gagal menyimpan cicilan: " . mysqli_error($conn)
    ]);
    exit;
}
$id_cicilan = mysqli_insert_id($conn);
// Cek apakah sudah lunas
$dibayarBaru = $dibayar + $jumlah_bayar;
if ($dibayarBaru >= $total_hutang) {
    $update = mysqli_query($conn, "UPDATE hutang 
        SET status = 'lunas', tanggal_pelunasan = '$tanggal_bayar' 
        WHERE id_hutang = '$id_hutang'");
    if (!$update) {
        echo json_encode([
            "status_code" => 500,
            "message" => "Gagal mengupdate status hutang: " . mysqli_error($conn)
        ]);
        exit;
    }
}

echo json_encode([
    "status_code" => 200,
    "message" => "Cicilan berhasil disimpan.",
    "id_cicilan" => $id_cicilan
]);
