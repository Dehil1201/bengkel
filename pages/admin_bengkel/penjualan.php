<style>
    @media print {
    .dataTables_filter,
    .dataTables_length,
    .dataTables_info,
    .dataTables_paginate {
        display: none !important;
    }
    body * {
        visibility: hidden;
    }

    #printArea, #printArea * {
        visibility: visible;
    }

    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        font-family: 'Arial', sans-serif;
        color: #000;
    }

    table {
        border-collapse: collapse !important;
        width: 100%;
    }

    table th, table td {
        border: 1px solid #000 !important;
        padding: 4px;
        font-size: 12px;
    }

    .invoice-header h3 {
        margin: 0;
    }

    .invoice-footer {
        margin-top: 30px;
        font-size: 12px;
    }

    .modal-footer, .close, .btn {
        display: none !important;
    }

    .cell-daftar-barang {
        max-height: 100px;    /* batas tinggi */
        overflow-y: auto;     /* aktifkan scroll */
        display: block;       /* supaya scroll berfungsi */
        white-space: normal;  /* biar teks dapat turun baris */
    }
}

</style>
<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? date('Y-m-01'); // Awal bulan ini
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-t'); // Akhir bulan ini
$id_pelanggan = $_GET['id_pelanggan'] ?? '';
$id_user = $_GET['id_user'] ?? '';

$where = "WHERE t.no_faktur LIKE '%PJ%'";
if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_pelanggan) {
    $where .= " AND t.id_pelanggan = '$id_pelanggan'";
}
if ($id_user) {
    $where .= " AND t.id_user = '$id_user'";
}

// ADD: Total penjualan
$total_penjualan = 0;
$q_total = mysqli_query($conn, "
    SELECT SUM(t.total_bayar) as total_penjualan
    FROM transaksi t
    $where AND id_bengkel = '$id_bengkel'
");
if ($row = mysqli_fetch_assoc($q_total)) {
    $total_penjualan = $row['total_penjualan'] ?? 0;
}

// Get transaksi
$query_laporan = mysqli_query($conn, "
    SELECT t.no_faktur, t.tanggal, p.nama_pelanggan, u.nama_lengkap, t.total_bayar, t.status, t.total, t.discount,t.uang_bayar, t.kembalian, t.metode_bayar,
    GROUP_CONCAT(s.nama_sparepart SEPARATOR ', ') AS daftar_barang
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN users u ON t.id_user = u.id_user
    LEFT JOIN transaksi_detail_sparepart td ON t.no_faktur = td.no_faktur
    LEFT JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
    $where AND id_bengkel = '$id_bengkel'
    GROUP BY t.no_faktur
    ORDER BY t.tanggal desc
");

// Dropdown data
$list_pelanggan = mysqli_query($conn, "SELECT id_pelanggan, nama_pelanggan FROM pelanggans WHERE bengkel_id = '$id_bengkel'");
$list_user = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users WHERE bengkel_id = '$id_bengkel'");
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Laporan Penjualan</h3>
    </div>
    <div class="box-body">

        <div class="row">
            <!-- Filter Form -->
            <div class="col-md-8">
                <form method="get" class="form-inline" style="margin-bottom: 20px;">
                    <input type="hidden" name="page" value="penjualan">
                    <div class="form-group">
                        <label>Dari</label>
                        <input type="date" name="tgl_dari" class="form-control" value="<?= $tgl_dari ?>">
                    </div>
                    <div class="form-group">
                        <label>Sampai</label>
                        <input type="date" name="tgl_sampai" class="form-control" value="<?= $tgl_sampai ?>">
                    </div>
                    <div class="form-group">
                        <label>Pelanggan</label>
                        <select name="id_pelanggan" class="form-control">
                            <option value="">-- Semua --</option>
                            <?php while ($p = mysqli_fetch_assoc($list_pelanggan)) : ?>
                                <option value="<?= $p['id_pelanggan']; ?>" <?= $id_pelanggan == $p['id_pelanggan'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama_pelanggan']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>User</label>
                        <select name="id_user" class="form-control">
                            <option value="">-- Semua --</option>
                            <?php while ($u = mysqli_fetch_assoc($list_user)) : ?>
                                <option value="<?= $u['id_user']; ?>" <?= $id_user == $u['id_user'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nama_lengkap']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>

            <!-- Total Penjualan -->
            <div class="col-md-4">
                <div class="callout callout-warning" style="margin-bottom: 20px;">
                    <h4>Total Penjualan</h4>
                    <p style="font-size: 18px; font-weight: bold;">Rp <?= number_format($total_penjualan, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>

        <!-- Table -->
        <table id="tableLaporan" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No Faktur</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>User Input</th>
                    <th>Status</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Diskon(%)</th>
                    <th>Total Bayar</th>
                    <th>Uang Bayar</th>
                    <th>Kembalian</th>
                    <th>Daftar Barang</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($query_laporan)) : ?>
                    <tr>
                        <td><?= htmlspecialchars($row['no_faktur']); ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($row['nama_lengkap'] ?? '-'); ?></td>
                        <td>
                            <?php if ($row['status'] == 'selesai') : ?>
                                <span class="label label-success">selesai</span>
                            <?php else : ?>
                                <span class="label label-warning"><?= htmlspecialchars($row['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['metode_bayar'] == 'Tunai') : ?>
                                <span class="label label-info">Tunai</span>
                            <?php else : ?>
                                <span class="label label-danger"><?= htmlspecialchars($row['metode_bayar']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>Rp <?= number_format($row['total'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['discount'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['total_bayar'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['uang_bayar'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['kembalian'], 0, ',', '.'); ?></td>
                        <td class="cell-daftar-barang">
                            <ul style="padding-left: 15px; margin: 0;">
                                <?php 
                                    $items = explode(',', $row['daftar_barang']);
                                    foreach ($items as $item) {
                                        echo "<li>" . htmlspecialchars(trim($item)) . "</li>";
                                    }
                                ?>
                            </ul>
                        </td>
                        <td>
                            <button class="btn btn-info btn-sm btn-detail" data-faktur="<?= htmlspecialchars($row['no_faktur']); ?>">
                                <i class="fa fa-eye"></i> Detail
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h4 class="modal-title">Detail Transaksi</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="printArea">

                <style>
                    .invoice-box {
                        margin: 0 auto;
                        background: #fff;
                        border-radius: 12px;
                        box-shadow: 0 0 15px rgba(0,0,0,0.1);
                        padding: 30px 40px;
                        font-family: "Poppins", Arial, sans-serif;
                        color: #333;
                    }

                    .invoice-header {
                        display: flex;
                        justify-content: space-between;
                        border-bottom: 2px solid #ccc;
                        padding-bottom: 10px;
                        margin-bottom: 20px;
                    }

                    .invoice-header h2 {
                        color: #000;
                        margin: 0;
                    }

                    .company-info {
                        text-align: right;
                        font-size: 12px;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .invoice-details td {
                        padding: 2px 0;
                        font-size: 12px;
                    }

                    table th {
                        background: #ccc;
                        padding: 10px;
                        font-size: 12px;
                    }

                    table td {
                        border-bottom: 1px solid #ddd;
                        padding: 6px;
                        font-size: 12px;
                    }

                    .text-right { text-align: right; }

                    .invoice-total td {
                        padding: 8px;
                        font-size: 15px;
                    }

                    .invoice-footer {
                        margin-top: 40px;
                        text-align: center;
                        font-size: 12px;
                        color: #555;
                    }

                    @media print {
                        body { background: #fff; }
                        .modal-footer, .close { display: none !important; }
                        .invoice-box { box-shadow: none; padding: 0; }
                    }
                </style>

                <div class="invoice-box">

                    <div class="invoice-header">
                        <div>
                            <h2>FAKTUR</h2>
                            <p>No: <strong id="noFaktur"></strong></p>
                        </div>

                        <div class="company-info">
                            <strong><?= $nama_bengkel; ?></strong><br>
                            <?= $alamat_bengkel; ?><br>
                            Telp: <?= $telepon_bengkel; ?><br>
                        </div>
                    </div>

                    <div class="invoice-details">
                        <table>
                            <tr>
                                <td><strong>Tanggal</strong></td>
                                <td>: <span id="headTanggal"></span></td>
                                <td><strong>Kasir</strong></td>
                                <td>: <span id="headKasir"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Pelanggan</strong></td>
                                <td>: <span id="headPelanggan"></span></td>
                                <td><strong>Tanggal JTT</strong></td>
                                <td>: <span id="tanggalJtt"></span></td>
                            </tr>
                        </table>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Qty</th>
                                <th>Satuan</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyBarang"></tbody>
                    </table>

                    <table class="invoice-total">
                        <tr>
                            <td class="text-right"><strong>Subtotal:</strong></td>
                            <td class="text-right" width="150" id="totalSparepart">Rp 0</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>Diskon:</strong></td>
                            <td class="text-right" id="diskonFaktur">Rp 0</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>PPN:</strong></td>
                            <td class="text-right" id="ppnFaktur">Rp 0</td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>Grand Total:</strong></td>
                            <td class="text-right"><strong id="grandTotalFaktur">Rp 0</strong></td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>Dibayar:</strong></td>
                            <td class="text-right"><strong id="dibayarFaktur">Rp 0</strong></td>
                        </tr>
                        <tr>
                            <td class="text-right"><strong>Kembali:</strong></td>
                            <td class="text-right"><strong id="kembaliFaktur">Rp 0</strong></td>
                        </tr>
                    </table>

                    <div class="invoice-footer">
                        <table width="100%">
                            <tr>
                                <td>Penerima</td>
                                <td width="50%"></td>
                                <td>Hormat Kami</td>
                            </tr>
                            <tr><td><br><br><br></td></tr>
                            <tr>
                                <td style="border-bottom:1px dotted;"></td>
                                <td></td>
                                <td style="border-bottom:1px dotted;"></td>
                            </tr>
                        </table>

                        <p>Terima kasih atas kepercayaan Anda!</p>
                    </div>

                </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnPrint"><i class="fa fa-print"></i> Print</button>
                <button class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>



<script>
$(document).ready(function () {

    // Init table laporan
    $('#tableLaporan').DataTable({
        order: [[1, 'desc']],
        scrollY: true,

    });

    // Klik tombol detail di table laporan
    $('#tableLaporan').on('click', '.btn-detail', function () {

        const table = $('#tableLaporan').DataTable();
        const data = table.row($(this).closest('tr')).data();

        const faktur = $(this).data('faktur'); // <-- pakai data dari tombol
        $('#btnPrint').data('faktur', faktur);

        // Isi header faktur
        $("#noFaktur").text(faktur);
        $("#headNoFaktur").text(faktur);
        $("#headTanggal").text(data[1]);
        $("#headKasir").text(data[3]);
        $("#headPelanggan").text(data[2]);

        // Tampilkan modal
        $("#modalDetail").modal("show");

        // Panggil API untuk barang detail
        loadDetailTransaksi(faktur);
        
        $('#btnPrint').data('faktur', faktur);

        
        
    });
    $('#btnPrint').on('click', function () {
        const faktur = $(this).data('faktur');
        window.location.href = "pages/admin_bengkel/print_struk.php?no_faktur=" + faktur + "&auto_print=1";
    });
});

// --------------------------
//  LOAD DETAIL TRANSAKSI
// --------------------------
function loadDetailTransaksi(faktur) {

    $.ajax({
        url: 'pages/admin_bengkel/api_get_transaksi.php',
        type: 'GET',
        data: { no_faktur: faktur },
        success: function (json) {

            const data = json.data || {};
            const list = data.detail_sparepart || [];
            let tbody = "";
            let total = 0;

            // Tampil detail sparepart
            list.forEach(item => {

                let harga = parseInt(item.harga) || 0;
                let subtotal = parseInt(item.subtotal) || 0;

                total += subtotal;

                tbody += `
                    <tr>
                        <td>${item.kode_sparepart}</td>
                        <td>${item.nama_sparepart}</td>
                        <td>${item.qty}</td>
                        <td>${item.satuan}</td>
                        <td>Rp ${harga.toLocaleString('id-ID')}</td>
                        <td class="text-right">Rp ${subtotal.toLocaleString('id-ID')}</td>
                    </tr>
                `;
            });

            $("#tbodyBarang").html(tbody);

            // Hitung total dan komponen lain
            $("#totalSparepart").text("Rp " + total.toLocaleString('id-ID'));

            const diskon = parseInt(data.diskon || 0);
            const ppn = parseInt(data.ppn || 0);

            const grandTotal = total - diskon + ppn;
            const dibayar = parseInt(data.dibayar || grandTotal);
            const kembali = dibayar - grandTotal;

            $("#diskonFaktur").text("Rp " + diskon.toLocaleString('id-ID'));
            $("#ppnFaktur").text("Rp " + ppn.toLocaleString('id-ID'));
            $("#grandTotalFaktur").text("Rp " + grandTotal.toLocaleString('id-ID'));
            $("#dibayarFaktur").text("Rp " + dibayar.toLocaleString('id-ID'));
            $("#kembaliFaktur").text("Rp " + kembali.toLocaleString('id-ID'));

            // Header tambahan dari API
            $("#tanggalJtt").text(data.tanggal_jtt || "-");
        }
    });

}
</script>

