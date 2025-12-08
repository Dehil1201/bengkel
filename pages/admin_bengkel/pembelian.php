<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? date('Y-m-d');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-d');
$id_supplier = $_GET['id_supplier'] ?? '';
$id_user = $_GET['id_user'] ?? '';

function generateNoFaktur($conn, $tanggal_transaksi = null) {

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!$tanggal_transaksi) {
        $tanggal_transaksi = date("Y-m-d");
    }

    $id_user = $_SESSION['id_user'] ?? null;
    if (!$id_user) {
        throw new Exception("User belum login");
    }

    // Ambil bengkel user
    $stmtUser = mysqli_prepare($conn, "
        SELECT bengkel_id 
        FROM users 
        WHERE id_user = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtUser, "i", $id_user);
    mysqli_stmt_execute($stmtUser);
    $resUser = mysqli_stmt_get_result($stmtUser);
    $d_user  = mysqli_fetch_assoc($resUser);

    $id_bengkel = $d_user['bengkel_id'] ?? null;
    if (!$id_bengkel) {
        throw new Exception("Bengkel user tidak ditemukan");
    }

    $todayYmd = date("Ymd", strtotime($tanggal_transaksi));
    $todaySql = date("Y-m-d", strtotime($tanggal_transaksi));

    mysqli_begin_transaction($conn);

    try {

        // 🔥 FILTER BERDASAR FAKTUR + TANGGAL + BENGKEL (PALING AMAN)
        $stmt = mysqli_prepare($conn, "
            SELECT MAX(CAST(SUBSTRING_INDEX(no_faktur, '.', -1) AS UNSIGNED)) AS max_urut
            FROM transaksi
            WHERE id_bengkel = ?
              AND no_faktur LIKE CONCAT('PB.', ?, '.%')
        ");

        mysqli_stmt_bind_param($stmt, "is", $id_bengkel, $todayYmd);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);

        $max_urut = (int)($row['max_urut'] ?? 0);
        $no_urut  = str_pad($max_urut + 1, 4, "0", STR_PAD_LEFT);

        mysqli_commit($conn);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    return "PB.$todayYmd.$id_user.$id_bengkel.$no_urut";
}




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
                <form class="form-inline" style="margin-bottom: 20px;">
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
                    <p style="font-size: 18px; font-weight: bold;" id="totalPembelianCallout"></p>
                </div>
            </div>
        </div>
        <!-- Button Tambah Pembelian -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-12 text-left">
                <button class="btn btn-success" data-toggle="modal" data-target="#modalTransaksiPembelian">
                <i class="fa fa-plus"></i> Tambah Pembelian
                </button>
                <button type="button" class="btn btn-default" id="btn-list-servis"><i class="fa fa-list"></i> List Pending</button>
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
            <tbody></tbody>
        </table>
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
          <thead style="width:100%">
            <tr>
              <th style="width:100%">No Faktur</th>
              <th style="width:100%">Supplier</th>
              <th style="width:100%">Tanggal</th>
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
            <div class="col-md-12">
              <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Faktur</label>
                        <input type="text" id="noFakturText" name="no_faktur" class="form-control"  value="<?= generateNoFaktur($conn); ?>">
                        <input type="hidden" id="jenisTransaksiInput" name="jenis" class="form-control" readonly value="pembelian">
                    </div>

                    <div class="form-group">
                        <label>Supplier</label>
                        <select id='selectSupplierInput' name="id_supplier" class="form-control" style="width:100%">
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
                        <select id='akunSelected' name="id_akun" class="form-control" style="width:100%">
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
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Metode Pembayaran:</label><br>
                        <label><input type="radio" name="metode_bayar" value="Tunai" checked> Tunai</label>
                        <label style="margin-left: 15px;"><input type="radio" name="metode_bayar" value="Non Tunai"> Non Tunai</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="statusTransaksi">Status Transaksi</label>
                        <select id="statusTransaksi" name="status" class="form-control" required>
                        <option value="">Pilih Status</option>
                        <option value="selesai">Selesai</option>
                        <option value="pending">Pending</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jatuh Tempo</label>
                        <input type="date" name="tanggal_pelunasan" class="form-control" id="jatuhTempo" readonly>
                        <input type="hidden" name="uangBayarHidden" class="form-control" id="uangBayar">
                    </div>
                </div>
              </div>
              
            <hr>
            </div>
            <!-- RIGHT SIDE -->
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Pilih Sparepart [F1]</label>
                            <select class="form-control" id="sparepart-select" style="width:100%;">
                                <!-- opsi akan diload otomatis via ajax -->
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Harga</label>
                            <input type="text" id="hargaBeli" class="form-control">
                            <input type="hidden" id="hargaBeliRaw" name="harga_beli">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Jumlah</label>
                            <input type="number" id="jumlahBarang" class="form-control" value="1" min="1">
                        </div>
                    </div>
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
                <input type="hidden" id="totalBayar" name="total_bayar_hidden">
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

    // Reset form saat modal ditutup
    $('#modalTransaksiPembelian').on('hidden.bs.modal', function () {
        const form = $('#formTransaksiPembelian')[0];

        // Reset semua input di form
        form.reset();

        // Reset Select2 sparepart
        if ($('#sparepart-select').hasClass('select2-hidden-accessible')) {
            $('#sparepart-select').val(null).trigger('change');
        }

        // Reset Select2 akun/supplier jika pakai select2
        if ($('#selectSupplierInput').hasClass('select2-hidden-accessible')) {
            $('#selectSupplierInput').val(null).trigger('change');
        }
        if ($('#akunSelected').hasClass('select2-hidden-accessible')) {
            $('#akunSelected').val(null).trigger('change');
        }

        // Reset hidden input
        $('#hargaBeliRaw').val('');
        $('#inputTotalHidden').val('0');
        $('#inputDaftarBarang').val('');
        $('#uangBayar').val('');
        $('#jatuhTempo').val('').prop('readonly', false);

        // Reset DataTable detail barang
        if ($.fn.DataTable.isDataTable('#tableBarangPembelianDetail')) {
            $('#tableBarangPembelianDetail').DataTable().clear().draw();
        }

        // Reset total di tampilan
        $('#totalPembelian').text('Rp 0');
    });

    
    $('#selectSupplierInput').select2();

    
    // Format angka ribuan
    const formatID = (num) => new Intl.NumberFormat('id-ID').format(num);

    // Hilangkan format (jadi integer string)
    const unformatID = (str) => str.replace(/\./g, "").replace(/,/g, "");


    // === KETIKA SPAREPART DIPILIH DARI SELECT2 ===
    $('#sparepart-select').on('select2:select', function(e) {
        const data = e.params.data;

        // set input tampilannya (format ribuan)
        $("#hargaBeli").val(formatID(data.harga_beli));

        // set nilai raw tanpa format untuk backend
        $("#hargaBeliRaw").val(data.harga_beli);
    });


    // === AUTO FORMAT SAAT USER MENGETIK ===
    $("#hargaBeli").on("input", function () {

        // ambil nilai tanpa titik
        let raw = unformatID($(this).val());

        // kalau kosong → set hidden juga kosong
        if (raw === "") {
            $("#hargaBeliRaw").val("");
            return;
        }

        // simpan raw ke hidden
        $("#hargaBeliRaw").val(raw);

        // format ulang tampilan
        $(this).val(formatID(raw));
    });


    $('#sparepart-select').select2({
        placeholder: '-- Pilih Sparepart --',
        allowClear: true,
        width: "100%",
        ajax: {
            url: 'pages/admin_bengkel/api_get_spareparts.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term || "",
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                return {
                    results: data.items,
                    pagination: { more: data.more }
                };
            },
            cache: true
        },
        templateResult: function(item) {
            if (item.loading) return item.text;
            return `${item.nama_sparepart} (${item.id})`;
        },
        templateSelection: function(item) {
            return item.nama_sparepart || item.text;
        }
    });


    // Shortcut keyboard F1 → Buka sparepart
    $(document).on('keydown', function(e) {
        if (e.key === "F1") {
            e.preventDefault();
            $('#sparepart-select').select2('open');
        }
    });


    $(document).on('click', '.btn-detail', function () {
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


    $("#btnTambahBarang").off("click").on("click", function(e) {
        e.preventDefault(); // cegah submit form bawaan

        let kode = $("#sparepart-select").val();

        let selected = $('#sparepart-select').select2('data')[0] || {};
        let nama = selected.nama_sparepart || "";
        let satuan = "PCS";

        let harga = parseInt($("#hargaBeliRaw").val()) || 0;
        let qty = parseInt($("#jumlahBarang").val()) || 0;
        let diskon = parseInt($("#diskonBarang").val()) || 0;

        if (!kode) {
            Swal.fire('Pilih sparepart dulu!');
            return;
        }
        if (qty < 1) {
            Swal.fire('Jumlah harus minimal 1!');
            return;
        }

        // 🔒 KUNCI TOMBOL AGAR TIDAK BISA KLIK DOBEL
        $("#btnTambahBarang").prop("disabled", true);

        $.post("pages/admin_bengkel/api_transaksi_sparepart.php", {
            action: "create",
            no_faktur: $("#noFakturText").val(),
            kode_sparepart: kode,
            nama_sparepart: nama,
            satuan: satuan,
            qty: qty,
            harga: harga,
            discount: diskon,
            jenis_transaksi: 'pembelian'
        }, function(res){

            // 🔓 BUKA LAGI TOMBOL
            $("#btnTambahBarang").prop("disabled", false);

            if (res.status_code != 200) {
                Swal.fire('Gagal!', res.message, 'warning');
                return;
            }

            reloadSparepartTable();

            $("#sparepart-select").val(null).trigger('change');
            $("#hargaBeli").val('');
            $("#hargaBeliRaw").val('');
            $("#jumlahBarang").val(1);
            $("#diskonBarang").val(0);

            sumTotal();

        }, "json")
        .fail(function(){
            $("#btnTambahBarang").prop("disabled", false);
            Swal.fire('Error!', 'Server tidak merespon', 'error');
        });

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
                $("#totalBayar").val(total)
                $("#inputTotalHidden").val(total);
                $("#uangBayar").val(total);
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
        const table = $('#tableBarangPembelianDetail').DataTable({
            destroy: true, 
            ordering: false,
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

                // 🔥 Kolom Harga — editable
                {
                    data: "harga",
                    render: function(data, type, row) {
                        let formatted = new Intl.NumberFormat("id-ID").format(data);
                        return `
                            <input type="text" class="form-control form-control-sm input-harga" 
                                data-id="${row.id_detail}"
                                value="${formatted}"
                                style="width:100px; text-align:right;">
                        `;
                    }
                },

                {
                    data: "qty",
                    render: function(data, type, row) {
                        return `
                            <input type="number" class="form-control form-control-sm input-qty"
                                data-id="${row.id_detail}"
                                value="${data}"
                                min="1"
                                style="width:60px; text-align:center;">
                        `;
                    }
                },

                { data: "satuan" },
                {
                    data: "discount",
                    render: function(data, type, row) {
                        let formatted = new Intl.NumberFormat("id-ID").format(data);
                        return `
                            <input type="text" class="form-control form-control-sm input-diskon"
                                data-id="${row.id_detail}"
                                value="${formatted}"
                                style="width:80px; text-align:right;">
                        `;
                    }
                },

                {
                    data: "subtotal",
                    render: data => new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0
                    }).format(data)
                },

                {
                    data: null,
                    orderable: false,
                    render: (data, type, row) => `
                        <button type="button" class="btn btn-xs btn-danger btn-delete-sparepart" data-id="${row.id_detail}">
                        <i class="fa fa-trash"></i> Hapus
                        </button>`
                }
            ]
        });

    }

    $(document).on("input", ".input-harga", function () {
        let val = $(this).val().replace(/\./g, "");
        if (isNaN(val) || val === "") val = 0;

        $(this).val(new Intl.NumberFormat("id-ID").format(val));
    });

    let timer = null;

    $(document).on("change", ".input-harga, .input-qty, .input-diskon", function () {
        clearTimeout(timer);
        let input = $(this);

        timer = setTimeout(() => {
            updateDetail(input);
        }, 400);
    });


    $(document).on("input", ".input-diskon", function () {
        let val = $(this).val().replace(/\./g, "");
        if (isNaN(val) || val === "") val = 0;

        $(this).val(new Intl.NumberFormat("id-ID").format(val));
    });

    function updateDetail(input) {
        let id = input.data("id");

        let row = input.closest("tr");

        let harga = row.find(".input-harga").val().replace(/\./g, "") * 1;
        let qty = row.find(".input-qty").val() * 1;
        let diskon = row.find(".input-diskon").val().replace(/\./g, "") * 1;

        $.post("pages/admin_bengkel/api_update_detail_sparepart.php", {
            id_detail: id,
            harga: harga,
            qty: qty,
            discount: diskon
        }, function(res) {

            if (res.status_code !== 200) {
                Swal.fire("Gagal", res.message, "warning");
                return;
            }
            reloadSparepartTable()
            sumTotal();
        }, "json");
    }


    $("input[name='metode_bayar']").on("change", function () {
        const metode = $(this).val();

        if (metode === "Tunai") {
            // Uang bayar = total
            sumTotal();

            // Jatuh tempo readonly
            $("#jatuhTempo").prop("readonly", true);
            $("#jatuhTempo").val("-"); // tanda atau kosong
        } else {
            // Non Tunai → uang bayar 0
            $("#uangBayar").val(0);

            // Jatuh tempo editable
            $("#jatuhTempo").prop("readonly", false);
            $("#jatuhTempo").val(""); // kosongkan agar bisa diisi
        }
    });




    $("#formTransaksiPembelian").on("submit", function(e){
        e.preventDefault();

        let metode = $('input[name="metode_bayar"]:checked').val();
        if (!metode) {
            Swal.fire('Mohon pilih metode bayar!');
            return false;
        }

        // Ambil semua data dari DataTable
        const table = $('#tableBarangPembelianDetail').DataTable();
        const allData = table.rows().data().toArray();
        if(allData.length === 0){
            Swal.fire('Belum ada barang di tabel!');
            return false;
        }

        // Simpan ke hidden input sebagai JSON string
        $('#inputDaftarBarang').val(JSON.stringify(allData));

        // Atur uang bayar & jatuh tempo sesuai metode
        const total = parseFloat($('#inputTotalHidden').val()) || 0;
        if(metode === 'Tunai'){
            $('#uangBayar').val(total);
            $('#jatuhTempo').prop('readonly', true);
        } else {
            $('#uangBayar').val(0);
            $('#jatuhTempo').prop('readonly', false);
        }

        // Serialize form & kirim AJAX
        let dataForm = $(this).serialize();

        $.ajax({
            url: "pages/admin_bengkel/api_selesai_transaksi.php",
            type: "POST",
            data: dataForm,
            dataType: "json",

            // ✅ LOADING DIMULAI
            beforeSend: function () {
                Swal.fire({
                    title: 'Menyimpan Transaksi...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // disable tombol submit supaya tidak dobel submit
                $("#formTransaksiPembelian button[type='submit']")
                    .prop("disabled", true);
            },

            success: function(res){
                Swal.close();

                $("#formTransaksiPembelian button[type='submit']")
                    .prop("disabled", false);

                if(res.status_code == 200){
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: res.message
                    }).then(() => {
                        resetModalPembelian();

                        tableLaporan.ajax.reload(null,false);
                        loadTotalPembelian();
                        window.location.href =
                            "pages/admin_bengkel/print_struk.php?no_faktur=" +
                            res.data.no_faktur + "&auto_print=1";
                    });
                } else {
                    Swal.fire("Error", res.message, "error");
                }
            },

            error: function(){
                Swal.close();

                $("#formTransaksiPembelian button[type='submit']")
                    .prop("disabled", false);

                Swal.fire("Error", "Terjadi kesalahan koneksi!", "error");
            }
        });
    });


    $(document).on("click", ".btn-delete-sparepart", function() {
        let id_detail = $(this).data("id");
        let noFaktur = $("#noFakturText").val();

        Swal.fire({
            title: 'Yakin ingin menghapus sparepart ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if(result.isConfirmed){
                $.post("pages/admin_bengkel/api_transaksi_sparepart.php", {
                    action: "delete",        // <-- action delete
                    id_detail: id_detail,    // <-- id detail yang mau dihapus
                    no_faktur: noFaktur,
                    jenis_transaksi: 'pembelian'
                }, function(res){
                    if(res.status_code == 200){
                        Swal.fire('Terhapus!', res.message, 'success');
                        reloadSparepartTable();
                        sumTotal();
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                }, "json");
            }
        });
    });

    function resetModalPembelian(){
        $('#formTransaksiPembelian')[0].reset();

        $('#selectSupplierInput').val('').trigger('change');
        $('#akunSelected').val('').trigger('change');
        $('#sparepart-select').val('').trigger('change');

        $('#tableBarangPembelianDetail').DataTable().clear().draw();

        $('#totalPembelianCallout').html('Rp 0');
        $('#totalBayar').val(0);
        $('#inputTotalHidden').val(0);
        $('#inputDaftarBarang').val('');

        $('#hargaBeli').val('');
        $('#hargaBeliRaw').val('');
        $('#jumlahBarang').val(1);
        $('#diskonBarang').val(0);
    }


    const tableLaporan = $('#tableLaporan').DataTable({
        processing: true,
        serverSide: true,
        scrollX:true,
        order: [[1, 'desc']],
        ajax: {
            url: "pages/admin_bengkel/api_laporan_pembelian_server.php",
            type: "GET",
            data: function(d) {
                d.tgl_dari     = "<?= $tgl_dari ?>";
                d.tgl_sampai   = "<?= $tgl_sampai ?>";
                d.id_supplier = "<?= $id_supplier ?>";
                d.id_user     = "<?= $id_user ?>";
            }
        },
        columns: [
            { data: "no_faktur" },
            { data: "tanggal" },
            { data: "supplier" },
            { data: "user" },
            { data: "status" },
            { data: "total" },
            { data: "aksi", orderable:false, searchable:false }
        ]
    });


    function loadTotalPembelian(){
        $.get("pages/admin_bengkel/api_total_pembelian.php", {
            tgl_dari: "<?= $tgl_dari ?>",
            tgl_sampai: "<?= $tgl_sampai ?>",
            id_supplier: "<?= $id_supplier ?>",
            id_user: "<?= $id_user ?>"
        }, function(res){
            $("#totalPembelianCallout").html(res.total_format);
        }, "json");
    }

    
    $('#btn-list-servis').on('click', function() {
        $('#modalPendingServis').modal('show');

        // Inisialisasi atau reload DataTable
        if ( $.fn.DataTable.isDataTable('#tablePendingServis') ) {
            $('#tablePendingServis').DataTable().ajax.reload();
        } else {
            $('#tablePendingServis').DataTable({
                "ajax": {
                "url": "pages/admin_bengkel/api_get_list_pending_transaction.php?jenis_faktur=PB", // sesuaikan path API
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "no_faktur" },
                    { "data": "supplier" },
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
                responsive: true,
                scrollY:500,
                deferRender:true,
                scroller:true
            });
        }
    });

    $('#tablePendingServis').on('click', '.btn-pilih', function() {
        var noFaktur = $(this).data('no_faktur');

        $("#noFakturText").val(noFaktur);

        reloadSparepartTable();
        sumTotal();

        $('#modalPendingServis').modal('hide');

        setTimeout(function() {
            $('#modalTransaksiPembelian').modal('show');

            // ✅ FORCE REFRESH SCROLL BOOTSTRAP
            setTimeout(function () {
                $('body').addClass('modal-open');
            }, 150);

        }, 300);
    });


    loadTotalPembelian();



});

</script>
