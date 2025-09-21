<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? date('Y-m-d');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-d');
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
                        <td><?= number_format($row['discount'], 0, ',', '.'); ?></td>
                        <td><?= number_format($row['total_bayar'], 0, ',', '.'); ?></td>
                        <td><?= number_format($row['uang_bayar'], 0, ',', '.'); ?></td>
                        <td><?= number_format($row['kembalian'], 0, ',', '.'); ?></td>
                        <td><?= $row['daftar_barang']; ?></td>
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

<!-- Modal -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h4 class="modal-title">Detail Transaksi</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table id="table-sparepart" class="table table-bordered table-striped">
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
                </table>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#tableLaporan').DataTable({
        order: [[1, 'desc']]
    });

    $('.btn-detail').on('click', function () {
        const faktur = $(this).data('faktur');
        $('#modalDetail').modal('show');

        $('#table-sparepart').DataTable({
            destroy: true,
            ajax: {
                url: 'pages/admin_bengkel/api_get_transaksi.php',
                type: 'GET',
                data: { no_faktur: faktur },
                dataSrc: function (json) {
                    return json.data.detail_sparepart || [];
                }
            },
            columns: [
                { data: 'kode_sparepart' },
                { data: 'nama_sparepart' },
                { data: 'harga', render: d => 'Rp ' + parseInt(d).toLocaleString('id-ID') },
                { data: 'qty' },
                { data: 'satuan' },
                { data: 'subtotal', render: d => 'Rp ' + parseInt(d).toLocaleString('id-ID') }
            ]
        });
    });
});
</script>
