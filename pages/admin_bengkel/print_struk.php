<?php
include "../../inc/koneksi.php";

$no_faktur = $_GET['no_faktur'] ?? '';
$jenis = strtolower(substr($no_faktur, 0, 2)); // pj / ps

function rupiah($n) {
    return 'Rp' . number_format((float)$n, 0, ',', '.');
}

function terbilang($n) {
    $angka = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $n = (float)$n;
    if ($n < 12) return $angka[$n];
    elseif ($n < 20) return terbilang($n - 10) . " Belas";
    elseif ($n < 100) return terbilang(floor($n / 10)) . " Puluh " . terbilang($n % 10);
    elseif ($n < 200) return "Seratus " . terbilang($n - 100);
    elseif ($n < 1000) return terbilang(floor($n / 100)) . " Ratus " . terbilang($n % 100);
    elseif ($n < 2000) return "Seribu " . terbilang($n - 1000);
    elseif ($n < 1000000) return terbilang(floor($n / 1000)) . " Ribu " . terbilang($n % 1000);
    elseif ($n < 1000000000) return terbilang(floor($n / 1000000)) . " Juta " . terbilang($n % 1000000);
    return $n;
}

// HEADER
$sql = mysqli_query($conn, "
    SELECT t.*, p.nama_pelanggan, te.nama_teknisi,
           b.nama_bengkel, b.alamat, b.telepon,
           u.nama_lengkap, pi.tanggal_pelunasan
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN teknisis te ON t.id_teknisi = te.id_teknisi
    LEFT JOIN bengkels b ON t.id_bengkel = b.id_bengkel
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN piutang pi ON t.no_faktur = pi.no_faktur
    WHERE t.no_faktur='".mysqli_real_escape_string($conn, $no_faktur)."'
    LIMIT 1
");
$transaksi = mysqli_fetch_assoc($sql) ?: [];

// DETAIL
$sparepart_q = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='".mysqli_real_escape_string($conn, $no_faktur)."'");
$servis_q    = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='".mysqli_real_escape_string($conn, $no_faktur)."'");

$sparepart_items = [];
while ($sp = mysqli_fetch_assoc($sparepart_q)) {
    $sparepart_items[] = $sp;
}

// PAGING 10 ITEM
$chunks = array_chunk($sparepart_items, 10);
$total_pages = count($chunks);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Faktur <?= htmlspecialchars($no_faktur) ?></title>
<style>
body { font-family: Arial,sans-serif; margin:0; background:#f3f3f3; }
.invoice-wrapper { width:100%; padding:5px; }
.invoice-box { background:#fff; padding:10px; border-radius:8px; font-size:12px; }
table { width:100%; border-collapse: collapse; }
table th, table td { padding:1px 4px; font-size:12px; }
table thead th { background:#777; color:#fff; }
.text-right { text-align:right; }
.actions { margin-bottom:10px; text-align:center; }
.btn { padding:6px 12px; border-radius:4px; background:#0d6efd; color:#fff; text-decoration:none; }
.items tbody td { vertical-align: top; }

@media print {
    .actions { display:none; }
    @page { size:A5 landscape; margin:5mm; }
    .page-break { page-break-after: always; }
}
</style>
</head>

<body>
<div class="invoice-wrapper">

<div class="actions">
    <button class="btn" onclick="window.print()">Print</button>
    <a class="btn" href="javascript:history.back()">Kembali</a>
</div>

<?php
$total_spare = 0;
$no_urut_global = 1;

foreach ($chunks as $page => $batch) :
?>
<div class="invoice-box page-break">

<table>
<tr>
    <td width="50%">
        <h3>FAKTUR</h3>
        No: <strong><?= htmlspecialchars($no_faktur) ?></strong>
    </td>
    <td class="text-right">
        <strong><?= htmlspecialchars($transaksi['nama_bengkel'] ?? '-') ?></strong><br>
        <?= htmlspecialchars($transaksi['alamat'] ?? '-') ?><br>
        Telp: <?= htmlspecialchars($transaksi['telepon'] ?? '-') ?>
    </td>
</tr>
</table>

<table>
<tr>
    <td>Tanggal</td>
    <td>: <?= htmlspecialchars($transaksi['tanggal'] ?? date('Y-m-d')) ?></td>
    <td>Kasir</td>
    <td>: <?= htmlspecialchars($transaksi['nama_lengkap'] ?? '-') ?></td>
</tr>
<tr>
    <td>Pelanggan</td>
    <td>: <?= htmlspecialchars($transaksi['nama_pelanggan'] ?? '-') ?></td>
    <td>Jatuh Tempo</td>
    <td>: <?= ($transaksi['tanggal_pelunasan']=='0000-00-00' ? '-' : $transaksi['tanggal_pelunasan']) ?></td>
</tr>
</table>

<table class="items">
<thead>
<tr>
    <th>No</th>
    <th>Kode</th>
    <th>Nama Barang</th>
    <th>Qty</th>
    <th>Satuan</th>
    <th class="text-right">Harga</th>
    <th class="text-right">Subtotal</th>
    <th class="text-right">Diskon</th>
    <th class="text-right">Total</th>
</tr>
</thead>
<tbody>
<?php foreach ($batch as $sp): 
    $harga = (float)$sp['harga'];
    $qty = (int)$sp['qty'];
    $diskon = (float)($sp['discount'] ?? 0);
    $total_normal = $harga * $qty;
    $potongan = ($diskon / 100) * $total_normal;
    $sub = max($total_normal - $potongan, 0);
    $total_spare += $sub;
?>
<tr>
    <td><?= $no_urut_global++ ?></td>
    <td><?= $sp['kode_sparepart'] ?></td>
    <td><?= $sp['nama_sparepart'] ?></td>
    <td><?= $qty ?></td>
    <td><?= $sp['satuan'] ?></td>
    <td class="text-right"><?= rupiah($harga) ?></td>
    <td class="text-right"><?= rupiah($total_normal) ?></td>
    <td class="text-right"><?= rupiah($potongan) ?></td>
    <td class="text-right"><?= rupiah($sub) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php
$total_servis = 0;
if ($servis_q) {
    mysqli_data_seek($servis_q, 0);
    while ($sv = mysqli_fetch_assoc($servis_q)) {
        $total_servis += $sv['biaya'] * $sv['qty'];
    }
}

$subtotal = $total_spare + $total_servis;
$diskon = (float) ($transaksi['discount'] ?? 0);
$diskon_nilai = ($diskon / 100) * $subtotal;
$total_setelah_diskon = max($subtotal - $diskon_nilai, 0);
$ppn_nilai = 0.11 * $total_setelah_diskon;
$grand = $total_setelah_diskon + $ppn_nilai;
$dibayar = $transaksi['uang_bayar'] ?? 0;
$kembali = max($dibayar - $transaksi['total_bayar'], 0);
?>

<h4>Total Pembayaran</h4>
<table class="items">
<tbody>
<tr><td class="text-right" width="50%"><strong>Subtotal</strong></td><td class="text-right" style="padding-right:100px"><?= rupiah($subtotal) ?></td></tr>
<tr><td class="text-right"><strong>Diskon <?= $diskon ?>%</strong></td><td class="text-right" style="padding-right:100px">- <?= rupiah($diskon_nilai) ?></td></tr>
<tr><td class="text-right"><strong>Total</strong></td><td class="text-right" style="padding-right:100px"><?= rupiah($total_setelah_diskon) ?></td></tr>
<tr><td class="text-right"><strong>PPN 11%</strong></td><td class="text-right" style="padding-right:100px"><?= rupiah($ppn_nilai) ?></td></tr>
<tr><td class="text-right"><strong>Grand Total</strong></td><td class="text-right" style="padding-right:100px"><strong><?= rupiah($grand) ?></strong></td></tr>
<tr><td class="text-right"><strong>Dibayar</strong></td><td class="text-right" style="padding-right:100px"><?= rupiah($dibayar) ?></td></tr>
<tr><td class="text-right"><strong>Kembali</strong></td><td class="text-right" style="padding-right:100px"><?= rupiah($kembali) ?></td></tr>
</tbody>
</table>

<br>

<table width="100%">
<tr>
    <td align="center" width="40%"><strong>Penerima</strong><br><br>....................</td>
    <td width="20%"></td>
    <td align="center" width="40%"><strong>Hormat Kami</strong><br><br>....................</td>
</tr>
</table>

</div>
<?php endforeach; ?>

</div>
</body>
</html>
