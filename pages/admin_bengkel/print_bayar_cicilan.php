<?php
include "../../inc/koneksi.php";

// Ambil id_cicilan dari GET
$id_cicilan = isset($_GET['id_cicilan']) ? intval($_GET['id_cicilan']) : 0;
$auto_print = isset($_GET['auto_print']) ? intval($_GET['auto_print']) : 0;

if ($id_cicilan <= 0) {
    echo "ID cicilan tidak valid.";
    exit;
}

// Query ambil data cicilan + piutang + pelanggan + transaksi + bengkel + users
$query = mysqli_query($conn, "
    SELECT 
        c.id_cicilan,
        c.tanggal_bayar,
        c.jumlah_bayar,
        c.metode_bayar,
        c.keterangan,
        p.id_piutang,
        p.no_faktur,
        p.jumlah AS total_piutang,
        pl.nama_pelanggan,
        b.nama_bengkel,
        b.alamat,
        b.telepon,
        u.nama_lengkap
    FROM cicilan_piutang c
    JOIN piutang p ON c.id_piutang = p.id_piutang
    JOIN transaksi t ON p.no_faktur = t.no_faktur
    JOIN pelanggans pl ON t.id_pelanggan = pl.id_pelanggan
    JOIN bengkels b ON t.id_bengkel = b.id_bengkel
    JOIN users u ON t.id_user = u.id_user
    WHERE c.id_cicilan = $id_cicilan
");

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "Data cicilan tidak ditemukan.";
    exit;
}

function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Hitung jumlah cicilan yang sudah dibayar sebelum cicilan ini
$query_cicilan_sebelumnya = mysqli_query($conn, "
    SELECT IFNULL(SUM(jumlah_bayar), 0) AS total_cicilan_sebelumnya
    FROM cicilan_piutang
    WHERE id_piutang = {$data['id_piutang']}
      AND id_cicilan < $id_cicilan
");

$data_cicilan_sebelumnya = mysqli_fetch_assoc($query_cicilan_sebelumnya);
$total_cicilan_sebelumnya = $data_cicilan_sebelumnya['total_cicilan_sebelumnya'];

// Hitung sub total (piutang sebelum bayar cicilan ini)
$sub_total = $data['total_piutang'] - $total_cicilan_sebelumnya;

// Hitung sisa piutang setelah bayar cicilan ini
$sisa_piutang = $sub_total - $data['jumlah_bayar'];
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Invoice Pembayaran Cicilan</title>
  <style>
    /* Reset dan dasar */
    body {
      font-family: 'Arial', sans-serif;
      padding: 30px;
      color: #000;
      background: #fff;
      font-size: 14px;
    }
    h1 {
      font-weight: 700;
      font-size: 28px;
      margin: 0;
    }
    p {
      margin: 4px 0;
    }
    .container {
      max-width: 700px;
      margin: auto;
      border: 1px solid #ddd;
      padding: 25px;
    }
    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      border-bottom: 1px solid #000;
      padding-bottom: 10px;
    }
    .header .logo {
      font-weight: 700;
      font-size: 24px;
    }
    .header .tagline {
      font-size: 12px;
      font-weight: 500;
      text-align: right;
    }

    /* Info pelanggan dan tanggal */
    .info-section {
      display: flex;
      justify-content: space-between;
      margin: 20px 0;
    }
    .info-left, .info-right {
      width: 48%;
    }
    .info-left strong, .info-right strong {
      display: block;
      margin-bottom: 5px;
      font-weight: 700;
    }

    /* Tabel rincian */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      font-size: 13px;
    }
    thead tr {
      background-color: #ddd;
      font-weight: 700;
    }
    thead th, tbody td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: left;
    }
    thead th:nth-child(1), tbody td:nth-child(1) {
      width: 50%;
    }
    thead th:nth-child(2), tbody td:nth-child(2) {
      text-align: right;
      width: 50%;
    }

    /* Pembayaran detail */
    .payment-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
    }
    .payment-left, .payment-right {
      width: 48%;
    }
    .payment-left strong, .payment-right strong {
      font-weight: 700;
    }
    .payment-right div {
      margin-bottom: 6px;
      text-align: right;
    }
    .total {
      font-size: 16px;
      font-weight: 700;
      border-top: 2px solid #000;
      padding-top: 5px;
    }

    /* Tanda tangan */
    table.signature-table {
      width: 100%;
      margin-top: 40px;
      border-collapse: collapse;
      border: none;
    }
    table.signature-table td {
      width: 50%;
      text-align: center;
      vertical-align: top;
      padding: 10px;
    }
    table.signature-table hr {
      margin: 60px 0 5px 0;
      border: none;
      border-bottom: 1px solid #000;
      width: 80%;
      margin-left: auto;
      margin-right: auto;
    }
    .signature-name {
      margin-top: 5px;
      font-weight: 700;
      font-size: 14px;
    }

    /* Footer */
    .footer-text {
      font-weight: 700;
      margin-top: 40px;
      text-align: center;
      font-size: 14px;
    }

    /* Button print */
    .btn-print {
      text-align: center;
      margin-top: 20px;
    }
    .btn-print button {
      background-color: #000;
      color: #fff;
      padding: 10px 25px;
      border: none;
      cursor: pointer;
      font-weight: 700;
      font-size: 14px;
      border-radius: 4px;
    }
    @media print {
      .btn-print {
        display: none;
      }
    }
  </style>
</head>
<body <?= $auto_print ? 'onload="window.print()"' : '' ?>>

  <div class="container">
    <div class="header">
      <div class="logo">INVOICE</div>
      <div class="tagline">
        <strong><?= htmlspecialchars($data['nama_bengkel']) ?></strong><br/>
        <?= htmlspecialchars($data['alamat']) ?><br/>
        Telp: <?= htmlspecialchars($data['telepon']) ?>
      </div>
    </div>

    <div class="info-section">
      <div class="info-left">
        <strong>KEPADA :</strong>
        <p><?= htmlspecialchars($data['nama_pelanggan']) ?></p>
      </div>
      <div class="info-right">
        <strong>TANGGAL :</strong>
        <p><?= date('l, d F Y', strtotime($data['tanggal_bayar'])) ?></p>

        <strong>NO INVOICE :</strong>
        <p><?= htmlspecialchars($data['id_cicilan']) . " / " . date('d/m/Y', strtotime($data['tanggal_bayar'])) ?></p>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>KETERANGAN</th>
          <th>JUMLAH</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Pembayaran Cicilan Untuk Faktur <?= htmlspecialchars($data['no_faktur']) ?></td>
          <td><?= rupiah($data['jumlah_bayar']) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="payment-info">
      <div class="payment-left">
        <strong>PEMBAYARAN :</strong>
        <p>Metode: <?= htmlspecialchars($data['metode_bayar'] ?: '-') ?></p>
        <p>Keterangan: <?= nl2br(htmlspecialchars($data['keterangan'] ?: '-')) ?></p>
      </div>
      <div class="payment-right">
        <div>SUB TOTAL : <?= rupiah($sub_total) ?></div>
        <div>JUMLAH DIBAYAR : <?= rupiah($data['jumlah_bayar']) ?></div>
        <div>SISA PIUTANG : <?= rupiah($sisa_piutang) ?></div>
        <div class="total">TOTAL : <?= rupiah($data['jumlah_bayar']) ?></div>
      </div>
    </div>

    <table class="signature-table">
      <tr>
        <td>
          <div>Petugas</div>
          <hr>
          <div class="signature-name"><?= htmlspecialchars($data['nama_lengkap']) ?></div>
        </td>
        <td>
          <div>Pelanggan</div>
          <hr>
          <div class="signature-name"><?= htmlspecialchars($data['nama_pelanggan']) ?></div>
        </td>
      </tr>
    </table>

    <div class="footer-text">
      TERIMAKASIH ATAS PEMBAYARAN ANDA
    </div>
  </div>

  <?php if (!$auto_print): ?>
    <div class="btn-print">
      <button onclick="window.print()">🖨 Cetak Invoice</button>
    </div>
  <?php endif; ?>
</body>
</html>
