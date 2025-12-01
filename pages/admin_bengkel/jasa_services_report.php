<?php

/* ================= AMBIL NILAI FILTER ================= */
$tgl_dari     = $_GET['tgl_dari'] ?? date('Y-m-01');
$tgl_sampai   = $_GET['tgl_sampai'] ?? date('Y-m-d');
$id_pelanggan = $_GET['id_pelanggan'] ?? '';
$id_user      = $_GET['id_user'] ?? '';
$id_teknisi   = $_GET['id_teknisi'] ?? '';

/* ================= DATA UNTUK SELECT FILTER ================= */

// Pelanggan
$list_pelanggan = mysqli_query($conn, "
    SELECT id_pelanggan, nama_pelanggan
    FROM pelanggans
    ORDER BY nama_pelanggan ASC
");

// User
$list_user = mysqli_query($conn, "
    SELECT id_user, nama_lengkap 
    FROM users
    ORDER BY nama_lengkap ASC
");

// Teknisi
$list_teknisi = mysqli_query($conn, "
    SELECT id_teknisi, nama_teknisi 
    FROM teknisis
    ORDER BY nama_teknisi ASC
");

if (!$list_pelanggan || !$list_user || !$list_teknisi) {
    die("Query filter gagal: " . mysqli_error($conn));
}
?>


<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Laporan Jasa Service</h3>
    </div>

    <div class="box-body">

        <!-- ================= FILTER ================= -->
        <form id="formFilter" class="form-inline mb-5">
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

        <!-- ================= SUMMARY ================= -->
        <div class="row" style="margin-bottom:20px;">
            <div class="col-md-4">
                <div class="callout callout-success">
                    <strong>Total Transaksi</strong>
                    <h3 id="txtTotalTransaksi">Rp 0</h3>
                </div>
            </div>

            <div class="col-md-4">
                <div class="callout callout-info">
                    <strong>Total Sparepart</strong>
                    <h3 id="txtTotalSparepart">Rp 0</h3>
                </div>
            </div>

            <div class="col-md-4">
                <div class="callout callout-warning">
                    <strong>Total Servis</strong>
                    <h3 id="txtTotalServis">Rp 0</h3>
                </div>
            </div>
        </div>

        <!-- ================= TABLE ================= -->
        <table id="tableLaporan" class="table table-bordered table-striped" width="100%">
            <thead>
                <tr>
                    <th>No Faktur</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kendaraan</th>
                    <th>No Polisi</th>
                    <th>User Input</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Total Belanja</th>
                    <th>Total Servis</th>
                    <th>Teknisi</th>
                    <th width="80">Detail</th>
                </tr>
            </thead>
            <tbody></tbody> <!-- ✅ WAJIB KOSONG KARENA SERVER-SIDE -->
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
                <table width="100%" class="table table-responsive">
                    <tr>
                        <td>No Faktur</td>
                        <td id="headNoFaktur"></td>
                        <td>Tanggal</td>
                        <td id="headTanggal"></td>
                    </tr>
                    <tr>
                        <td>Kasir</td>
                        <td id="headKasir"></td>
                        <td>Teknisi</td>
                        <td id="headTeknisi"></td>
                    </tr>
                    <tr>
                        <td>Pelanggan</td>
                        <td id="headPelanggan" colspan="3"></td>
                    </tr>
                    <tr>
                        <td>Kendaraan</td>
                        <td id="headKendaraan"></td>
                        <td>No Polisi</td>
                        <td id="headNoPolisi"></td>
                    </tr>
                    <tr>
                        <td colspan="4">Deskripsi</td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <textarea name="textDeskripsi" id="textDeskripsi" cols="30" rows="10" readonly class="form-control"></textarea>
                        </td>
                    </tr>
                </table>
                <h3>Detail Sparepart</h3>
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
                <h3>Detail Servis</h3>
                <table class="table table-striped" id="table-servis">
                    <thead>
                        <tr>
                            <th>Nama Servis</th>
                            <th>Biaya</th>
                        </tr>
                    </thead>
                    <tbody id="cart-servis-body">
                    </tbody>
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

    // ===== INIT DATATABLE SERVER SIDE =====
    const table = $('#tableLaporan').DataTable({
        processing: true,
        serverSide: true,
        order: [[1, 'desc']],
        ajax: {
            url: 'pages/admin_bengkel/api_laporan_jasa_service.php',
            type: 'GET',
            data: function (d) {
                d.tgl_dari     = $('input[name="tgl_dari"]').val();
                d.tgl_sampai   = $('input[name="tgl_sampai"]').val();
                d.id_pelanggan = $('select[name="id_pelanggan"]').val();
                d.id_user      = $('select[name="id_user"]').val();
                d.id_teknisi   = $('select[name="id_teknisi"]').val();
            }
        }
    });

    // ===== LOAD SUMMARY PERTAMA KALI =====
    loadSummary();

    // ===== FILTER SUBMIT (SATU KALI SAJA) =====
    $('#formFilter').on('submit', function(e){
        e.preventDefault();
        table.ajax.reload();   // reload datatable
        loadSummary();         // reload summary
    });

    // ===== DETAIL BUTTON =====
    $('#tableLaporan').on('click', '.btn-detail', function () {
        const faktur = $(this).data('faktur');
        const data   = table.row($(this).closest('tr')).data();

        $("#headTanggal").html(data[1]);
        $("#headNoFaktur").html(data[0]);
        $("#headKasir").html(data[5]);
        $("#headTeknisi").html(data[10]);
        $("#headPelanggan").html(data[2]);
        $("#headKendaraan").html(data[3]);
        $("#headNoPolisi").html(data[4]);

        // Ambil deskripsi
        $.getJSON('pages/admin_bengkel/api_get_transaksi.php', { no_faktur: faktur }, function (res) {
            const trx = res.data?.transaksi || {};
            $("#textDeskripsi").val(trx.deskripsi || '');
        });

        $('#modalDetail').modal('show');

        // ===== DETAIL SERVIS =====
        $('#table-servis').DataTable({
            destroy: true,
            ajax: {
                url: 'pages/admin_bengkel/api_get_transaksi.php',
                type: 'GET',
                data: { no_faktur: faktur },
                dataSrc: json => json.data.detail_servis || []
            },
            columns: [
                { data: 'nama_servis' },
                { data: 'biaya', render: d => 'Rp ' + parseInt(d).toLocaleString('id-ID') }
            ]
        });

        // ===== DETAIL SPAREPART =====
        $('#table-sparepart').DataTable({
            destroy: true,
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
            ]
        });
    });

    // ===== LOAD SUMMARY FUNCTION =====
    function loadSummary() {
        $.getJSON(
            'pages/admin_bengkel/api_laporan_jasa_service_summary.php',
            {
                tgl_dari: $('input[name="tgl_dari"]').val(),
                tgl_sampai: $('input[name="tgl_sampai"]').val(),
                id_pelanggan: $('select[name="id_pelanggan"]').val(),
                id_user: $('select[name="id_user"]').val(),
                id_teknisi: $('select[name="id_teknisi"]').val()
            },
            function (res) {
                if (res.success) {
                    $('#txtTotalTransaksi').text(
                        'Rp ' + parseInt(res.data.total_transaksi || 0).toLocaleString('id-ID')
                    );
                    $('#txtTotalSparepart').text(
                        'Rp ' + parseInt(res.data.total_sparepart || 0).toLocaleString('id-ID')
                    );
                    $('#txtTotalServis').text(
                        'Rp ' + parseInt(res.data.total_servis || 0).toLocaleString('id-ID')
                    );
                }
            }
        );
    }

});
</script>

