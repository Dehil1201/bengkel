<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$id_bengkel = $_SESSION['id_bengkel'] ?? 0;

$tgl_dari     = $_GET['tgl_dari'] ?? '';
$tgl_sampai   = $_GET['tgl_sampai'] ?? '';
$id_pelanggan = $_GET['id_pelanggan'] ?? '';
$id_user      = $_GET['id_user'] ?? '';
$id_teknisi   = $_GET['id_teknisi'] ?? '';

/* ================== WHERE UNTUK TRANSAKSI (TANPA JOIN) ================== */
$whereBaseTransaksi = "
    (no_faktur LIKE '%PS%')
    AND id_bengkel = '$id_bengkel'
";

if ($tgl_dari && $tgl_sampai) {
    $whereBaseTransaksi .= " AND DATE(tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_pelanggan) {
    $whereBaseTransaksi .= " AND id_pelanggan = '$id_pelanggan'";
}
if ($id_user) {
    $whereBaseTransaksi .= " AND id_user = '$id_user'";
}
if ($id_teknisi) {
    $whereBaseTransaksi .= " AND id_teknisi = '$id_teknisi'";
}

/* ================== WHERE UNTUK JOIN (PAKAI ALIAS t) ================== */
$whereBaseJoin = "
    (t.no_faktur LIKE '%PS%')
    AND t.id_bengkel = '$id_bengkel'
";

if ($tgl_dari && $tgl_sampai) {
    $whereBaseJoin .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_pelanggan) {
    $whereBaseJoin .= " AND t.id_pelanggan = '$id_pelanggan'";
}
if ($id_user) {
    $whereBaseJoin .= " AND t.id_user = '$id_user'";
}
if ($id_teknisi) {
    $whereBaseJoin .= " AND t.id_teknisi = '$id_teknisi'";
}

/* ================== TOTAL TRANSAKSI ================== */
$qTotal = mysqli_query($conn, "
    SELECT IFNULL(SUM(total_bayar),0) AS total
    FROM transaksi
    WHERE $whereBaseTransaksi
");

$totalTransaksi = mysqli_fetch_assoc($qTotal)['total'] ?? 0;

/* ================== TOTAL SPAREPART ================== */
$qSparepart = mysqli_query($conn, "
    SELECT IFNULL(SUM(ts.subtotal),0) AS total
    FROM transaksi_detail_sparepart ts
    JOIN transaksi t ON ts.no_faktur = t.no_faktur
    WHERE $whereBaseJoin
");

$totalSparepart = mysqli_fetch_assoc($qSparepart)['total'] ?? 0;

/* ================== TOTAL SERVIS ================== */
$qServis = mysqli_query($conn, "
    SELECT IFNULL(SUM(td.biaya),0) AS total
    FROM transaksi_detail_servis td
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    WHERE $whereBaseJoin
");

$totalServis = mysqli_fetch_assoc($qServis)['total'] ?? 0;

echo json_encode([
    'success' => true,
    'data' => [
        'total_transaksi' => (int)$totalTransaksi,
        'total_sparepart' => (int)$totalSparepart,
        'total_servis'    => (int)$totalServis,
    ]
]);
