<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$id_pelanggan = $_GET['id_pelanggan'] ?? '';
$id_user = $_GET['id_user'] ?? '';
$id_teknisi = $_GET['id_teknisi'] ?? '';

$where = "WHERE t.no_faktur LIKE '%PS%'";
if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_pelanggan) {
    $where .= " AND t.id_pelanggan = '$id_pelanggan'";
}
if ($id_user) {
    $where .= " AND t.id_user = '$id_user'";
}
if ($id_teknisi) {
    $where .= " AND t.id_teknisi = '$id_teknisi'";
}

// Get transaksi
$query_laporan = mysqli_query($conn, "
    SELECT 
    t.no_faktur, 
    t.tanggal, 
    p.nama_pelanggan, 
    u.nama_lengkap, 
    t.total_bayar, 
    t.status, 
    tk.nama_teknisi,

    -- Total servis per faktur
    IFNULL((
        SELECT SUM(td.biaya)
        FROM transaksi_detail_servis td
        WHERE td.no_faktur = t.no_faktur
    ), 0) AS total_servis,

    -- Total sparepart per faktur
    IFNULL((
        SELECT SUM(ts.subtotal)
        FROM transaksi_detail_sparepart ts
        WHERE ts.no_faktur = t.no_faktur
    ), 0) AS total_sparepart

FROM transaksi t
LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
LEFT JOIN users u ON t.id_user = u.id_user
LEFT JOIN teknisis tk ON t.id_teknisi = tk.id_teknisi
$where AND t.id_bengkel = '$id_bengkel'
ORDER BY t.tanggal DESC
");

// Hitung total keseluruhan
$total_semua = 0;
$total_sparepart_all = 0;
$total_servis_all = 0;

mysqli_data_seek($query_laporan, 0);
while ($row = mysqli_fetch_assoc($query_laporan)) {
    $total_semua += $row['total_bayar'];
    $total_sparepart_all += $row['total_sparepart'];
    $total_servis_all += $row['total_servis'];
}
mysqli_data_seek($query_laporan, 0);

// Dropdown data
$list_pelanggan = mysqli_query($conn, "SELECT id_pelanggan, nama_pelanggan FROM pelanggans where bengkel_id = '$id_bengkel'");
$list_user = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users where bengkel_id = '$id_bengkel'");
$list_teknisi = mysqli_query($conn, "SELECT id_teknisi, nama_teknisi FROM teknisis where bengkel_id = '$id_bengkel'");
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Laporan Jasa Service</h3>
    </div>
    <div class="box-body">
        <!-- Filter -->
        <form method="get" class="form-inline" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="jasa_services_report">
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
            <div class="form-group">
                <label>Teknisi</label>
                <select name="id_teknisi" class="form-control">
                    <option value="">-- Semua --</option>
                    <?php while ($t = mysqli_fetch_assoc($list_teknisi)) : ?>
                        <option value="<?= $t['id_teknisi']; ?>" <?= $id_teknisi == $t['id_teknisi'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nama_teknisi']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <!-- Total Summary -->
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-4">
                <div class="callout callout-success">
                    <strong>Total Transaksi:</strong><br>
                    <h3>Rp <?= number_format($total_semua, 0, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="callout callout-info">
                    <strong>Total Sparepart:</strong><br>
                    <h3> Rp <?= number_format($total_sparepart_all, 0, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="callout callout-warning">
                    <strong>Total Servis:</strong><br>
                    <h3>Rp <?= number_format($total_servis_all, 0, ',', '.') ?></h3>
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
                    <th>Total</th>
                    <th>Total Belanja</th>
                    <th>Total Servis</th>
                    <th>Teknisi</th>
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
                        <td>Rp <?= number_format($row['total_bayar'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['total_sparepart'], 0, ',', '.'); ?></td>
                        <td>Rp <?= number_format($row['total_servis'], 0, ',', '.'); ?></td>
                        <td><?= htmlspecialchars($row['nama_teknisi'] ?? '-'); ?></td>
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
    $('#tableLaporan').DataTable();

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
