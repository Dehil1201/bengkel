<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// Validasi login
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode(["error" => "Session expired"]);
    exit;
}

// Ambil bengkel user
$qB = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$dB = mysqli_fetch_assoc($qB);
$id_bengkel = $dB['bengkel_id'];

$tgl_mulai   = $_POST['tgl_mulai'] ?? "";
$tgl_selesai = $_POST['tgl_selesai'] ?? "";

// Filter tanggal
$filterTanggal = "";
if ($tgl_mulai != "" && $tgl_selesai != "") {
    $filterTanggal = " AND DATE(tanggal) BETWEEN '$tgl_mulai' AND '$tgl_selesai' ";
}

// ====================
// HITUNG TOTAL PENJUALAN
// ====================
$qJual = mysqli_query($conn, "
    SELECT SUM(total) AS total
    FROM transaksi
    WHERE jenis = 'penjualan'
    AND id_bengkel = '$id_bengkel'
    $filterTanggal
");
$dJual = mysqli_fetch_assoc($qJual);
$total_penjualan = (float)($dJual['total'] ?? 0);

// ====================
// HITUNG TOTAL PEMBELIAN
// ====================
$qBeli = mysqli_query($conn, "
    SELECT SUM(total) AS total
    FROM transaksi
    WHERE jenis = 'pembelian'
    AND id_bengkel = '$id_bengkel'
    $filterTanggal
");
$dBeli = mysqli_fetch_assoc($qBeli);
$total_pembelian = (float)($dBeli['total'] ?? 0);

// ====================
// SISa SALDO
// ====================
$sisa_saldo = $total_penjualan - $total_pembelian;

// ====================
// HITUNG TOTAL HPP
// ====================
// Ambil total HPP dari transaksi penjualan
$qHpp = mysqli_query($conn, "
    SELECT SUM(d.qty * s.harga_beli) AS total_hpp
    FROM transaksi t
    JOIN transaksi_detail_sparepart d ON d.no_faktur = t.no_faktur
    JOIN spareparts s ON d.kode_sparepart = s.kode_sparepart
    WHERE t.jenis = 'penjualan'
    AND t.id_bengkel = '$id_bengkel'
    $filterTanggal
");
$dHpp = mysqli_fetch_assoc($qHpp);
$total_hpp = (float)($dHpp['total_hpp'] ?? 0);


// ====================
// RESPONSE
// ====================
echo json_encode([
    "total_penjualan" => $total_penjualan,
    "total_pembelian" => $total_pembelian,
    "sisa_saldo" => $sisa_saldo,
    "total_hpp" => $total_hpp,

    // Format Rupiah
    "total_penjualan_formatted" => "Rp " . number_format($total_penjualan, 0, ',', '.'),
    "total_pembelian_formatted" => "Rp " . number_format($total_pembelian, 0, ',', '.'),
    "sisa_saldo_formatted" => "Rp " . number_format($sisa_saldo, 0, ',', '.'),
    "total_hpp_formatted" => "Rp " . number_format($total_hpp, 0, ',', '.')
]);
