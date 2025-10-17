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
                        <td>Rp <?= $row['daftar_barang']; ?></td>
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
                <div class="invoice-header text-center mb-3">
                    <h3><strong><?= $nama_bengkel; ?></strong></h3>
                    <p><?= $alamat_bengkel; ?><br>
                    Telp: <?= $telepon_bengkel; ?></p>
                    <hr>
                </div>

                <table width="100%" class="table table-sm">
                    <tr>
                        <td><strong>No Faktur</strong></td>
                        <td id="headNoFaktur"></td>
                        <td><strong>Tanggal</strong></td>
                        <td id="headTanggal"></td>
                    </tr>
                    <tr>
                        <td><strong>Kasir</strong></td>
                        <td id="headKasir"></td>
                        <td><strong>Pelanggan</strong></td>
                        <td id="headPelanggan"></td>
                    </tr>
                </table>

                <table id="table-sparepart" class="table table-bordered table-striped mt-3">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" style="text-align:right">Total:</th>
                            <th id="totalSparepart">Rp 0</th>
                        </tr>
                    </tfoot>
                </table>

                <div class="invoice-footer text-center mt-4">
                    <p><em>Terima kasih atas kunjungannya</em></p>
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
    $('#tableLaporan').DataTable({
        order: [[1, 'desc']],
        scrollY: true,
    });

    $('#tableLaporan').on('click', '.btn-detail', function () {
        const faktur = $(this).data('faktur');
        const table = $('#tableLaporan').DataTable();
        const data = table.row($(this).closest('tr')).data();

        $("#headTanggal").html(data[1]);
        $("#headNoFaktur").html(data[0]);
        $("#headKasir").html(data[3]);
        $("#headPelanggan").html(data[2]);


        $('#modalDetail').modal('show');


        $('#table-sparepart').DataTable({
            destroy: true,
            info: false,
            ordering: false,
            ajax: {
                url: 'pages/admin_bengkel/api_get_transaksi.php',
                type: 'GET',
                data: { no_faktur: faktur },
                dataSrc: json => json.data.detail_sparepart || []
            },
            columns: [
                { data: 'kode_sparepart' },
                { data: 'nama_sparepart' },
                { data: 'harga', render: d => 'Rp ' + parseInt(d).toLocaleString('id-ID') },
                { data: 'qty' },
                { data: 'satuan' },
                { data: 'subtotal', render: d => 'Rp ' + parseInt(d).toLocaleString('id-ID') }
            ],
            paging: false,
            footerCallback: function (row, data) {
                let total = 0;
                data.forEach(item => {
                    total += parseFloat(item.subtotal || 0);
                });

                // Format ke rupiah dengan titik
                const totalFormatted = 'Rp ' + total.toLocaleString('id-ID');

                // Tampilkan ke footer
                $(this.api().column(5).footer()).html(totalFormatted);
            }
        });
    });
    $('#btnPrint').on('click', function () {
        const printArea = document.getElementById('printArea').innerHTML;
        const printWindow = window.open('', '', 'height=700,width=900');

        printWindow.document.write(`
            <html>
                <head>
                    <title>Invoice</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h3 { margin-bottom: 5px; }
                        p { margin: 2px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                        th, td { border: 1px solid #000; padding: 5px; font-size: 12px; }
                        th { background: #f2f2f2; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                    </style>
                </head>
                <body>${printArea}</body>
            </html>
        `);

        printWindow.document.close();
        printWindow.print();
    });

});
</script>
