<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json');

// simple cache 30s
$_SESSION['dashboard_cache_time'] ??= 0;
if (time() - $_SESSION['dashboard_cache_time'] < 30 && !empty($_SESSION['dashboard_cache_data'])) {
    echo json_encode($_SESSION['dashboard_cache_data']);
    exit;
}

// ==== VALIDASI LOGIN ====
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode([
        "status" => 401,
        "message" => "Unauthorized"
    ]);
    exit;
}

// ==== AMBIL BENGKEL USER ====
$q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user = '$id_user'");
$user = mysqli_fetch_assoc($q_user);
$id_bengkel = $user['bengkel_id'] ?? null;

if (!$id_bengkel) {
    echo json_encode([
        "status" => 403,
        "message" => "Bengkel tidak ditemukan"
    ]);
    exit;
}

// =====================================================
// 1. STATISTIK DASAR
// =====================================================
// total spareparts
$total_spareparts = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM spareparts 
    WHERE bengkel_id = '$id_bengkel'
"))['total'] ?? 0);

// total pelanggan
$total_pelanggan = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM pelanggans 
    WHERE bengkel_id = '$id_bengkel'
"))['total'] ?? 0);

// tanggal range bulan (untuk performa)
$start_bulan = date('Y-m-01', strtotime('-11 months'));
$end_bulan   = date('Y-m-t');

// omset bulan ini (pakai rentang untuk index)
$start_this_month = date('Y-m-01');
$end_this_month   = date('Y-m-t');

$omset_bulan_ini = (float) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total),0) AS total
    FROM transaksi
    WHERE tanggal BETWEEN '$start_this_month' AND '$end_this_month'
      AND id_bengkel = '$id_bengkel'
"))['total'] ?? 0);

// laba bulan ini (pakai rentang; COALESCE untuk safety)
$laba_bulan_ini = (float) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(COALESCE(td.subtotal,0) - (COALESCE(td.qty,0) * COALESCE(s.hpp_per_pcs,0))),0) AS laba
    FROM transaksi_detail_sparepart td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    LEFT JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
    WHERE t.tanggal BETWEEN '$start_this_month' AND '$end_this_month'
      AND t.id_bengkel = '$id_bengkel'
"))['laba'] ?? 0);

// =====================================================
// 2. GRAFIK BULANAN 12 BULAN (omset + laba)
// =====================================================
$sql_bulanan = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(t.tanggal, '%b %Y') AS label,
        SUM(COALESCE(t.total,0)) AS omset,
        SUM(COALESCE(td.subtotal,0) - (COALESCE(td.qty,0) * COALESCE(s.hpp_per_pcs,0))) AS laba,
        MIN(t.tanggal) as min_tanggal
    FROM transaksi t
    LEFT JOIN transaksi_detail_sparepart td ON t.no_faktur = td.no_faktur
    LEFT JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
    WHERE t.tanggal BETWEEN '$start_bulan' AND '$end_bulan'
      AND t.id_bengkel = '$id_bengkel'
    GROUP BY label
    ORDER BY min_tanggal
");

$labels_penjualan = [];
$data_omset_per_bulan = [];
$data_laba_per_bulan = [];

while ($row = mysqli_fetch_assoc($sql_bulanan)) {
    $labels_penjualan[] = $row['label'];
    $data_omset_per_bulan[] = (float)$row['omset'];
    $data_laba_per_bulan[]  = (float)$row['laba'];
}

// =====================================================
// 3. GRAFIK HARIAN BULAN INI (TOTAL NOMINAL per hari)
// =====================================================
$start_hari = date('Y-m-01');
$end_hari   = date('Y-m-t');

$sql_harian = mysqli_query($conn, "
    SELECT DAY(tanggal) AS hari, SUM(COALESCE(total,0)) AS total_nominal
    FROM transaksi
    WHERE tanggal BETWEEN '$start_hari' AND '$end_hari'
      AND id_bengkel = '$id_bengkel'
    GROUP BY hari
");

$labels_harian = range(1, date('t'));
$data_transaksi_harian = array_fill(1, date('t'), 0.0);

while ($row = mysqli_fetch_assoc($sql_harian)) {
    $data_transaksi_harian[(int)$row['hari']] = (float)$row['total_nominal'];
}
$data_transaksi_harian = array_values($data_transaksi_harian);

// =====================================================
// 4. STOK LIMIT
// =====================================================
$stok_limit = 5;
$query_stok_limit = mysqli_query($conn, "
    SELECT kode_sparepart, nama_sparepart, stok_pcs
    FROM spareparts
    WHERE stok_pcs <= $stok_limit 
      AND bengkel_id = '$id_bengkel'
    ORDER BY stok_pcs ASC
    LIMIT 10
");

$stok_limit_data = [];
while ($row = mysqli_fetch_assoc($query_stok_limit)) {
    $stok_limit_data[] = $row;
}

// =====================================================
// 5. BARANG TERLARIS (batasi rentang 12 bulan untuk performa)
// =====================================================
$query_barang_terlaris = mysqli_query($conn, "
    SELECT s.kode_sparepart, s.nama_sparepart, SUM(td.qty) AS total_terjual
    FROM transaksi_detail_sparepart td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
    WHERE t.id_bengkel = '$id_bengkel'
      AND t.tanggal BETWEEN '$start_bulan' AND '$end_bulan'
    GROUP BY s.kode_sparepart, s.nama_sparepart
    ORDER BY total_terjual DESC
    LIMIT 10
");

$barang_terlaris = [];
while ($row = mysqli_fetch_assoc($query_barang_terlaris)) {
    $barang_terlaris[] = $row;
}

// =====================================================
// BUILD RESPONSE AND CACHE
// =====================================================
$response_array = [
    "status" => 200,
    "summary" => [
        "total_spareparts"   => $total_spareparts,
        "total_pelanggan"    => $total_pelanggan,
        "omset_bulan_ini"    => $omset_bulan_ini,
        "laba_bulan_ini"     => $laba_bulan_ini
    ],
    "grafik_bulanan" => [
        "labels" => $labels_penjualan,
        "omset"  => $data_omset_per_bulan,
        "laba"   => $data_laba_per_bulan
    ],
    "grafik_harian" => [
        "labels" => $labels_harian,
        "data"   => $data_transaksi_harian
    ],
    "stok_limit" => $stok_limit_data,
    "barang_terlaris" => $barang_terlaris
];

// simpan cache
$_SESSION['dashboard_cache_time'] = time();
$_SESSION['dashboard_cache_data'] = $response_array;

// output
echo json_encode($response_array, JSON_PRETTY_PRINT);
