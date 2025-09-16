<?php
// asumsi session sudah start dan koneksi sudah tersedia ($conn)
function generateNoFaktur($conn) {
    $id_user = $_SESSION['id_user'];
    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $prefix = "PJ." . date("Ymd") . "." . $id_user . "." . $id_bengkel;
    $today = date("Y-m-d");
    $q = mysqli_query($conn, "SELECT COUNT(*) as total 
                              FROM transaksi 
                              WHERE DATE(tanggal)='$today' 
                                AND id_user='$id_user' 
                                AND id_bengkel='$id_bengkel'
                                AND jenis='penjualan'");
    $row = mysqli_fetch_assoc($q);
    $no_urut = str_pad($row['total'] + 1, 4, "0", STR_PAD_LEFT);

    return $prefix . "." . $no_urut;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_faktur   = $_POST['no_faktur'];
    $id_user     = $_POST['id_user'];
    $id_customer = $_POST['id_customer'];
    $id_bengkel  = $_POST['id_bengkel'];
    $jenis       = $_POST['jenis'];
    $tanggal     = $_POST['tanggal'] ?? date("Y-m-d");

    $spareparts  = json_decode($_POST['spareparts'], true);

    mysqli_begin_transaction($conn);
    try {
        $sql = "INSERT INTO transaksi (no_faktur, jenis, id_user, id_customer, id_bengkel, total, tanggal) 
                VALUES (?, ?, ?, ?, ?, 0, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssiiis", $no_faktur, $jenis, $id_user, $id_customer, $id_bengkel, $tanggal);
        mysqli_stmt_execute($stmt);
        $id_transaksi = mysqli_insert_id($conn);

        $total = 0;

        foreach ($spareparts as $sp) {
            $kode    = $sp['kode'];
            $nama    = $sp['nama'];
            $harga   = $sp['harga'];
            $qty     = $sp['qty'];
            $satuan  = $sp['satuan'];
            $subtotal= $harga * $qty;

            $sql_detail = "INSERT INTO transaksi_detail_sparepart 
                           (id_transaksi, kode_sparepart, nama_sparepart, harga, qty, satuan, subtotal)
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt2 = mysqli_prepare($conn, $sql_detail);
            mysqli_stmt_bind_param($stmt2, "sssiiid", $id_transaksi, $kode, $nama, $harga, $qty, $satuan, $subtotal);
            mysqli_stmt_execute($stmt2);

            if ($jenis == "penjualan") {
                mysqli_query($conn, "UPDATE sparepart SET stok = stok - $qty WHERE kode_sparepart='". mysqli_real_escape_string($conn, $kode) ."'");
            } else {
                mysqli_query($conn, "UPDATE sparepart SET stok = stok + $qty WHERE kode_sparepart='". mysqli_real_escape_string($conn, $kode) ."'");
            }

            $total += $subtotal;
        }

        $sql_upd = "UPDATE transaksi SET total=? WHERE id_transaksi=?";
        $stmt3 = mysqli_prepare($conn, $sql_upd);
        mysqli_stmt_bind_param($stmt3, "di", $total, $id_transaksi);
        mysqli_stmt_execute($stmt3);

        mysqli_commit($conn);
        echo "<script>
                Swal.fire({
                  icon: 'success',
                  title: 'Berhasil',
                  text: 'Transaksi berhasil disimpan (No Faktur: $no_faktur)'
                }).then(() => { window.location = 'transaksi.php'; });
              </script>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>
                Swal.fire({
                  icon: 'error',
                  title: 'Gagal',
                  text: 'Transaksi gagal: ". addslashes($e->getMessage()) ."'
                });
              </script>";
    }
}
?>

<div class="row">
  <div class="col-md-4">
    <div class="box box-primary">
      <div class="box-body">
        <form id="form-transaksi" method="POST" autocomplete="off">
          <input type="hidden" name="id_user" value="<?= $_SESSION['id_user']; ?>">
          <input type="hidden" name="id_bengkel" value="<?php 
              $q = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='".$_SESSION['id_user']."' LIMIT 1");
              $d = mysqli_fetch_assoc($q);
              echo $d['bengkel_id'];
          ?>">
          <input type="hidden" name="jenis" value="penjualan">

          <div class="form-group">
            <label>No Faktur</label>
            <input type="text" class="form-control" name="no_faktur" id="noFakturText" value="<?= generateNoFaktur($conn); ?>" readonly>
          </div>

          <h4><b>Daftar Sparepart</b></h4>
          <div class="form-group">
            <label>Pilih Sparepart [F1]</label>
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
                    data-harga="'.htmlspecialchars($row['harga_jual']).'" 
                    data-nama_sparepart="'.htmlspecialchars($row['nama_sparepart']).'" 
                    data-satuan="'.htmlspecialchars($row['satuan']).'">'
                    .htmlspecialchars($row['nama_sparepart']).' - '.number_format($row['harga_jual']).'/'.htmlspecialchars($row['satuan']).'</option>';
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>Jumlah</label>
            <input type="number" class="form-control" id="jumlah-barang-input" value="1" min="1">
          </div>
          <button type="button" class="btn btn-warning btn-block" id="btn-add-sparepart"><i class="fa fa-plus"></i> Tambah Sparepart</button>

          <input type="hidden" name="spareparts" id="input-spareparts">

          <button type="button" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;" data-toggle="modal" data-target="#modalSelesaiTransaksi">
              <i class="fa fa-check"></i> Selesai Transaksi
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="box box-warning">
      <div class="box-body" style="display: flex; justify-content: space-between; align-items: center; padding: 15px;">
        <h3 style="margin: 0; font-weight: bold;">TOTAL</h3>
        <h3 id="total-display" style="margin: 0; font-weight: bold; font-size: 40px;">Rp 0</h3>
      </div>
    </div>

    <div class="box">
      <div class="box-header"><h3 class="box-title">Keranjang Sparepart</h3></div>
      <div class="box-body table-responsive">
        <table class="table table-striped" id="table-sparepart">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama</th>
              <th>Harga</th>
              <th>Qty</th>
              <th>Satuan</th>
              <th>Subtotal</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="cart-barang-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Selesai Transaksi -->
<div class="modal fade" id="modalSelesaiTransaksi" tabindex="-1" role="dialog" aria-labelledby="modalSelesaiLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formSelesaiTransaksi">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSelesaiLabel"><i class="fa fa-check"></i> Konfirmasi Selesai Transaksi</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="textNoFakturModal">No Faktur</label>
            <input type="hidden" id="textUserId" name="id_user" value="<?= $_SESSION['id_user']; ?>">
            <input type="text" id="textNoFakturModal" name="no_faktur" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label for="dateTanggal">Tanggal</label>
            <input type="date" id="dateTanggal" name="tanggal" class="form-control" readonly value="<?= date("Y-m-d") ?>">
          </div>
          <div class="form-group">
            <label>Metode Bayar</label><br>
            <div class="radio">
              <label><input type="radio" name="metode_bayar" value="Tunai"> Tunai</label>
            </div>
            <div class="radio">
              <label><input type="radio" name="metode_bayar" value="Non Tunai"> Non Tunai / Kredit</label>
            </div>
          </div>
          <div class="form-group">
            <label for="pelanggan">Pelanggan</label>
            <select id="pelanggan" name="id_pelanggan" class="form-control" style="width:100%">
              <option value="">-- Pilih Pelanggan --</option>
              <?php
              $qPelanggan = mysqli_query($conn, "SELECT id_pelanggan, nama_pelanggan FROM pelanggans ORDER BY nama_pelanggan ASC");
              while($row = mysqli_fetch_assoc($qPelanggan)){
                  echo '<option value="'.htmlspecialchars($row['id_pelanggan']).'">'.htmlspecialchars($row['nama_pelanggan']).'</option>';
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="statusTransaksi">Status Transaksi</label>
            <select id="statusTransaksi" name="status" class="form-control" required>
              <option value="">-- Pilih Status --</option>
              <option value="selesai">Selesai</option>
              <option value="pending">Pending</option>
            </select>
          </div>

          <div class="form-group">
            <label for="totalBayar">Total Bayar</label>
            <input type="text" id="totalBayar" name="totalBayar" class="form-control" readonly>
          </div>

          <div class="form-group">
            <label for="uangBayar">Uang Dibayar</label>
            <input type="number" id="uangBayar" name="uangBayar" class="form-control" >
          </div>

          <div class="form-group">
            <label for="kembalian">Kembalian</label>
            <input type="text" id="kembalian" name="kembalian" class="form-control" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan & Cetak</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#sparepart-select').select2({ placeholder: "Cari sparepart...", allowClear: true });
    $('#pelanggan').select2({ placeholder: "Pilih Pelanggan", allowClear: true, width: "100%" });

    function reloadSparepartTable() {
        let noFaktur = $("#noFakturText").val();
        $("#table-sparepart").DataTable({
            destroy: true,
            ajax: {
                url: "pages/admin_bengkel/api_get_transaksi.php",
                type: "GET",
                data: { no_faktur: noFaktur },
                dataSrc: function(res) {
                    return res.data.detail_sparepart || [];
                }
            },
            columns: [
                { data: "kode_sparepart", title: "Kode" },
                { data: "nama_sparepart", title: "Nama Sparepart" },
                { 
                  data: "harga", 
                  title: "Harga",
                  render: function(data) {
                      return new Intl.NumberFormat('id-ID', { 
                          style: 'currency', 
                          currency: 'IDR',
                          minimumFractionDigits: 0,
                          maximumFractionDigits: 0
                      }).format(data);
                  }
                },
                { data: "qty", title: "Qty" },
                { data: "satuan", title: "Satuan" },
                { 
                  data: "subtotal", 
                  title: "Subtotal",
                  render: function(data) {
                      return new Intl.NumberFormat('id-ID', { 
                          style: 'currency', 
                          currency: 'IDR',
                          minimumFractionDigits: 0,
                          maximumFractionDigits: 0
                      }).format(data);
                  }
                },
                {
                  data: null,
                  title: "Action",
                  orderable: false,
                  render: function(data, type, row) {
                      return `
                        <button class="btn btn-xs btn-danger btn-delete-sparepart" data-id="${row.id_detail}">
                          <i class="fa fa-trash"></i>
                        </button>
                      `;
                  }
                }
            ]
        });
    }

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
                $("#total-display").html(totalIDR);
                $("#totalBayar").val(totalIDR);
            },
            error: function() {
                $("#total-display").html("Rp 0");
                $("#totalBayar").val("Rp 0");
            }
        });
    }

    $("#btn-add-sparepart").on("click", function() {
        let kode = $("#sparepart-select").val();
        let nama = $("#sparepart-select option:selected").data("nama_sparepart");
        let harga = parseInt($("#sparepart-select option:selected").data("harga"));
        let satuan = $("#sparepart-select option:selected").data("satuan");
        let qty = parseInt($("#jumlah-barang-input").val());

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
            harga: harga
        }, function(res){
            if (res.status_code == 400) {
                Swal.fire('Gagal!', res.message, 'warning');
            }
            reloadSparepartTable();
            sumTotal();
        }, "json");
    });

    $('#modalSelesaiTransaksi').on('show.bs.modal', function () {
        let noFaktur = $("#noFakturText").val();
        $("#textNoFakturModal").val(noFaktur);
        sumTotal(); // pastikan totalBayar diperbarui
    });

    $("#uangBayar").on("input", function(){
        let totalText = $("#totalBayar").val().replace(/[^\d]/g,'');
        let total = parseInt(totalText) || 0;
        let bayar = parseInt($(this).val()) || 0;
        let kembali = bayar - total;
        $("#kembalian").val(kembali >= 0 ? kembali : "0");
    });

    $("#formSelesaiTransaksi").on("submit", function(e){
        e.preventDefault();
        let metode = $('input[name="metode_bayar"]:checked').val();
        if (!metode) {
            Swal.fire('Mohon pilih metode bayar!');
            return false;
        }
        let pelanggan = $("#pelanggan").val();
        if (!pelanggan) {
            Swal.fire('Mohon pilih pelanggan!');
            return false;
        }
        this.submit();
    });

    reloadSparepartTable();
    sumTotal();
});
</script>
