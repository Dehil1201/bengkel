<?php
include "../../inc/koneksi.php";

$no_faktur = $_GET['no_faktur'] ?? '';
$jenis = strtolower(substr($no_faktur, 0, 2)); // pj / ps

// Format rupiah
function rupiah($n) {
    return 'Rp' . number_format((float)$n, 0, ',', '.');
}

function terbilang($n) {
    $angka = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $n = (float)$n;
    if ($n < 12) {
        return $angka[$n];
    } elseif ($n < 20) {
        return terbilang($n - 10) . " Belas";
    } elseif ($n < 100) {
        return terbilang(floor($n / 10)) . " Puluh" . (($n % 10 != 0) ? " " . terbilang($n % 10) : "");
    } elseif ($n < 200) {
        return "Seratus" . (($n - 100 != 0) ? " " . terbilang($n - 100) : "");
    } elseif ($n < 1000) {
        return terbilang(floor($n / 100)) . " Ratus" . (($n % 100 != 0) ? " " . terbilang($n % 100) : "");
    } elseif ($n < 2000) {
        return "Seribu" . (($n - 1000 != 0) ? " " . terbilang($n - 1000) : "");
    } elseif ($n < 1000000) {
        return terbilang(floor($n / 1000)) . " Ribu" . (($n % 1000 != 0) ? " " . terbilang($n % 1000) : "");
    } elseif ($n < 1000000000) {
        return terbilang(floor($n / 1000000)) . " Juta" . (($n % 1000000 != 0) ? " " . terbilang($n % 1000000) : "");
    } else {
        return $n;
    }
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

// Ambil detail
$sparepart_q = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE no_faktur='" . mysqli_real_escape_string($conn, $no_faktur) . "'");
$servis_q = mysqli_query($conn, "SELECT * FROM transaksi_detail_servis WHERE no_faktur='" . mysqli_real_escape_string($conn, $no_faktur) . "'");

// Simpan item sparepart ke array
$sparepart_items = [];
if ($sparepart_q && mysqli_num_rows($sparepart_q)) {
    while ($sp = mysqli_fetch_assoc($sparepart_q)) {
        $sparepart_items[] = $sp;
    }
}

// Bagi batch per 10 item
$chunks = array_chunk($sparepart_items, 10);
$total_pages = count($chunks);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Faktur <?= htmlspecialchars($no_faktur) ?></title>
<style>
body { font-family: Arial,sans-serif; margin:0; color:#000; background:#f3f3f3; }
.invoice-wrapper { width:100%; margin:auto; padding:5px; }
.invoice-box { background:#fff; padding:10px; border-radius:8px; box-shadow:0 3px 8px rgba(0,0,0,0.1); font-size:12px; }
h2 { font-size:18px; margin:0; }
h4 { margin:12px 0 6px; padding:4px 0; border-bottom:1px solid #e5e5e5; font-size:14px; }
table { width:100%; border-collapse: collapse; margin-bottom:6px; }
tr { text-align:left; }
table tr td, table tr th { padding:4px 6px; font-size:12px; }
table.items thead th { background:#7f7f7f; font-size:12px; color:#fff; }
.text-right { text-align:right; }
.muted { color:#000; }
.actions { margin-bottom:10px; text-align:center; }
.btn { padding:6px 12px; border-radius:4px; background:#0d6efd; color:#fff; text-decoration:none; cursor:pointer; font-size:12px; }
.btn.secondary { background:#6c757d; }
@media print {
    .actions { display:none; }
    body { background:#fff; margin:0; }
    .invoice-box { box-shadow:none; }
    @page { size:A5 landscape; }
    .page-break { page-break-after: always; }
}
</style>
</head>
<body>
<div class="invoice-wrapper">

<div class="actions no-print">
    <button class="btn" onclick="window.print()">Print</button>
    <a class="btn secondary" href="javascript:history.back()">Kembali</a>
</div>

<?php
$total_spare = 0;
$page_number = 1;

// Loop tiap batch 10 item
foreach($chunks as $batch){
    echo '<div class="invoice-box page-break">';
    echo '<table>
        <tr>
            <td>
                <h2>FAKTUR</h2>
                <small>No: <strong>'.htmlspecialchars($no_faktur).'</strong></small>
            </td>
            <td class="text-right">
                <strong>'.htmlspecialchars($transaksi['nama_bengkel'] ?? 'Bengkel').'</strong><br>
                '.htmlspecialchars($transaksi['alamat'] ?? '-').'<br>
                Telp: '.htmlspecialchars($transaksi['telepon'] ?? '-').'
            </td>
        </tr>
    </table>';

    echo '<table>
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>: '.htmlspecialchars($transaksi['tanggal'] ?? date('Y-m-d H:i')).'</td>
            <td><strong>Kasir</strong></td>
            <td>: '.htmlspecialchars($transaksi['nama_lengkap'] ?? '-').'</td>
        </tr>
        <tr>
            <td><strong>Pelanggan</strong></td>
            <td>: '.htmlspecialchars($transaksi['nama_pelanggan'] ?? '-').'</td>
            <td><strong>Jatuh Tempo</strong></td>
            <td>: '.(($transaksi['tanggal_pelunasan']=='0000-00-00')?'-':htmlspecialchars($transaksi['tanggal_pelunasan']??'-')).'</td>
        </tr>
    </table>';

    // Tabel sparepart
    echo '<table class="items">
        <thead>
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Potongan</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>';
    $i = ($page_number - 1) * 10 + 1;
    foreach($batch as $sp){
        $harga = (float)$sp['harga'];
        $qty = (int)$sp['qty'];
        $diskon = (float)($sp['discount'] ?? 0);
        $total_normal = $harga*$qty;
        $discount_harga = ($diskon/100)*$total_normal;
        $sub = max($total_normal-$discount_harga,0);
        $total_spare += $sub;
        echo "<tr>
                <td>{$i}</td>
                <td>{$sp['kode_sparepart']}</td>
                <td>{$sp['nama_sparepart']}</td>
                <td>{$qty}</td>
                <td>{$sp['satuan']}</td>
                <td class='text-right'>".rupiah($harga)."</td>
                <td class='text-right'>".rupiah($total_normal)."</td>
                <td class='text-right'>".rupiah($discount_harga)." ({$diskon}%)</td>
                <td class='text-right'>".rupiah($sub)."</td>
              </tr>";
        $i++;
    }
    echo '</tbody></table>';

    $total_servis = 0;
    if($jenis == 'PS' && $page_number == $total_pages){ // hanya di halaman terakhir
        echo '<h4>Servis</h4>';
        echo '<table class="items">
            <thead><tr>
                <th>Nama Servis</th>
                <th width="80">Qty</th>
                <th class="text-right">Harga</th>
            </tr></thead><tbody>';

        if($servis_q && mysqli_num_rows($servis_q)){
            mysqli_data_seek($servis_q,0); // reset pointer
            while($sv = mysqli_fetch_assoc($servis_q)){
                $qty = $sv['qty'] ?? 1;
                $sub = $sv['biaya'] * $qty;
                $total_servis += $sub;
                echo "<tr>
                        <td>{$sv['nama_servis']}</td>
                        <td>{$qty}</td>
                        <td class='text-right'>".rupiah($sub)."</td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='3' class='muted'>Tidak ada servis.</td></tr>";
        }
        echo '</tbody></table>';
    }


    // Tampilkan total hanya di halaman terakhir
    if($page_number==$total_pages){
        $subtotal = $total_spare + $total_servis;
        $diskon = $transaksi['discount'] ?? 0;
        $ppn = $transaksi['ppn'] ?? 0;
        $grand = $transaksi['total_bayar'] ?? $subtotal;
        $dibayar = $transaksi['uang_bayar'] ?? $grand;
        $kembali = $transaksi['kembalian'] ?? 0;

        echo '<h4>Total Pembayaran</h4>
        <table>
            <tr><td class="text-right" width="50%"><strong>Subtotal</strong></td><td class="text-right" style="padding-right:80px">'.rupiah($subtotal).'</td></tr>
            <tr><td class="text-right"><strong>Diskon</strong></td><td class="text-right" style="padding-right:80px">'.$diskon.'%</td></tr>
            <tr><td class="text-right"><strong>PPN</strong></td><td class="text-right" style="padding-right:80px">'.rupiah($ppn).'</td></tr>
            <tr><td class="text-right"><strong>Grand Total</strong></td><td class="text-right" style="padding-right:80px"><strong>'.rupiah($grand).'<br><i>(' . terbilang($grand) . 'Rupiah)</i> </strong>
            </td></tr>
            <tr><td class="text-right"><strong>Dibayar</strong></td><td class="text-right" style="padding-right:80px">'.rupiah($dibayar).'</td></tr>
            <tr><td class="text-right"><strong>Kembali</strong></td><td class="text-right" style="padding-right:80px">'.rupiah($kembali).'</td></tr>
        </table>';

        // Tanda tangan
        echo '<table width="100%">
              <tr>
                <td align=center><strong>Penerima</strong><br><br><br>.............................</td>
                <td></td>
                <td align=center><strong>Hormat Kami</strong><br><br><br>.............................</td>
              </tr>
            </table>';
    }

    echo '</div>'; // invoice-box
    $page_number++;
}
?>

</div>
</body>
</html>
