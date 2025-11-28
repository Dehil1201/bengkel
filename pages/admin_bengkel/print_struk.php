<?php
include "../../inc/koneksi.php";

$no_faktur = $_GET['no_faktur'] ?? '';

// Cek jenis faktur (pj = penjualan sparepart, ps = servis)
$jenis = strtolower(substr($no_faktur, 0, 2)); // pj / ps

// Format rupiah
function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

// Ambil header
$sql = mysqli_query($conn, "
    SELECT t.*, 
           p.nama_pelanggan, 
           te.nama_teknisi, 
           b.nama_bengkel, 
           b.alamat, 
           b.telepon, 
           u.nama_lengkap,
           pi.tanggal_pelunasan
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN teknisis te ON t.id_teknisi = te.id_teknisi
    LEFT JOIN bengkels b ON t.id_bengkel = b.id_bengkel
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN piutang pi ON t.no_faktur = pi.no_faktur
    WHERE t.no_faktur='" . mysqli_real_escape_string($conn, $no_faktur) . "'
    LIMIT 1
");
$transaksi = mysqli_fetch_assoc($sql) ?: [];

// Detail
$servis_q = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='" . mysqli_real_escape_string($conn, $no_faktur) . "'");
$sparepart_q = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='" . mysqli_real_escape_string($conn, $no_faktur) . "'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Faktur <?= htmlspecialchars($no_faktur) ?></title>
<style>
    /* GENERAL */
    body {
        font-family: Arial, sans-serif;
        background: #f3f3f3;
        margin: 0;
        color: #333;
    }
    .invoice-wrapper {
        margin: auto;
        padding: 10px;
    }
    .invoice-box {
        background: #fff;
        padding: ;
        border-radius: 8px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        font-size: 12px; /* lebih kecil agar muat */
    }

    h2 {
        font-size: 18px;
        margin-bottom: 5px;
    }
    h4 {
        margin: 12px 0 6px;
        padding: 4px 0;
        border-bottom: 1px solid #e5e5e5;
        font-size: 14px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }
    tr {
        text-align: left;
    }
    table tr td, table tr th {
        padding: 4px 6px;
        font-size: 12px;
    }
    table.items thead th {
        background: #f6f6f6;
        font-size: 12px;
    }

    .text-right { text-align: right; }
    .muted { color: #777; }
    .actions { margin-bottom: 10px; text-align: center; }
    .btn {
        padding: 6px 12px;
        border-radius: 4px;
        background: #0d6efd;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        font-size: 12px;
    }
    .btn.secondary { background: #6c757d; }

    /* PRINT */
    @media print {
        .actions { display: none; }
        body { background: #fff; margin: 0; }
        .invoice-box { box-shadow: none;}
        @page { size: A5 landscape;}
    }
</style>

</head>
<body>

<div class="invoice-wrapper">

    <div class="actions no-print">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn secondary" href="javascript:history.back()">Kembali</a>
    </div>

    <div class="invoice-box">
        
        <!-- HEADER -->
        <table>
            <tr>
                <td>
                    <h2 style="margin:0;">FAKTUR</h2>
                    <small>No: <strong><?= htmlspecialchars($no_faktur) ?></strong></small>
                </td>
                <td class="text-right">
                    <strong><?= htmlspecialchars($transaksi['nama_bengkel'] ?? 'Bengkel') ?></strong><br>
                    <?= htmlspecialchars($transaksi['alamat'] ?? '-') ?><br>
                    Telp: <?= htmlspecialchars($transaksi['telepon'] ?? '-') ?>
                </td>
            </tr>
        </table>

        <!-- DETAIL TRANSAKSI -->
        <table>
            <tr>
                <td><strong>Tanggal</strong></td>
                <td>: <?= htmlspecialchars($transaksi['tanggal'] ?? date('Y-m-d H:i')) ?></td>
                <td><strong>Kasir</strong></td>
                <td>: <?= htmlspecialchars($transaksi['nama_lengkap'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Pelanggan</strong></td>
                <td>: <?= htmlspecialchars($transaksi['nama_pelanggan'] ?? '-') ?></td>
                <td><strong>Jatuh Tempo</strong></td>
                <td>: <?php
                    if ($transaksi['tanggal_pelunasan'] == '0000-00-00') {
                        echo "-";
                    }else {
                        echo htmlspecialchars($transaksi['tanggal_pelunasan'] ?? '-');
                    }
                 ?></td>
            </tr>
        </table>

        <!-- SPAREPART ALWAYS SHOW -->
        <table class="items">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th><th width="60">Qty</th>
                    <th width="80">Satuan</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Diskon</th>   <!-- ✅ BARU -->
                    <th class="text-right">Subtotal</th>

                </tr>
            </thead>
            <tbody>
                <?php
                $total_spare = 0;
                $i = 1;
                
                if ($sparepart_q && mysqli_num_rows($sparepart_q)) {
                    while ($sp = mysqli_fetch_assoc($sparepart_q)) {
                
                        $harga  = (float)$sp['harga'];
                        $qty    = (int)$sp['qty'];
                        $diskon = (float)($sp['discount'] ?? 0); // ✅ persen dari DB
                
                        // ✅ Hitung total normal
                        $total_normal = $harga * $qty;
                
                        // ✅ Hitung nilai diskon dalam rupiah
                        $discount_harga = ($diskon / 100) * $total_normal;
                
                        // ✅ Hitung subtotal setelah diskon
                        $sub = $total_normal - $discount_harga;
                        if ($sub < 0) $sub = 0;
                
                        $total_spare += $sub;
                
                        // ✅ Format: Rp 10.000 (10%)
                        $diskon_text = rupiah($discount_harga) . " ({$diskon}%)";
                
                        echo "
                        <tr>
                            <td>{$i}</td>
                            <td>{$sp['kode_sparepart']}</td>
                            <td>{$sp['nama_sparepart']}</td>
                            <td>{$qty}</td>
                            <td>{$sp['satuan']}</td>
                            <td class='text-right'>" . rupiah($harga) . "</td>
                            <td class='text-right'>{$diskon_text}</td>
                            <td class='text-right'>" . rupiah($sub) . "</td>
                        </tr>";
                
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='8' class='muted'>Tidak ada sparepart.</td></tr>";
                }
                
                ?>
                </tbody>

        </table>

        <!-- SERVICE ONLY IF ps -->
        <?php if ($jenis == 'ps') : ?>
            <h4>Servis</h4>
            <table class="items">
                <thead>
                    <tr>
                        <th>Nama Servis</th>
                        <th width="80">Qty</th>
                        <th class="text-right">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_servis = 0;
                    if ($servis_q && mysqli_num_rows($servis_q)) {
                        while ($sv = mysqli_fetch_assoc($servis_q)) {
                            $qty = $sv['qty'] ?? 1;
                            $sub = $sv['biaya'] * $qty;
                            $total_servis += $sub;
                            echo "
                            <tr>
                                <td>{$sv['nama_servis']}</td>
                                <td>{$qty}</td>
                                <td class='text-right'>" . rupiah($sub) . "</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' class='muted'>Tidak ada servis.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php $total_servis = 0; ?>
        <?php endif; ?>

        <!-- TOTAL -->
        <?php
        $subtotal = $total_spare + $total_servis;
        $diskon = $transaksi['discount'] ?? 0;
        $ppn = $transaksi['ppn'] ?? 0;
        $grand = $transaksi['total_bayar'];
        $dibayar = $transaksi['uang_bayar'] ?? $grand;
        $kembali = $transaksi['kembalian'];
        ?>

        <h4>Total Pembayaran</h4>
        <table>
            <tr><td class="text-right" width="50%"><strong>Subtotal</strong></td><td class="text-right" style="padding-right:80px"><?= rupiah($subtotal) ?></td></tr>
            <tr><td class="text-right"><strong>Diskon</strong></td><td class="text-right" style="padding-right:80px"><?= $diskon."%" ?></td></tr>
            <tr><td class="text-right"><strong>PPN</strong></td><td class="text-right" style="padding-right:80px"><?= rupiah($ppn) ?></td></tr>
            <tr><td class="text-right"><strong>Grand Total</strong></td><td class="text-right" style="padding-right:80px"><strong><?= rupiah($grand) ?></strong></td></tr>
            <tr><td class="text-right"><strong>Dibayar</strong></td><td class="text-right" style="padding-right:80px"><?= rupiah($dibayar) ?></td></tr>
            <tr><td class="text-right"><strong>Kembali</strong></td><td class="text-right" style="padding-right:80px"><?= rupiah($kembali) ?></td></tr>
        </table>

        <table width="100%">
          <tr>
            <td align=center><strong>Penerima</strong>
          
            <br><br><br>
            .............................
          </td>
            <td></td>
            <td align=center><strong>Hormat Kami, </strong><br><br><br>

            .............................
            </td>
          </tr>
        </table>

        <center><p class="text-center muted">Terima kasih telah berkunjung!</p></center>

    </div>
</div>

</body>
</html>
