<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$id_supplier = $_GET['id_supplier'] ?? '';
$id_user = $_GET['id_user'] ?? '';

function generateNoFaktur($conn) {
    $id_user = $_SESSION['id_user'];
    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $prefix = "PB." . date("Ymd") . "." . $id_user . "." . $id_bengkel;
    $today = date("Y-m-d");
    $q = mysqli_query($conn, "SELECT COUNT(*) as total 
                              FROM transaksi 
                              WHERE DATE(tanggal)='$today' 
                                AND id_user='$id_user' 
                                AND id_bengkel='$id_bengkel'
                                AND jenis='pembelian'");
    $row = mysqli_fetch_assoc($q);
    $no_urut = str_pad($row['total'] + 1, 4, "0", STR_PAD_LEFT);

    return $prefix . "." . $no_urut;
}

$where = "WHERE t.no_faktur LIKE '%PB%'";
if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_supplier) {
    $where .= " AND t.id_supplier = '$id_supplier'";
}
if ($id_user) {
    $where .= " AND t.id_user = '$id_user'";
}

// ADD: Total penjualan
$total_pembelian = 0;
$q_total = mysqli_query($conn, "
    SELECT SUM(t.total) as total_pembelian
    FROM transaksi t
    $where AND id_bengkel = '$id_bengkel'
");
if ($row = mysqli_fetch_assoc($q_total)) {
    $total_pembelian = $row['total_pembelian'] ?? 0;
}

// Get transaksi
$query_laporan = mysqli_query($conn, "
    SELECT t.no_faktur, t.tanggal, p.nama_supplier, u.nama_lengkap, t.total, t.status
    FROM transaksi t
    LEFT JOIN suppliers p ON t.id_supplier = p.id_supplier
    LEFT JOIN users u ON t.id_user = u.id_user
    $where AND id_bengkel = '$id_bengkel'
    ORDER BY t.tanggal DESC
");

// Dropdown data
$list_supplier = mysqli_query($conn, "SELECT id_supplier, nama_supplier FROM suppliers WHERE bengkel_id = '$id_bengkel'");
$list_user = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users WHERE bengkel_id = '$id_bengkel'");
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Laporan Pembelian</h3>
    </div>
    <div class="box-body">

        <div class="row">
            <!-- Filter Form -->
            <div class="col-md-8">
                <form method="get" class="form-inline" style="margin-bottom: 20px;">
                    <input type="hidden" name="page" value="pembelian">
                    <div class="form-group">
                        <label>Dari</label>
                        <input type="date" name="tgl_dari" class="form-control" value="<?= $tgl_dari ?>">
                    </div>
                    <div class="form-group">
                        <label>Sampai</label>
                        <input type="date" name="tgl_sampai" class="form-control" value="<?= $tgl_sampai ?>">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="id_supplier" class="form-control">
                            <option value="">-- Semua --</option>
                            <?php while ($p = mysqli_fetch_assoc($list_supplier)) : ?>
                                <option value="<?= $p['id_supplier']; ?>" <?= $id_supplier == $p['id_supplier'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama_supplier']) ?>
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
                    <h4>Total Pembelian</h4>
                    <p style="font-size: 18px; font-weight: bold;">Rp <?= number_format($total_pembelian, 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <!-- Button Tambah Pembelian -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-12 text-left">
                <button class="btn btn-success" data-toggle="modal" data-target="#modalTransaksiPembelian">
                <i class="fa fa-plus"></i> Tambah Pembelian
                </button>
            </div>
        </div>

        <!-- Table -->
        <table id="tableLaporan" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No Faktur</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>User Input</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($query_laporan)) : ?>
                    <tr>
                        <td><?= htmlspecialchars($row['no_faktur']); ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                        <td><?= htmlspecialchars($row['nama_supplier'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($row['nama_lengkap'] ?? '-'); ?></td>
                        <td>
                            <?php if ($row['status'] == 'selesai') : ?>
                                <span class="label label-success">selesai</span>
                            <?php else : ?>
                                <span class="label label-warning"><?= htmlspecialchars($row['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>Rp <?= number_format($row['total'], 0, ',', '.'); ?></td>
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
                <table id="tableBarangPembelian" class="table table-bordered table-striped">
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

<!-- Modal Tambah Pembelian -->
<div class="modal fade" id="modalTransaksiPembelian" tabindex="-1" role="dialog" aria-labelledby="labelTransaksiPembelian" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formTransaksiPembelian" method="POST" action="aksi_simpan_pembelian.php">
        <div class="modal-header">
          <h5 class="modal-title" id="labelTransaksiPembelian">Transaksi Pembelian</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <div class="row">
            <!-- LEFT SIDE -->
            <div class="col-md-6">
              <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="form-group">
                <label>Faktur</label>
                <input type="text" id="noFakturText" name="no_faktur" class="form-control" readonly value="<?= generateNoFaktur($conn); ?>">
                <input type="hidden" id="jenisTransaksiInput" name="jenis" class="form-control" readonly value="pembelian">
              </div>

              <div class="form-group">
                <label>Supplier</label>
                <select id='selectSupplierInput' name="id_supplier" class="form-control" required style="width:100%">
                  <option value="">-- Pilih Supplier --</option>
                  <?php
                  $qSupplier = mysqli_query($conn, "SELECT id_supplier, nama_supplier FROM suppliers WHERE bengkel_id = '$id_bengkel'");
                  while ($s = mysqli_fetch_assoc($qSupplier)) {
                      echo '<option value="'.$s['id_supplier'].'">'.htmlspecialchars($s['nama_supplier']).'</option>';
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label>Nama Akun</label>
                <select id='akunSelected' name="id_akun" class="form-control" required style="width:100%">
                  <option value="">-- Pilih Akun --</option>
                  <?php
                  $qAkun = mysqli_query($conn, "SELECT id_akun, nama_akun FROM akun WHERE id_bengkel = '$id_bengkel'");
                  while ($s = mysqli_fetch_assoc($qAkun)) {
                      echo '<option value="'.$s['id_akun'].'">'.htmlspecialchars($s['nama_akun']).'</option>';
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label>No. Akun</label>
                <input type="text" name="no_akun" class="form-control">
              </div>

              <div class="form-group">
                <label>Metode Pembayaran:</label><br>
                <label><input type="radio" name="metode_bayar" value="Tunai" checked> Tunai</label>
                <label style="margin-left: 15px;"><input type="radio" name="metode_bayar" value="Non Tunai"> Non Tunai</label>
              </div>
              
              <div class="form-group">
                <label for="statusTransaksi">Status Transaksi</label>
                <select id="statusTransaksi" name="status" class="form-control" required>
                  <option value="">-- Pilih Status --</option>
                  <option value="selesai">Selesai</option>
                  <option value="pending">Pending</option>
                </select>
              </div>
            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-6">
              <div class="form-group">
                <label>Pilih Sparepart</label>
                <select class="form-control" id="sparepart-select" style="width:100%;">
                <option value="">-- Pilih Sparepart --</option>
                <?php
                $id_user_sess = $_SESSION['id_user'];
                $q2 = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user_sess' LIMIT 1");
                $d2 = mysqli_fetch_assoc($q2);
                $id_bengkel2 = $d2['bengkel_id'];

                $qsp = mysqli_query($conn, "SELECT 
                    sp.kode_sparepart, 
                    sp.nama_sparepart, 
                    sp.harga_beli,
                    sp.hpp_per_pcs, 
                    st.nama_satuan as satuan,
                    hjs.harga_jual
                    FROM spareparts sp
                    JOIN harga_jual_sparepart hjs ON sp.id_sparepart = hjs.sparepart_id
                    JOIN satuan st ON hjs.satuan_jual_id = st.id_satuan
                    WHERE sp.bengkel_id = '$id_bengkel2'
                    ORDER BY sp.nama_sparepart ASC
                ");
                while($row = mysqli_fetch_assoc($qsp)) {
                    echo '<option 
                        value="'.htmlspecialchars($row['kode_sparepart']).'" 
                        data-harga="'.htmlspecialchars($row['hpp_per_pcs']).'" 
                        data-nama_sparepart="'.htmlspecialchars($row['nama_sparepart']).'" 
                        data-satuan="'.htmlspecialchars($row['satuan']).'">'
                        .htmlspecialchars($row['nama_sparepart']).' - '.number_format($row['hpp_per_pcs']).'/'.htmlspecialchars($row['satuan']).'</option>';
                }
                ?>
                </select>
              </div>

              <div class="form-group">
                <label>Jumlah</label>
                <input type="number" id="jumlahBarang" class="form-control" value="1" min="1">
              </div>

              <div class="form-group">
                <label>Diskon (%)</label>
                <input type="number" id="diskonBarang" class="form-control" min="0" max="100" value="0">
              </div>

              <div class="form-group">
                <button type="button" class="btn btn-warning btn-block" id="btnTambahBarang"><i class="fa fa-plus"></i> Tambah</button>
              </div>

              <div class="form-group text-right">
                <label>TOTAL</label>
                <div style="font-size: 24px; font-weight: bold; background: #ffffcc; padding: 10px; border-radius: 5px;" id="totalPembelian">
                  Rp 0
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive mt-3">
          <table class="table table-bordered table-striped" id="tableBarangPembelianDetail">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Satuan</th>
                        <th>Diskon (%)</th>
                        <th>Sub Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
           </table>

          </div>
        </div>

        <input type="hidden" name="total" id="inputTotalHidden" value="0">
        <input type="hidden" name="daftar_barang" id="inputDaftarBarang" value="">

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
          <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
$(document).ready(function () {
    $('#tableLaporan').DataTable();
    $('#selectSupplierInput').select2();
    $('#sparepart-select').select2();

    $('.btn-detail').on('click', function () {
        const faktur = $(this).data('faktur');
        $('#modalDetail').modal('show');

        $('#tableBarangPembelian').DataTable({
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

    $("#btnTambahBarang").on("click", function() {
        let kode = $("#sparepart-select").val();
        let nama = $("#sparepart-select option:selected").data("nama_sparepart");
        let harga = parseInt($("#sparepart-select option:selected").data("harga"));
        let satuan = $("#sparepart-select option:selected").data("satuan");
        let qty = parseInt($("#jumlahBarang").val());

        if (!kode) {
            Swal.fire('Pilih sparepart dulu!');
            return;
        }
        if (qty < 1) {
            Swal.fire('Jumlah harus minimal 1!');
            return;
        }

        // Tambah ke database detail
        $.post("pages/admin_bengkel/api_transaksi_sparepart.php", {
            action: "create",
            no_faktur: $("#noFakturText").val(),
            kode_sparepart: kode,
            nama_sparepart: nama,
            satuan: satuan,
            qty: qty,
            harga: harga,
            jenis_transaksi: 'pembelian'
        }, function(res){
            if (res.status_code == 400) {
                Swal.fire('Gagal!', res.message, 'warning');
            }
            reloadSparepartTable();
            sumTotal();
        }, "json");
    });

    function sumTotal() {
        let noFaktur = $("#noFakturText").val(); 
        $.ajax({
            url: "pages/admin_bengkel/api_get_transaksi.php", 
            type: "GET",
            data: { no_faktur: noFaktur },
            dataType: "json",
            success: function(res) {
                let total = 0;
                if(res.status_code === 200 && res.data.total !== undefined) {
                    total = res.data.total;
                }
                let totalIDR = new Intl.NumberFormat('id-ID', { 
                    style: 'currency', 
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(total);
                $("#totalPembelian").html(totalIDR);
                $("#inputTotalHidden").val(total);
            },
            error: function() {
                $("#totalPembelian").html("Rp 0");
            }
        });
    }

    $('#modalTransaksiPembelian').on('shown.bs.modal', function () {
        reloadSparepartTable();
        sumTotal();
    });
    
    function reloadSparepartTable() {
        let noFaktur = $("#noFakturText").val();
        // Memastikan modal sudah ter-load dan noFaktur tersedia
        // if (!noFaktur) {
        //     return;  // Jika noFaktur belum ada, jangan lanjutkan
        // }

        // Inisialisasi DataTable tanpa destroy: true
        const table = $('#tableBarangPembelianDetail').DataTable({
            destroy: true, // ✅ penting
            ajax: {
                url: "pages/admin_bengkel/api_get_transaksi.php",
                type: "GET",
                data: { no_faktur: noFaktur },
                dataSrc: function (res) {
                    return res.data.detail_sparepart || [];
                }
            },
            columns: [
                { data: "kode_sparepart" },
                { data: "nama_sparepart" },
                {
                data: "harga",
                render: data => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(data)
                },
                { data: "qty" },
                { data: "satuan" },
                {
                data: "discount",
                render: data => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(data)
                },
                {
                data: "subtotal",
                render: data => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(data)
                },
                {
                data: null,
                orderable: false,
                render: (data, type, row) => `
                    <button class="btn btn-xs btn-danger btn-delete-sparepart" data-id="${row.id_detail}">
                    <i class="fa fa-trash"></i> Hapus
                    </button>`
                }
            ]
        });

    }

    $("#formTransaksiPembelian").on("submit", function(e){
        e.preventDefault();
        let metode = $('input[name="metode_bayar"]:checked').val();
        if (!metode) {
            Swal.fire('Mohon pilih metode bayar!');
            return false;
        }e.preventDefault();
        let dataForm = $(this).serialize();
        $.ajax({
            url: "pages/admin_bengkel/api_selesai_transaksi.php",
            type: "POST",
            data: dataForm,
            dataType: "json",
            success: function(res){
                if(res.status_code == 200){
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: res.message
                    }).then(() => {
                        // redirect ke halaman cetak dan auto print
                        window.location.href = "pages/admin_bengkel/print_struk.php?no_faktur=" + res.data.no_faktur + "&auto_print=1";
                        // window.open("pages/admin_bengkel/print_struk.php?no_faktur=" + res.data.no_faktur, "_blank");

                        // reload halaman
                        // location.reload();
                    });
                } else {
                    Swal.fire("Error", res.message, "error");
                }
            },
            error: function(){
                Swal.fire("Error", "Terjadi kesalahan koneksi!", "error");
            }
        });
    });


});
</script>
