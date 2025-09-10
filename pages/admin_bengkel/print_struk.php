<?php
include "../../inc/koneksi.php";

$no_faktur = $_GET['no_faktur'] ?? '';

// Ambil header transaksi
$sql = mysqli_query($conn, "SELECT t.*, p.nama_pelanggan, te.nama_teknisi, b.nama_bengkel, b.alamat, b.telepon
                            FROM transaksi t
                            LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
                            LEFT JOIN teknisis te ON t.id_teknisi = te.id_teknisi
                            LEFT JOIN bengkels b ON t.id_bengkel = b.id_bengkel
                            WHERE no_faktur='$no_faktur' LIMIT 1");
$transaksi = mysqli_fetch_assoc($sql);

// Ambil detail servis
$servis = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");

// Ambil detail sparepart
$sparepart = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Struk <?= $no_faktur ?></title>
  <style>
    body {
      font-family: monospace;
      font-size: 12px;
      width: 250px; /* ±58mm */
      margin: 0 auto;
    }
    .center {
      text-align: center;
    }
    .line {
      border-top: 1px dashed #000;
      margin: 5px 0;
    }
    table {
      width: 100%;
    }
    table td {
      vertical-align: top;
    }
    .right {
      text-align: right;
    }
  </style>
</head>
<body onload="printAndBack()">

  <div class="center">
    <strong><?= $transaksi['nama_bengkel'] ?></strong><br>
    <?= $transaksi['alamat'] ?><br>
    Telp: <?= $transaksi['telepon'] ?>
  </div>

  <div class="line"></div>
  <table>
    <tr>
      <td>No Faktur</td><td>:</td><td><?= $transaksi['no_faktur'] ?></td>
    </tr>
    <tr>
      <td>Tanggal</td><td>:</td><td><?= $transaksi['tanggal'] ?></td>
    </tr>
    <tr>
      <td>Pelanggan</td><td>:</td><td><?= $transaksi['nama_pelanggan'] ?: '-' ?></td>
    </tr>
    <tr>
      <td>Teknisi</td><td>:</td><td><?= $transaksi['nama_teknisi'] ?: '-' ?></td>
    </tr>
  </table>
  <div class="line"></div>

  <strong>Servis:</strong><br>
  <table>
    <?php 
    $total_servis = 0;
    while($s = mysqli_fetch_assoc($servis)){ 
      $total_servis += $s['biaya']; ?>
      <tr>
        <td colspan="2"><?= $s['nama_servis'] ?></td>
        <td class="right"><?= number_format($s['biaya'],0,',','.') ?></td>
      </tr>
    <?php } ?>
  </table>

  <strong>Sparepart:</strong><br>
  <table>
    <?php 
    $total_sparepart = 0;
    while($sp = mysqli_fetch_assoc($sparepart)){ 
      $total_sparepart += $sp['subtotal']; ?>
      <tr>
        <td><?= $sp['nama_sparepart'] ?></td>
        <td><?= $sp['qty'] ?>x</td>
        <td class="right"><?= number_format($sp['subtotal'],0,',','.') ?></td>
      </tr>
    <?php } ?>
  </table>
  <div class="line"></div>

  <table>
    <tr>
      <td>Total Servis</td>
      <td class="right"><?= number_format($total_servis,0,',','.') ?></td>
    </tr>
    <tr>
      <td>Total Sparepart</td>
      <td class="right"><?= number_format($total_sparepart,0,',','.') ?></td>
    </tr>
    <tr>
      <td><strong>Grand Total</strong></td>
      <td class="right"><strong><?= number_format($transaksi['total'],0,',','.') ?></strong></td>
    </tr>
  </table>
  <div class="line"></div>

  <div class="center">
    Terima Kasih<br>
    --- Semoga puas dengan layanan kami ---
  </div>

  <script>
    function printAndBack(){
      window.print();
      window.onafterprint = function(){
        window.history.back();
      };
    }
  </script>
</body>
</html>
