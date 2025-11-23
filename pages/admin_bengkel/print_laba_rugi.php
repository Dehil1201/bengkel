<?php
session_start();
include "../../inc/koneksi.php";

// Validasi login
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo "Session expired";
    exit;
}

// Ambil bengkel user
$qB = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$dB = mysqli_fetch_assoc($qB);
$id_bengkel = $dB['bengkel_id'];

// Ambil tanggal dari query string
$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// Filter tanggal
$filterTanggal = " AND DATE(tanggal) BETWEEN '$tgl_mulai' AND '$tgl_selesai' ";

// Total penjualan
$qJual = mysqli_query($conn, "
    SELECT SUM(total) AS total
    FROM transaksi
    WHERE jenis='penjualan' AND id_bengkel='$id_bengkel' $filterTanggal
");
$dJual = mysqli_fetch_assoc($qJual);
$total_penjualan = (float)($dJual['total'] ?? 0);

// Total pembelian
$qBeli = mysqli_query($conn, "
    SELECT SUM(total) AS total
    FROM transaksi
    WHERE jenis='pembelian' AND id_bengkel='$id_bengkel' $filterTanggal
");
$dBeli = mysqli_fetch_assoc($qBeli);
$total_pembelian = (float)($dBeli['total'] ?? 0);

// Total HPP (bisa disesuaikan jika HPP ada tabel terpisah)
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

// Laba/Rugi = Penjualan - (Pembelian + HPP)
$laba_rugi = $total_penjualan - $total_hpp;

// Format Rupiah
function rupiah($val) {
    return "Rp " . number_format($val, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Laba Rugi</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: right; }
        th { background: #f0f0f0; }
        td.left { text-align: left; }
    </style>
</head>
<body>
    <h2>Laporan Laba Rugi</h2>
    <p>Periode: <?= $tgl_mulai ?> s/d <?= $tgl_selesai ?></p>
    <table>
        <tr>
            <th class="left">Keterangan</th>
            <th>Jumlah</th>
        </tr>
        <tr>
            <td class="left">Total Penjualan</td>
            <td><?= rupiah($total_penjualan) ?></td>
        </tr>
        <tr>
            <td class="left">Total HPP</td>
            <td><?= rupiah($total_hpp) ?></td>
        </tr>
        <tr>
            <td class="left"><strong>Laba / Rugi</strong></td>
            <td><strong><?= rupiah($laba_rugi) ?></strong></td>
        </tr>
    </table>

    <script>
        window.print();
        window.onafterprint = function(){ window.close(); };
    </script>
</body>
</html>
