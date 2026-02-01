<?php
// asumsi session sudah start dan koneksi sudah tersedia ($conn)


function sanitize_input($data) {
  global $conn;
  return mysqli_real_escape_string($conn, trim($data));
}

function generateNoFaktur($conn, $tanggal_transaksi = null) {
  if (!$tanggal_transaksi) {
      $tanggal_transaksi = date("Y-m-d"); // default hari ini
  }

  $id_user = $_SESSION['id_user'] ?? null;
  if (!$id_user) {
      throw new Exception("User belum login");
  }

  $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
  if (!$q_user) throw new Exception("Gagal ambil bengkel: ".mysqli_error($conn));
  $d_user = mysqli_fetch_assoc($q_user);
  $id_bengkel = $d_user['bengkel_id'] ?? null;
  if (!$id_bengkel) throw new Exception("Bengkel user tidak ditemukan");

  // pastikan tanggal transaksi valid
  $tgl = date("Y-m-d", strtotime($tanggal_transaksi));
  $today = date("Ymd", strtotime($tanggal_transaksi));

  // ambil max urut, cast aman, fallback 0 jika null
  $q = mysqli_query($conn, "
      SELECT MAX(CAST(SUBSTRING_INDEX(no_faktur, '.', -1) AS UNSIGNED)) AS max_urut
      FROM transaksi
      WHERE id_user='$id_user'
        AND id_bengkel='$id_bengkel'
        AND jenis='penjualan'
  ");
  if (!$q) throw new Exception("Gagal ambil max faktur: ".mysqli_error($conn));
  $row = mysqli_fetch_assoc($q);
  $max_urut = isset($row['max_urut']) ? (int)$row['max_urut'] : 0;

  $no_urut = str_pad($max_urut + 1, 4, "0", STR_PAD_LEFT);

  return "PJ." . $today . "." . $id_user . "." . $id_bengkel . "." . $no_urut;
}

$user_role = get_user_role();
$allowed_roles = ['owner_bengkel', 'admin_bengkel', 'kasir'];

if (!in_array($user_role, $allowed_roles)) {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit();
}


// Tentukan ID bengkel yang bisa diakses oleh user
$accessible_bengkel_ids = [];
if ($user_role === 'owner_bengkel') {
    $owner_id = $_SESSION['id_user'];
    $query_bengkel_ids = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE owner_id = '$owner_id'");
    while ($row = mysqli_fetch_assoc($query_bengkel_ids)) {
        $accessible_bengkel_ids[] = $row['id_bengkel'];
    }
} else if ($user_role === 'admin_bengkel' || $user_role === 'kasir') {
    $user_id = $_SESSION['id_user'];
    $query_bengkel_admin = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user = '$user_id'");
    if ($row = mysqli_fetch_assoc($query_bengkel_admin)) {
        $accessible_bengkel_ids[] = $row['bengkel_id'];
    }
}
if (empty($accessible_bengkel_ids)) {
    echo "<div class='alert alert-danger'>Anda tidak terdaftar di bengkel manapun.</div>";
    exit();
}
$bengkel_ids_string = "'" . implode("','", $accessible_bengkel_ids) . "'";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $action = $_POST['action'] ?? '';
  $current_page = '?page=kasir';
  
  if ($action == 'tambah') {
      $nama_pelanggan = sanitize_input($_POST['nama_pelanggan']);
      $alamat = sanitize_input($_POST['alamat']);
      $telepon = sanitize_input($_POST['telepon']);
      $bengkel_id = sanitize_input($_POST['bengkel_id']);
      
      // Pastikan bengkel ID yang dikirim valid dan sesuai dengan yang diakses user
      if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
          header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
          exit();
      }

      $query_tambah = "INSERT INTO pelanggans (nama_pelanggan, alamat, telepon, bengkel_id) VALUES ('$nama_pelanggan', '$alamat', '$telepon', '$bengkel_id')";
      if (mysqli_query($conn, $query_tambah)) {
          header("Location: $current_page&status=success&message=Pelanggan berhasil ditambahkan.");
      } else {
          header("Location: $current_page&status=error&message=Gagal menambahkan pelanggan.");
      }
      exit();

  }
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
</style>

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
              <!-- opsi akan di-load otomatis lewat ajax -->
            </select>
          </div>
          <div class="form-group">
            <input type="text" class="form-control" id="kode-barang-input" placeholder="Kode Barang..." required readonly>
            <input type="text" class="form-control" id="nama-barang-input" placeholder="Nama Barang..." required readonly>
          </div>
          <div class="form-group">
          <label>Harga Jual Satuan</label>
            <select class="form-control" id="harga-jual-satuan" style="width:100%;">
              <!-- opsi akan di-load otomatis lewat ajax -->
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
          <button type="button" class="btn btn-default btn-block" id="btn-list-servis"><i class="fa fa-list"></i> List Pending</button>
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
              <th>Diskon</th>
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
          <div class="row">
            <!-- KIRI -->
            <div class="col-md-6">
              <div class="form-group">
                <label for="textNoFakturModal">No Faktur</label>
                <input type="hidden" id="textUserId" name="id_user" value="<?= $_SESSION['id_user']; ?>">
                <input type="text" id="textNoFakturModal" name="no_faktur" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label for="dateTanggal">Tanggal</label>
                <input type="date" id="dateTanggal" name="tanggal" class="form-control" value="<?= date("Y-m-d") ?>">
              </div>

              <div class="form-group">
                <label>Metode Bayar</label><br>
                <div class="radio">
                  <label><input type="radio" name="metode_bayar" value="Tunai" checked> Tunai</label>
                </div>
                <div class="radio">
                  <label><input type="radio" name="metode_bayar" value="Non Tunai"> Kredit</label>
                </div>
                <div class="radio">
                  <label><input type="radio" name="metode_bayar" value="Qris"> Qris</label>
                </div>
                <div class="radio">
                  <label><input type="radio" name="metode_bayar" value="Debit"> Debit</label>
                </div>
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
                <label for="jatuhTempo">Jatuh Tempo</label>
                <input id="jatuhTempo" name="tanggal_pelunasan" class="form-control" type="date" disabled readonly>
              </div>
            </div>

            <!-- KANAN -->
            <div class="col-md-6">
              <div class="form-group">
                <label for="pelanggan">Pelanggan</label>
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalTambahPelanggan"><i class="fa fa-plus"></i> Tambah Pelanggan</button>
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
                <label for="totalAwal">Total Awal</label>
                <input type="text" id="totalAwal" name="totalAwal" class="form-control" readonly value="0">
                <input type="hidden" id="totalAwalHidden" name="totalAwalHidden" class="form-control" readonly value="0">
              </div>

              <div class="form-group">
                <label for="diskon">Diskon (%)</label>
                <input type="number" id="diskon" name="diskon" class="form-control" step="0.01" min="0" max="100" value="0">
              </div>

              <div class="form-group">
                <label for="totalBayar">Total Bayar (Setelah Diskon)</label>
                <input type="text" id="totalBayar" name="total_bayar" class="form-control" readonly>
                <input type="hidden" id="totalBayarHidden" name="total_bayar_hidden" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label for="uangBayar">Uang Dibayar</label>
                <input type="text" id="uangBayar" name="uangBayar" class="form-control">
                <input type="hidden" id="uangBayarHidden" name="uangBayarHidden" class="form-control">
              </div>

              <div class="form-group">
                <label for="kembalian">Kembalian</label>
                <input type="text" id="kembalian" name="kembalian" class="form-control" readonly>
                <input type="hidden" id="kembalianHidden" name="kembalianHidden" class="form-control" readonly>
              </div>
            </div>
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

<div class="modal fade" id="modalPendingServis" tabindex="-1" role="dialog" aria-labelledby="modalPendingServisLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalListPending"><i class="fa fa-check"></i> List Pending Transaksi </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <div class="modal-body">
        <table id="tablePendingServis" class="table table-bordered table-striped" style="width:100%">
          <thead>
            <tr>
              <th style="width:100px">No Faktur</th>
              <th style="width:100%">Pelanggan</th>
              <th style="width:100px">Tanggal</th>
              <th style="width:100%">Total</th>
              <th style="width:100%">Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambahPelanggan" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Pelanggan</h4>
            </div>
            <form id="formTambahPelanggan" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_pelanggan">Nama Pelanggan</label>
                        <input type="text" class="form-control" id="nama_pelanggan" name="nama_pelanggan" required>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="text" class="form-control" id="telepon" name="telepon">
                    </div>
                    <div class="form-group">
                        <label for="bengkel_id_add">Bengkel</label>
                        <select class="form-control" id="bengkel_id_add" name="bengkel_id" required>
                            <?php 
                            $query_bengkel_add = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE id_bengkel IN ($bengkel_ids_string)");
                            while ($row_bengkel = mysqli_fetch_assoc($query_bengkel_add)) {
                                echo "<option value='{$row_bengkel['id_bengkel']}'>{$row_bengkel['nama_bengkel']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    $('#sparepart-select').select2({
      placeholder: '-- Pilih Sparepart --',
      allowClear: true,

      ajax: {
        url: "pages/admin_bengkel/api_get_spareparts.php",
        type: "POST",
        dataType: "json",
        delay: 250,
        data: function(params) {
          return {
            search: params.term || "",
            page: params.page || 1
          };
        },
        processResults: function(data, params) {
          return {
            results: data.items,
            pagination: { more: data.more }
          };
        },
        cache: true
      },

      // ========================================================
      // TEMPLATE RESULT (HASIL PENCARIAN)
      // ========================================================
      templateResult: function(item) {
        if (item.loading) return item.text;

        // harga_satuan langsung dari API gabungan
        const hargaList = item.harga_satuan || [];

        // Container utama
        let $container = $(`
          <div style="padding:6px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
              <div style="font-weight:bold; font-size:14px;">
                ${item.nama_sparepart}
              </div>
              <span style="
                background:#198754;
                color:#fff;
                font-size:11px;
                padding:2px 8px;
                border-radius:12px;
                font-weight:600;
              ">
                Stok: ${item.stok}
              </span>
            </div>
            <div class="harga-wrapper" style="display:flex; flex-wrap:wrap; gap:4px;"></div>
          </div>
        `);


        let $wrap = $container.find('.harga-wrapper');

        // ========== RENDER BADGE HARGA ==========
        hargaList.forEach(h => {
          let badge = $(`
            <span style="
              display:inline-block;
              padding:3px 6px;
              background:#e1f0ff;
              border:1px solid #b6d8ff;
              color:#004a99;
              border-radius:6px;
              font-size:11px;
              white-space:nowrap;
            ">
              Rp ${Number(h.harga_jual).toLocaleString('id-ID')}
            </span>
          `);

          $wrap.append(badge);
        });

        // Jika tidak ada harga
        if (hargaList.length === 0) {
          $wrap.append(`
            <span style="font-size:11px; color:#999;">
              <em>Tidak ada harga</em>
            </span>
          `);
        }

        return $container;
      },

      // ========================================================
      // TEMPLATE SELECTION (SETELAH DIPILIH)
      // ========================================================
      templateSelection: function(item) {
        return item.nama_sparepart || item.text;
      }
    });


    // Setelah sparepart dipilih dari Select2
    $('#sparepart-select').on('select2:select', function(e) {
        const data = e.params.data;

        // Isi kode dan nama sparepart
        $("#kode-barang-input").val(data.id);
        $("#nama-barang-input").val(data.nama_sparepart);

        // Kosongkan dropdown harga satuan dulu
        $("#harga-jual-satuan").empty().trigger("change");

        // Ambil harga jual per satuan dari API
        $.ajax({
            url: "pages/admin_bengkel/api_get_harga_jual.php",
            type: "POST",
            dataType: "json",
            data: { kode_sparepart: data.id },
            success: function(res) {
              if (res.status_code === 200 && res.data.harga_satuan && res.data.harga_satuan.length > 0) {
                  const hargaOptions = res.data.harga_satuan.map(item => ({
                      id: item.harga_jual,
                      text: `Rp ${new Intl.NumberFormat('id-ID').format(item.harga_jual)}`,
                      satuan: item.nama_satuan
                  }));

                  // Hancurkan instance lama sebelum buat baru
                  if ($("#harga-jual-satuan").hasClass("select2-hidden-accessible")) {
                      $("#harga-jual-satuan").select2('destroy');
                  }

                  $("#harga-jual-satuan")
                      .empty()
                      .select2({
                          placeholder: "-- Pilih Harga Satuan --",
                          data: hargaOptions,
                          width: "100%"
                      });
              } else {
                  Swal.fire("Harga Tidak Ditemukan", "Sparepart ini belum memiliki harga jual.", "warning");
              }
          },
        });
    });


    
    // shortcut keyboard
    $(document).on('keydown', function(e) {
        // F1 → Sparepart
        if (e.key === "F1") {
            e.preventDefault(); // cegah browser help
            $('#sparepart-select').select2('open');
        }
    });

    $('#pelanggan').select2({ placeholder: "Pilih Pelanggan", allowClear: true, width: "100%" });

    $("#table-sparepart").on("change", ".input-qty", function() {
        let id_detail = $(this).data("id");
        let qty = $(this).val();

        $.ajax({
            url: "pages/admin_bengkel/api_update_qty_sparepart.php",
            type: "POST",
            data: { id_detail: id_detail, qty: qty },
            success: function(res) {
                // reload biar subtotal ikut update
                reloadSparepartTable();
                sumTotal();
            },
            error: function(err) {
                alert("Gagal update qty");
            }
        });

    });

    $("#table-sparepart").on("change", ".input-diskon", function() {
        let id_detail = $(this).data("id");
        let diskon = $(this).val();

        $.ajax({
            url: "pages/admin_bengkel/api_update_diskon_sparepart.php",
            type: "POST",
            data: { id_detail: id_detail, diskon: diskon },
            success: function(res) {
                reloadSparepartTable();
                sumTotal();
            },
            error: function(err) {
                alert("Gagal update diskon");
            }
        });
    });
    $("#table-sparepart").on("click", ".btn-delete-sparepart", function() {
        let id_detail = $(this).data("id");

        if (!confirm("Yakin ingin menghapus sparepart ini?")) return;

        $.ajax({
            url: "pages/admin_bengkel/api_delete_sparepart.php",
            type: "POST",
            data: { id_detail: id_detail },
            success: function(res) {
                try {
                    let json = JSON.parse(res);
                    if (json.status === "success") {
                        reloadSparepartTable(); // refresh tabel setelah hapus
                        sumTotal();
                    } else {
                        alert("Gagal hapus: " + json.message);
                    }
                } catch(e) {
                    alert("Response error: " + res);
                }
            },
            error: function(err) {
                alert("Terjadi error saat hapus sparepart");
            }
        });
    });

    $("#table-sparepart").on("keyup", ".input-harga", function () {
        let nilai = $(this).val().replace(/[^\d]/g, ''); // buang selain angka
        let formatted = nilai.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        $(this).val(formatted); // <- INI YANG KURANG
    });

    $("#table-sparepart").on("change", ".input-harga", function() {
        let id_detail = $(this).data("id");
        
        let nilai = $(this).val().replace(/[^\d]/g, ''); // buang selain angka
        let formatted = nilai.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        $(this).val(formatted);

        $.ajax({
            url: "pages/admin_bengkel/api_update_harga_sparepart.php",
            type: "POST",
            data: { id_detail: id_detail, harga: nilai },
            success: function(res) {
                reloadSparepartTable();
                sumTotal();
            },
            error: function(err) {
                alert("Gagal update harga");
            }
        });
    });

    function reloadSparepartTable() {
        let noFaktur = $("#noFakturText").val();
        $("#table-sparepart").DataTable({
            destroy: true,
            order : [],
            ordering : false,
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
                  render: function(data, type, row) {
                      let harga = parseInt(data) || 0;
                      let hargaFormat = new Intl.NumberFormat('id-ID').format(harga);

                      return `
                        <input type="text" 
                              class="form-control form-control-sm input-harga" 
                              data-id="${row.id_detail}" 
                              value="${hargaFormat}" 
                              style="width:120px">
                      `;
                  }
                },
                { 
                  data: "qty", 
                  title: "Qty",
                  render: function(data, type, row) {
                      return `
                        <input type="number" 
                              class="form-control form-control-sm input-qty" 
                              data-id="${row.id_detail}" 
                              value="${data}" 
                              min="1" style="width:80px">
                      `;
                  }
                },
                { data: "satuan", title: "Satuan" },
                { 
                  data: "discount", title: "Diskon",
                  render: function(data, type, row) {
                      return `
                        <input type="number" 
                              class="form-control form-control-sm input-diskon" 
                              data-id="${row.id_detail}" 
                              value="${data}" 
                              min="1" step="0.01" style="width:80px">
                      `;
                  }
                },
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
                $("#totalAwal").val(totalIDR);
                $("#totalAwalHidden").val(parseAngka(totalIDR));
                $("#totalBayar").val(totalIDR);
                $("#totalBayarHidden").val(parseAngka(totalIDR));
                $("#uangBayar").val(totalIDR);
                $("#uangBayarHidden").val(parseAngka(totalIDR));
                $("#kembalian").val(0);
                $("#kembalianHidden").val(0);
            },
            error: function() {
                $("#total-display").html("Rp 0");
                $("#totalAwal").val("Rp 0");
                $("#totalBayar").val("Rp 0");
            }
        });
    }

    $("#btn-add-sparepart").on("click", function() {
        let kode = $("#kode-barang-input").val();
        let nama = $('#nama-barang-input').val();
        let harga = parseInt($('#harga-jual-satuan').val());
        let satuan = $('#harga-jual-satuan').select2('data')[0].satuan;
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
            kosongkanFormTambah();
        }, "json");
    });

    $('#modalSelesaiTransaksi').on('show.bs.modal', function () {
        let noFaktur = $("#noFakturText").val();
        $("#textNoFakturModal").val(noFaktur);
        $("#uangBayar").val(0)
        $("#uangBayarHidden").val(0)
        $("#kembalianHidden").val(0)
        $("#diskon").val(0)
        sumTotal(); // pastikan totalAwal diperbarui
        toggleJatuhTempo();
    });
    function formatAngka(angka) {
      return angka.toLocaleString('id-ID');
    }

    function parseAngka(str) {
      return parseInt(str.replace(/[^\d]/g, '')) || 0;
    }

    function hitungTransaksi() {
      let totalAwal = parseAngka($("#totalAwal").val());
      let diskon = parseFloat($("#diskon").val()) || 0;
      let uangBayar = parseAngka($("#uangBayar").val());
      let uangBayarHidden = parseAngka($("#uangBayar").val());

      // Hitung total setelah diskon
      let totalSetelahDiskon = totalAwal - (totalAwal * diskon / 100);
      let totalPembayaran = Math.floor(totalSetelahDiskon); // atau gunakan toFixed(2) jika desimal dibutuhkan

      // Hitung kembalian
      let kembalian = uangBayar - totalPembayaran;
      if (kembalian < 0) kembalian = 0;

      // Tampilkan hasil
      $("#totalBayar").val("Rp " + formatAngka(totalPembayaran));
      $("#kembalian").val("Rp " +formatAngka(kembalian));
      $("#totalBayarHidden").val(totalPembayaran);
      $("#uangBayarHidden").val(uangBayarHidden);
      $("#kembalianHidden").val(kembalian);
    }

    // Trigger saat input berubah
    $("#diskon").on("input", hitungTransaksi);
    
    $('#uangBayar').on('keyup', function () {
        let nilai = $(this).val();

        // Hapus semua karakter selain angka
        nilai = nilai.replace(/[^0-9]/g, '');

        // Format angka dengan titik setiap 3 digit dari belakang
        if (nilai) {
            nilai = nilai.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        $(this).val(nilai);
    }).on('input', hitungTransaksi);

    $("#formSelesaiTransaksi").on("submit", function(e){
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
                        kosongkanModal();
                        // redirect ke halaman cetak dan auto print
                        window.location.href = "pages/admin_bengkel/print_struk.php?no_faktur=" + res.no_faktur + "&auto_print=1";

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

    function toggleJatuhTempo() {
      const metode = $('input[name="metode_bayar"]:checked').val();
      if (metode === 'Tunai' || metode === 'Qris' || metode === 'Debit') {
        $('#jatuhTempo').prop('disabled', true).prop('readonly', true).val('');
      } else {
        $('#jatuhTempo').prop('disabled', false).prop('readonly', false);
      }
    }

    function kosongkanFormTambah() {
        // Kosongkan input teks
        document.getElementById('kode-barang-input').value = '';
        document.getElementById('nama-barang-input').value = '';
        document.getElementById('jumlah-barang-input').value = 1;
        document.getElementById('input-spareparts').value = '';

        // Reset select sparepart (jika pakai select2)
        $("#sparepart-select").val(null).trigger("change");
        $("#harga-jual-satuan").val(null).trigger("change");

        // Fokuskan kembali ke pilih sparepart
        setTimeout(() => {
            $("#sparepart-select").focus();
        }, 300);
    }

      function kosongkanModal() {
          // ========================
          // KIRI
          // ========================

          // No faktur (biasanya diisi ulang saat buka modal)
          document.getElementById('textNoFakturModal').value = '';

          // Set tanggal ke hari ini
          const today = new Date().toISOString().split('T')[0];
          document.getElementById('dateTanggal').value = today;

          // Metode bayar → default Tunai
          document.querySelectorAll('input[name="metode_bayar"]').forEach(el => {
              el.checked = false;
          });
          document.querySelector('input[name="metode_bayar"][value="Tunai"]').checked = true;

          // Status transaksi
          document.getElementById('statusTransaksi').value = '';

          // Jatuh tempo (disable + kosongkan)
          const jatuhTempo = document.getElementById('jatuhTempo');
          jatuhTempo.value = '';
          jatuhTempo.setAttribute('disabled', true);
          jatuhTempo.setAttribute('readonly', true);

          // ========================
          // KANAN
          // ========================

          // Pelanggan (jika pakai select2)
          if (window.jQuery && $('#pelanggan').hasClass("select2-hidden-accessible")) {
              $('#pelanggan').val(null).trigger('change');
          } else {
              document.getElementById('pelanggan').selectedIndex = 0;
          }

          // Total awal
          document.getElementById('totalAwal').value = '0';
          document.getElementById('totalAwalHidden').value = '0';

          // Diskon
          document.getElementById('diskon').value = 0;

          // Total bayar
          document.getElementById('totalBayar').value = '0';
          document.getElementById('totalBayarHidden').value = '0';

          // Uang dibayar
          document.getElementById('uangBayar').value = '';
          document.getElementById('uangBayarHidden').value = '0';

          // Kembalian
          document.getElementById('kembalian').value = '0';
          document.getElementById('kembalianHidden').value = '0';
      }


    
    $('input[name="metode_bayar"]').on('change', toggleJatuhTempo);

    $('#btn-list-servis').on('click', function() {
        $('#modalPendingServis').modal('show');

        // Inisialisasi atau reload DataTable
        if ( $.fn.DataTable.isDataTable('#tablePendingServis') ) {
            $('#tablePendingServis').DataTable().ajax.reload();
        } else {
            $('#tablePendingServis').DataTable({
                "ajax": {
                    "url": "pages/admin_bengkel/api_get_list_pending_transaction.php?jenis_faktur=PJ", // sesuaikan path API
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "no_faktur" },
                    { "data": "pelanggan" },
                    { "data": "tanggal" },
                    { 
                        "data": "total",
                        "render": function(data) {
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data);
                        }
                    },
                    { 
                        "data": null,
                        "render": function(data, type, row) {
                            return '<button class="btn btn-primary btn-sm btn-pilih" data-no_faktur="'+row.no_faktur+'">Pilih</button>';
                        }
                    }
                ],
                scrollY:500,
                deferRender:true,
                scroller:true,
                ordering: false,
            });
        }
    });

    $('#tablePendingServis').on('click', '.btn-pilih', function() {
        var noFaktur = $(this).data('no_faktur');

        $("#noFakturText").val(noFaktur);

        
        reloadSparepartTable();
        sumTotal();
        $('#modalPendingServis').modal('hide');
    });


    reloadSparepartTable();
    sumTotal();
});
</script>
