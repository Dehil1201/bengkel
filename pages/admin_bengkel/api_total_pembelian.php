<?php
session_start();
include "../../inc/koneksi.php";

$id_bengkel = $_SESSION['id_bengkel'];

$tgl_dari = $_GET['tgl_dari'];
$tgl_sampai = $_GET['tgl_sampai'];
$id_supplier = $_GET['id_supplier'];
$id_user = $_GET['id_user'];

$where = "WHERE jenis='pembelian' AND id_bengkel='$id_bengkel'";

if ($tgl_dari && $tgl_sampai)
    $where .= " AND DATE(tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
if ($id_supplier)
    $where .= " AND id_supplier='$id_supplier'";
if ($id_user)
    $where .= " AND id_user='$id_user'";

$q = mysqli_query($conn, "SELECT SUM(total) total FROM transaksi $where");
$total = mysqli_fetch_assoc($q)['total'] ?? 0;

echo json_encode([
    "total" => $total,
    "total_format" => "Rp ".number_format($total,0,',','.')
]);
