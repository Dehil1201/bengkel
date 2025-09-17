<?php

// ✅ Generate nomor faktur otomatis
function generateNoFaktur($conn) {
    // ambil id_user dari session
    $id_user = $_SESSION['id_user'];

    // cari bengkel_id dari user
    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    // prefix: PJ.YYYYMMDD.id_user.id_bengkel.no_urut
    $prefix = "PS." . date("Ymd") . "." . $id_user . "." . $id_bengkel;

    // hitung transaksi hari ini untuk user & bengkel
    $today = date("Y-m-d");
    $q = mysqli_query($conn, "SELECT COUNT(*) as total 
                              FROM transaksi 
                              WHERE DATE(tanggal)='$today' 
                              AND id_user='$id_user' 
                              AND id_bengkel='$id_bengkel'");
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

    $spareparts  = json_decode($_POST['spareparts'], true);
    $services    = json_decode($_POST['services'], true);

    mysqli_begin_transaction($conn);
    try {
        // 1. Insert ke transaksi (header)
        $sql = "INSERT INTO transaksi (no_faktur, jenis, id_user, id_customer, id_bengkel, total) 
                VALUES ('$no_faktur', '$jenis', '$id_user', '$id_customer', '$id_bengkel', 0)";
        mysqli_query($conn, $sql);
        $id_transaksi = mysqli_insert_id($conn);

        $total = 0;

        // 2. Sparepart detail
        foreach ($spareparts as $sp) {
            $kode    = $sp['kode'];
            $nama    = $sp['nama'];
            $harga   = $sp['harga'];
            $qty     = $sp['qty'];
            $satuan  = $sp['satuan'];
            $subtotal= $harga * $qty;

            $sql_detail = "INSERT INTO transaksi_detail_sparepart 
                           (id_transaksi, kode_sparepart, nama_sparepart, harga, qty, satuan, subtotal)
                           VALUES ('$id_transaksi', '$kode', '$nama', '$harga', '$qty', '$satuan', '$subtotal')";
            mysqli_query($conn, $sql_detail);

            // update stok
            if ($jenis == "penjualan") {
                mysqli_query($conn, "UPDATE sparepart SET stok = stok - $qty WHERE kode_sparepart='$kode'");
            } else {
                mysqli_query($conn, "UPDATE sparepart SET stok = stok + $qty WHERE kode_sparepart='$kode'");
            }

            $total += $subtotal;
        }

        // 3. Servis detail
        foreach ($services as $srv) {
            $nama  = $srv['nama'];
            $biaya = $srv['biaya'];

            $sql_servis = "INSERT INTO transaksi_detail_servis 
                          (id_transaksi, kode_servis, nama_servis, biaya)
                           VALUES ('$id_transaksi', '', '$nama', '$biaya')";
            mysqli_query($conn, $sql_servis);

            $total += $biaya;
        }

        // 4. Update total
        mysqli_query($conn, "UPDATE transaksi SET total='$total' WHERE id_transaksi='$id_transaksi'");

        mysqli_commit($conn);
        echo "<script>alert('Transaksi berhasil disimpan dengan No Faktur: $no_faktur'); window.location='transaksi.php';</script>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Transaksi gagal: " . $e->getMessage() . "');</script>";
    }
}
?>


<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-body">
                <form id="form-transaksi" method="POST">
                    <input type="hidden" name="id_user" value="">
                    <input type="hidden" name="id_customer" value="">
                    <input type="hidden" name="id_bengkel" value="">
                    <input type="hidden" name="jenis" value="penjualan">

                    <div class="form-group">
                        <label>No Faktur</label>
                        <input type="text" class="form-control" name="no_faktur" value="<?php echo generateNoFaktur($conn); ?>" id = "noFakturText" readonly>
                    </div>

                    <h4><b>DAFTAR SPAREPART</b></h4>
                    <div class="form-group">
                        <label>Pilih Sparepart [F1]</label>
                        <select class="form-control" id="sparepart-select" style="width:100%;">
                            <option value="">-- Pilih Sparepart --</option>
                            <?php
                            $qsp = mysqli_query($conn, "SELECT 
                            sp.kode_sparepart, 
                            sp.nama_sparepart, 
                            sp.hpp_per_pcs, 
                            st.nama_satuan as satuan,
                            hjs.harga_jual
                                FROM spareparts sp
                                JOIN harga_jual_sparepart hjs ON sp.id_sparepart = hjs.sparepart_id
                                JOIN satuan st ON hjs.satuan_jual_id = st.id_satuan
                                WHERE sp.bengkel_id = '$id_bengkel'
                                ORDER BY sp.nama_sparepart ASC
                                ");
                            while($row = mysqli_fetch_assoc($qsp)) {
                                echo '<option 
                                value="'.$row['kode_sparepart'].'" 
                                data-harga="'.$row['harga_jual'].'"'.'" 
                                data-nama_sparepart="'.$row['nama_sparepart'].'"'.'" 
                                data-satuan="'.$row['satuan'].'">'
                                .$row['nama_sparepart'].'-'.number_format($row['harga_jual']).'/'.$row['satuan'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jumlah</label>
                        <input type="number" class="form-control" id="jumlah-barang-input" value="1" min="1">
                    </div>
                    <button type="button" class="btn btn-warning btn-block" id="btn-add-sparepart"><i class="fa fa-plus"></i> Tambah Sparepart</button>

                    <h4 style="margin-top:20px;"><b>JENIS SERVIS</b></h4>
                    <div class="form-group">
                        <label>Pilih Jenis Servis [F7]</label>
                        <select class="form-control" id="servis-select" style="width:100%;">
                            <option value="">-- Pilih Servis --</option>
                            <?php
                            $q = mysqli_query($conn, "SELECT id_servis, nama_servis, biaya FROM jenis_servis where bengkel_id = '$id_bengkel' ORDER BY nama_servis ASC");
                            while($row = mysqli_fetch_assoc($q)) {
                                echo '<option value="'.$row['id_servis'].'" 
                                        data-biaya="'.$row['biaya'].'">'.$row['nama_servis'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Biaya Servis</label>
                        <input type="number" class="form-control" id="biaya-servis-input" value="0">
                    </div>
                    <button type="button" class="btn btn-info btn-block" id="btn-add-servis"><i class="fa fa-plus"></i> Tambah Servis</button>
                    <button type="button" class="btn btn-default btn-block" id="btn-list-servis"><i class="fa fa-list"></i> List Pending Servis</button>

                    <!-- Hidden input untuk keranjang -->
                    <input type="hidden" name="spareparts" id="input-spareparts">
                    <input type="hidden" name="services" id="input-services">

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
                <h3 id="total-display" style="margin: 0; font-weight: bold; font-size: 40px;">0</h3>
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
                    <tbody id="cart-barang-body">
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box">
            <div class="box-header"><h3 class="box-title">Keranjang Servis</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-striped" id="table-servis">
                    <thead>
                        <tr>
                            <th>Nama Servis</th>
                            <th>Biaya</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cart-servis-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalSelesaiTransaksi" tabindex="-1" role="dialog" aria-labelledby="modalSelesaiLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formSelesaiTransaksi">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSelesaiLabel">
            <i class="fa fa-check"></i> Konfirmasi Selesai Transaksi
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="row">
            <!-- Kolom Kiri -->
            <div class="col-md-6">
              <div class="form-group">
                <label for="textNoFakturModal">No Faktur</label>
                <input type="hidden" id="textUserId" name="id_user" value="<?= $_SESSION['id_user']; ?>">
                <input type="text" id="textNoFakturModal" name="no_faktur" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label for="textKendaraan">Kendaraan</label>
                <input type="text" id="textKendaraan" name="kendaraan" class="form-control">
              </div>

              <div class="form-group">
                <label for="textNoPolisi">No Polisi</label>
                <input type="text" id="textNoPolisi" name="no_polisi" class="form-control">
              </div>

              <div class="form-group">
                <label for="pelanggan">Pelanggan</label>
                <select id="pelanggan" name="id_pelanggan" class="form-control" style="width:100%">
                  <option value="">-- Pilih Pelanggan --</option>
                  <?php
                  $qPelanggan = mysqli_query($conn, "SELECT id_pelanggan, nama_pelanggan FROM pelanggans ORDER BY nama_pelanggan ASC");
                  while($row = mysqli_fetch_assoc($qPelanggan)){
                      echo '<option value="'.$row['id_pelanggan'].'">'.$row['nama_pelanggan'].'</option>';
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label for="teknisi">Teknisi</label>
                <select id="teknisi" name="id_teknisi" class="form-control" style="width:100%">
                  <option value="">-- Pilih Teknisi --</option>
                  <?php
                  $qTeknisi = mysqli_query($conn, "SELECT id_teknisi, nama_teknisi FROM teknisis ORDER BY nama_teknisi ASC");
                  while($row = mysqli_fetch_assoc($qTeknisi)){
                      echo '<option value="'.$row['id_teknisi'].'">'.$row['nama_teknisi'].'</option>';
                  }
                  ?>
                </select>
              </div>
            </div>

            <!-- Kolom Kanan -->
            <div class="col-md-6">
              <div class="form-group">
                <label for="statusTransaksi">Status Transaksi</label>
                <select id="statusTransaksi" name="status" class="form-control" required>
                  <option value="">-- Pilih Status --</option>
                  <option value="selesai">Selesai</option>
                  <option value="pending">Pending</option>
                </select>
              </div>

              <div class="form-group">
                <label>Metode Bayar</label><br>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="metode_bayar" value="Tunai" id="bayarTunai">
                  <label class="form-check-label" for="bayarTunai">Tunai</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="metode_bayar" value="Non Tunai" id="bayarNonTunai">
                  <label class="form-check-label" for="bayarNonTunai">Non Tunai / Kredit</label>
                </div>
              </div>

              <div class="form-group">
                <label for="totalAwal">Total Awal</label>
                <input type="text" id="totalAwal" name="totalAwal" class="form-control" readonly value="0">
              </div>

              <div class="form-group">
                <label for="diskon">Diskon (%)</label>
                <input type="number" id="diskon" name="diskon" class="form-control" min="0" max="100" value="0">
              </div>

              <div class="form-group">
                <label for="totalBayar">Total Bayar (Setelah Diskon)</label>
                <input type="text" id="totalBayar" name="total_bayar" class="form-control" readonly>
              </div>

              <div class="form-group">
                <label for="uangBayar">Uang Dibayar</label>
                <input type="number" id="uangBayar" name="uangBayar" class="form-control">
              </div>

              <div class="form-group">
                <label for="kembalian">Kembalian</label>
                <input type="text" id="kembalian" name="kembalian" class="form-control" readonly>
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
          <thead style="width:100%">
            <tr>
              <th style="width:100%">No Faktur</th>
              <th style="width:100%">Kendaraan</th>
              <th style="width:100%">No Polisi</th>
              <th style="width:100%">Pelanggan</th>
              <th style="width:100%">Teknisi</th>
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

<script>


$(document).ready(function() {
    // aktifkan select2
    $('#sparepart-select').select2({
        placeholder: "Cari sparepart...",
        allowClear: true
    });

    $('#servis-select').select2({
        placeholder: "Cari jenis servis...",
        allowClear: true
    });

    
    $("#pelanggan").select2({ 
        placeholder: "Pilih Pelanggan", 
        width: "100%" ,
        allowClear: true
    });
    $("#teknisi").select2({ 
        placeholder: "Pilih Teknisi", 
        width: "100%",
        allowClear: true
     });

    $('#modalSelesaiTransaksi').on('show.bs.modal', function () {
        let noFaktur = $("#noFakturText").val();
        $("#textNoFakturModal").val(noFaktur);
        $.ajax({
            url: "pages/admin_bengkel/api_get_transaksi.php",
            type: "GET",
            data: { no_faktur: noFaktur },
            dataType: "json",
            success: function(res){
                if(res.status_code == 200){
                    let totalSparepart = res.data.detail_sparepart.reduce((acc, item) => acc + parseInt(item.subtotal), 0);
                    let totalServis = res.data.detail_servis.reduce((acc, item) => acc + parseInt(item.biaya_servis), 0);
                    let total = res.data.total;
                    let uang_bayar = 0;
                    let kembalian = 0;
                    let kendaraan = "";
                    let no_polisi = "";
                    let status = "pending";
                    let pelanggan = "";
                    let teknisi = "";
                    if (res.transaksi == null) {
                        uang_bayar = 0;
                        kembalian = 0;
                        kendaraan = '';
                        no_polisi = '';
                        status = 'pending';
                        pelanggan = '';
                        teknisi = '';

                    }else {
                        uang_bayar = res.data.transaksi.uang_bayar;
                        kembalian = res.data.transaksi.kembalian;
                        kendaraan = res.data.transaksi.kendaraan;
                        no_polisi = res.data.transaksi.no_polisi;
                        status = res.data.transaksi.status;
                        pelanggan = res.data.transaksi.pelanggan;
                        teknisi = res.data.transaksi.teknisi;
                    }
                    $("#totalAwal").val(total.toLocaleString('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:0, maximumFractionDigits:0}));
                    $("#uangBayar").val(uang_bayar.toLocaleString('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:0, maximumFractionDigits:0}));
                    $("#kembalian").val(kembalian.toLocaleString('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:0, maximumFractionDigits:0}));
                    $("#textKendaraan").val();
                    $("#textNoPolisi").val(no_polisi);
                    $("#statusTransaksi").val(status);
                    $("#pelanggan").val(pelanggan).trigger("change");
                    $("#teknisi").val(teknisi).trigger("change");
                } else {
                    $("#totalBayar").val("0");
                }
            }
        });
    });

    
    $('#btn-list-servis').on('click', function() {
        $('#modalPendingServis').modal('show');

        // Inisialisasi atau reload DataTable
        if ( $.fn.DataTable.isDataTable('#tablePendingServis') ) {
            $('#tablePendingServis').DataTable().ajax.reload();
        } else {
            $('#tablePendingServis').DataTable({
                "ajax": {
                    "url": "pages/admin_bengkel/api_get_list_pending_transaction.php", // sesuaikan path API
                    "dataSrc": "data"
                },
                "columns": [
                    { "data": "no_faktur" },
                    { "data": "kendaraan" },
                    { "data": "no_polisi" },
                    { "data": "pelanggan" },
                    { "data": "teknisi" },
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

    // Event pilih transaksi
    $('#tablePendingServis').on('click', '.btn-pilih', function() {
        var noFaktur = $(this).data('no_faktur');

        $("#noFakturText").val(noFaktur);

        
        reloadServisTable();
        reloadSparepartTable();
        sumTotal();
        $('#modalPendingServis').modal('hide');
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

      // Hitung total setelah diskon
      let totalSetelahDiskon = totalAwal - (totalAwal * diskon / 100);
      let totalPembayaran = Math.floor(totalSetelahDiskon); // atau gunakan toFixed(2) jika desimal dibutuhkan

      // Hitung kembalian
      let kembalian = uangBayar - totalPembayaran;
      if (kembalian < 0) kembalian = 0;

      // Tampilkan hasil
      $("#totalBayar").val(totalPembayaran);
      $("#kembalian").val(kembalian);
    }

    // Trigger saat input berubah
    $("#diskon, #uangBayar").on("input", hitungTransaksi);

    // Submit form selesai transaksi
    $("#formSelesaiTransaksi").on("submit", function(e){
        e.preventDefault();
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

    // shortcut keyboard
    $(document).on('keydown', function(e) {
        // F1 → Sparepart
        if (e.key === "F1") {
            e.preventDefault(); // cegah browser help
            $('#sparepart-select').select2('open');
        }

        // F7 → Servis
        if (e.key === "F7") {
            e.preventDefault();
            $('#servis-select').select2('open');
        }
    });
    
    $("#btn-add-sparepart").on("click", function() {
        let noFaktur = $("#noFakturText").val();
        let kode = $("#sparepart-select").val();
        let nama = $("#sparepart-select option:selected").data("nama_sparepart");
        let harga = $("#sparepart-select option:selected").data("harga");
        let satuan = $("#sparepart-select option:selected").data("satuan");
        let qty = $("#jumlah-barang-input").val();

        if(kode == "") {
            alert("Pilih sparepart terlebih dahulu!");
            return;
        }

        $.post("pages/admin_bengkel/api_transaksi_sparepart.php", {
            action: "create",
            no_faktur: noFaktur,
            kode_sparepart: kode,
            nama_sparepart: nama,
            satuan : satuan,
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

    $("#servis-select").on('change', function() {
        let biayaServis = $(this).find("option:selected").data("biaya");
        $("#biaya-servis-input").val(biayaServis);
    });
    // Tambah Servis
    $("#btn-add-servis").on("click", function() {
        let noFaktur = $("#noFakturText").val();
        let idServis = $("#servis-select").val();
        let namaServis = $("#servis-select option:selected").text();
        let biaya = $("#biaya-servis-input").val();

        if(idServis == "") {
            alert("Pilih jenis servis terlebih dahulu!");
            return;
        }

        $.post("pages/admin_bengkel/api_transaksi_servis.php", {
            action: "create",
            no_faktur: noFaktur,
            id_servis: idServis,
            nama_servis: namaServis,
            biaya: biaya
        }, function(res){
            reloadServisTable();
            sumTotal()
        }, "json");
    });

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
                    searchable: false,
                    render: function(row, type, data) {
                        return `
                            <button class="btn btn-xs btn-warning btn-edit-sparepart" data-id="${data.id_detail}">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-xs btn-danger btn-delete-sparepart" data-id="${data.id_detail}">
                                <i class="fa fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ]
        });
    }

    $(document).off('click', '.btn-edit-sparepart').on('click', '.btn-edit-sparepart', function() {
        let idDetail = $(this).data('id');
        let table = $("#table-sparepart").DataTable();
        let dataRow = table.rows().data().toArray().find(row => row.id_detail == idDetail);

        if (!dataRow) return;

        Swal.fire({
            title: 'Edit Qty',
            html: `
                <input type="text" id="swal-kode_sparepart" class="swal2-input" style="width:80%" placeholder="Kode Sparepart" value="${dataRow.kode_sparepart}" min="1" readonly>
                <input type="text" id="swal-nama_sparepart" class="swal2-input" style="width:80%" placeholder="Nama Sparepart" value="${dataRow.nama_sparepart}" min="1" readonly>
                <input type="number" id="swal-harga" class="swal2-input" style="width:80%" placeholder="Harga" value="${dataRow.harga}" min="1" readonly>
                <input type="number" id="swal-qty" class="swal2-input" style="width:80%" placeholder="Jumlah" value="${dataRow.qty}" min="1">
                
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            preConfirm: () => {
                return {
                    kode_sparepart: document.getElementById('swal-kode_sparepart').value,
                    nama_sparepart: document.getElementById('swal-nama_sparepart').value,
                    qty: parseInt(document.getElementById('swal-qty').value),
                    harga: parseInt(document.getElementById('swal-harga').value),
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let updatedQty = result.value.qty;
                let updateHarga = result.value.harga;
                let updateKode = result.value.kode_sparepart;
                let updatedSubtotal = updatedQty * updateHarga;

                $.post("pages/admin_bengkel/api_transaksi_sparepart.php",
                    { 
                        action: "update", 
                        id_detail: idDetail, 
                        qty: updatedQty, 
                        kode_sparepart: result.value.kode_sparepart, 
                        nama_sparepart: result.value.nama_sparepart, 
                        harga: result.value.harga, 
                        subtotal: updatedSubtotal 
                    }, 
                    function(res) {
                        if (res.status_code === 200) {
                            reloadSparepartTable();
                            sumTotal();
                            Swal.fire('Berhasil!', 'Sparepart berhasil diupdate.', 'success');
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }, 'json'
                );
            }
        });
    });

    // Event delegation untuk Delete Sparepart
    $(document).off('click', '.btn-delete-sparepart').on('click', '.btn-delete-sparepart', function() {
        let idDetail = $(this).data('id');

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Sparepart ini akan dihapus dari transaksi!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("pages/admin_bengkel/api_transaksi_sparepart.php", 
                    { action: "delete", id_detail: idDetail }, 
                    function(res) {
                        if(res.status_code === 200) {
                            reloadSparepartTable();
                            sumTotal();
                            Swal.fire('Terhapus!', 'Sparepart berhasil dihapus.', 'success');
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }, 'json'
                );
            }
        });
    });


    function reloadServisTable() {
        let noFaktur = $("#noFakturText").val();
        $.ajax({
            url: "pages/admin_bengkel/api_get_transaksi.php",
            type: "GET",
            data: { no_faktur: noFaktur },
            dataType: "json",
            success: function(res) {
                $("#table-servis").DataTable({
                    destroy: true,
                    data: res.data.detail_servis,
                    columns: [
                        { data: "nama_servis", title: "Jenis Servis" },
                        { 
                            data: "biaya", 
                            title: "Biaya",
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
                            searchable: false,
                            render: function(row, type, data) {
                                // tombol edit dan hapus, pakai id_detatil sebagai identifier
                                return `
                                    <button class="btn btn-xs btn-warning btn-edit-servis" data-id="${data.id_detail}"><i class="fa fa-edit"></i></button>
                                    <button class="btn btn-xs btn-danger btn-delete-servis" data-id="${data.id_detail}"><i class="fa fa-trash"></i></button>
                                `;
                            }
                        }
                    ]
                });
            }
        });
    }

    $(document).off('click', '.btn-edit-servis').on('click', '.btn-edit-servis', function() {
        let idDetail = $(this).data('id');

        // Ambil data servis dari DataTable
        let table = $("#table-servis").DataTable();
        let dataRow = table.rows().data().toArray().find(row => row.id_detail == idDetail);

        if(!dataRow) return;

        Swal.fire({
            title: 'Edit Servis',
            html: `
                <input type="text" id="swal-nama-servis" class="swal2-input" placeholder="Nama Servis" value="${dataRow.nama_servis}">
                <input type="number" id="swal-biaya-servis" class="swal2-input" placeholder="Biaya Servis" value="${dataRow.biaya}">
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            preConfirm: () => {
                return {
                    nama_servis: document.getElementById('swal-nama-servis').value,
                    biaya: parseInt(document.getElementById('swal-biaya-servis').value)
                }
            }
        }).then((result) => {
            if(result.isConfirmed) {
                let updatedData = result.value;

                // Kirim ke API untuk update
                $.post("pages/admin_bengkel/api_transaksi_servis.php", 
                    { 
                        action: "update", 
                        id_detail: idDetail, 
                        nama_servis: updatedData.nama_servis,
                        biaya: updatedData.biaya
                    }, 
                    function(res) {
                        if(res.status_code === 200) {
                            reloadServisTable();
                            sumTotal();
                            Swal.fire('Berhasil!', 'Servis berhasil diupdate.', 'success');
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }, 'json');
            }
        });
    });

    // Event delete servis dengan SweetAlert
    $(document).off('click', '.btn-delete-servis').on('click', '.btn-delete-servis', function() {
        let idDetail = $(this).data('id');

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data servis akan dihapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("pages/admin_bengkel/api_transaksi_servis.php", 
                    { action: "delete", id_detail: idDetail }, 
                    function(res) {
                        if(res.status_code === 200) {
                            reloadServisTable();
                            sumTotal();
                            Swal.fire('Terhapus!', 'Servis berhasil dihapus.', 'success');
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }, 'json');
            }
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

                // Format ke IDR tanpa desimal
                let totalIDR = new Intl.NumberFormat('id-ID', { 
                    style: 'currency', 
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(total);

                $("#total-display").html(totalIDR);
            },
            error: function() {
                $("#total-display").html("Rp 0");
            }
        });
    }



    reloadServisTable();
    reloadSparepartTable();
    sumTotal();
});
</script>
