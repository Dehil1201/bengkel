<?php 
function generateNoFaktur($conn) {
    $id_user = $_SESSION['id_user'];
    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $prefix = "RT." . date("Ymd") . "." . $id_user . "." . $id_bengkel;
    $today = date("Y-m-d");
    $q = mysqli_query($conn, "SELECT COUNT(*) as total 
                              FROM retur_penjualan 
                              WHERE DATE(tanggal_retur)='$today' 
                                AND id_user='$id_user' 
                                AND id_bengkel='$id_bengkel'");
    $row = mysqli_fetch_assoc($q);
    $no_urut = str_pad($row['total'] + 1, 4, "0", STR_PAD_LEFT);

    return $prefix . "." . $no_urut;
}
?>
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title">Daftar Retur Penjualan</h3>
  </div>
  <div class="box-body">

    <!-- Filter -->
    <form id="filterFormPenjualan" class="form-inline" style="margin-bottom: 15px;">
      <div class="form-group">
        <label for="tanggal_dari_penjualan">Dari:</label>
        <input type="date" id="tanggal_dari_penjualan" name="tanggal_dari" class="form-control">
      </div>
      <div class="form-group" style="margin-left:10px;">
        <label for="tanggal_sampai_penjualan">Sampai:</label>
        <input type="date" id="tanggal_sampai_penjualan" name="tanggal_sampai" class="form-control">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-left:10px;">
        <i class="fa fa-search"></i> Filter
      </button>
    </form>
    <br>
        
    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalReturPenjualan">
      <i class="fa fa-plus"></i> Tambah Retur Penjualan
    </button>
    <br><br><br>
    <table id="tableReturPenjualan" class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>No</th>
          <th>Tanggal</th>
          <th>No Retur</th>
          <th>No Faktur</th>
          <th>Alasan</th>
          <th>Total Retur</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Modal Retur Penjualan -->
<div class="modal fade" id="modalReturPenjualan" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formReturPenjualan">
        <div class="modal-header">
          <h4 class="modal-title">Retur Penjualan</h4>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="row">
            <!-- Kiri -->
            <div class="col-md-6">
              <div class="form-group">
                <label>No Faktur Retur</label>
                <input type="text" id="retur_no_faktur" name="no_retur" class="form-control" readonly value="<?= generateNoFaktur($conn) ?>">
              </div>
              <div class="form-group">
                <label>Faktur Penjualan</label>
                <select name="faktur_penjualan" id="fakturPenjualan" class="form-control select2">
                  <option value="">--Pilih Faktur--</option>
                </select>
              </div>
            </div>

            <!-- Kanan -->
            <div class="col-md-6 text-right">
              <div class="form-group">
                <label>Tanggal</label>
                <input type="date" id="returTanggal" name="tanggal" class="form-control" readonly value="<?= date("Y-m-d") ?>">
              </div>
            </div>
          </div>
          <!-- table Barang -->
          <div class="row">
            <div class="col-sm-12">
              <table class="table table-bordered table-striped table-hovered" id="tablePenjualan">
                <thead>
                  <tr>
                    <th>Kode</th>
                    <th>Nama Sparepart</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
              </table>
            </div>
          </div>

          <div class="row">
            <div class="col-sm-3">
              <div class="form-group">
                <label for="kodeSparepartInput">Kode</label>
                <input type="text" class="form-control" name="kode_sparepart_input" placeholder="Kode Sparepart" readonly id="kodeSparepartInput">
              </div>
            </div>
            <div class="col-sm-3">
              <div class="form-group">
                <label for="namaSparepartInput">Nama Sparepart</label>
                <input type="text" class="form-control" name="nama_sparepart_input" placeholder="Nama Sparepart" readonly id="namaSparepartInput">
              </div>
            </div>
            <div class="col-sm-3">
              <div class="form-group">
                <label for="qtyInput">Harga</label>
                <input type="number" class="form-control" id="hargaInput" name="harga_input" placeholder="Harga" readonly>
              </div>
            </div>
            <div class="col-sm-3">
              <div class="form-group">
                <label for="qtyInput">Qty</label>
                <input type="number" class="form-control" id="qtyInput" name="qty_input" placeholder="Qty" value="1">
              </div>
            </div>
            <div class="col-sm-3">
              <div class="form-group">
                <label for="btnTambahRetur"></label>
                <button class="btn btn-sm btn-primary" id="btnTambaRetur">
                  <i class="fa fa-plus"></i> Tambahkan
                </button>
              </div>
            </div>
          </div>

          <!-- Table Retur -->
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hovered" id="tabelRetur">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Nama</th>
                  <th>Qty</th>
                  <th>Harga</th>
                  <th>Subtotal</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot>
                <tr>
                  <th colspan="3" class="text-right">TOTAL</th>
                  <th id="total_retur">0</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>
          <label for="textAlasan">Alasan Retur</label>
          <textarea name="alasan_retur" id="textAlasan" cols="30" rows="10" class="form-control" placeholder="Masukkan alasan retur"></textarea>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-success">Simpan Retur</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
$(document).ready(function () {
    // === Tabel Retur Penjualan (list) ===
    const tableReturPenjualan = $('#tableReturPenjualan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'pages/admin_bengkel/get_retur_penjualan.php',
            type: 'POST',
            data: function (d) {
                d.tanggal_dari = $('#tanggal_dari_penjualan').val();
                d.tanggal_sampai = $('#tanggal_sampai_penjualan').val();
            }
        },
        columns: [
            { data: 'no', orderable: false, searchable: false },
            { data: 'tanggal_retur' },
            { data: 'no_retur' },
            { data: 'no_faktur' },
            { data: 'alasan' },
            { data: 'total_retur', render: $.fn.dataTable.render.number('.', ',', 0, 'Rp ') }
        ],
        order: [[1, 'desc']]
    });

    // filter berdasarkan tanggal
    $('#filterFormPenjualan').submit(function (e) {
        e.preventDefault();
        tableReturPenjualan.ajax.reload();
    });

    // === Tabel detail penjualan (pilih sparepart dari faktur) ===
    let tablePenjualan = $('#tablePenjualan').DataTable({
        searching: false,
        paging: false,
        info: false,
        columns: [
            { data: 'kode_sparepart' },
            { data: 'nama_sparepart' },
            { data: 'qty' },
            { data: 'harga' },
            { data: 'subtotal' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return `<button class="btn btn-primary btn-sm pilih-sparepart">Pilih</button>`;
                }
            }
        ]
    });

    // === Saat modal dibuka ===
    $('#modalReturPenjualan').on('shown.bs.modal', function () {
        initSelectFaktur(tablePenjualan); // pasang select2 dan isi tablePenjualan
        reloadTableRetur($("#retur_no_faktur").val()); // load detail retur

        // aksi pilih sparepart
        $('#tablePenjualan tbody').off('click', '.pilih-sparepart').on('click', '.pilih-sparepart', function (e) {
            e.preventDefault();
            var data = tablePenjualan.row($(this).parents('tr')).data();
            if (data) {
                $('#kodeSparepartInput').val(data.kode_sparepart || "");
                $('#namaSparepartInput').val(data.nama_sparepart || "");
                $('#hargaInput').val(data.harga || "");
            }
        });
    });

    // === Tambahkan detail retur ===
    $('#btnTambaRetur').on('click', function (e) {
        e.preventDefault();

        let kode = $('#kodeSparepartInput').val();
        let nama = $('#namaSparepartInput').val();
        let qty = $('#qtyInput').val();
        let noFakturRetur = $('#retur_no_faktur').val();
        let harga = $('#hargaInput').val();

        if (!kode || !nama || qty <= 0) {
            alert("Pilih sparepart dan isi qty dengan benar.");
            return;
        }

        $.ajax({
            url: 'pages/admin_bengkel/api_tambah_detail_retur.php',
            type: 'POST',
            dataType: 'json',
            data: {
                no_retur: noFakturRetur,
                kode_sparepart: kode,
                nama_sparepart: nama,
                qty: qty,
                harga: harga
            },
            success: function (res) {
                if (res.status_code === 200) {
                    $('#qtyInput').val(1); // reset qty
                    reloadTableRetur(noFakturRetur); // refresh table retur
                } else {
                    alert(res.message || "Gagal menambahkan detail retur");
                }
            },
            error: function () {
                alert("Terjadi kesalahan koneksi ke server");
            }
        });
    });

    $('#tabelRetur').on('click', '.hapus-retur', function () {
      let id_detail = $(this).data('id');

      Swal.fire({
        title: 'Hapus data?',
        text: "Data ini akan dihapus dari retur",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post('pages/admin_bengkel/api_hapus_detail_retur_penjualan.php', { id_detail: id_detail }, function (res) {
            if (res.status_code === 200) {
              Swal.fire('Berhasil', res.message, 'success');
              $('#tabelRetur').DataTable().ajax.reload();
              $('#total_retur').text(res.data.total_retur);
            } else {
              Swal.fire('Gagal', res.message, 'error');
            }
          }, 'json');
        }
      });
    });


    $('#formReturPenjualan').on('submit', function (e) {
          e.preventDefault();

          let no_retur      = $('#retur_no_faktur').val();   // hidden input no retur
          let no_faktur     = $('#fakturPenjualan').val();   // select2 faktur
          let tanggal_retur = $('#returTanggal').val();      // input date
          let alasan        = $('#textAlasan').val();        // textarea alasan
          let total_retur   = $('#total_retur').text().replace(/[^0-9]/g, ""); // ambil angka saja

          if (!no_retur || !no_faktur) {
              Swal.fire('Peringatan', 'No retur dan No faktur wajib diisi!', 'warning');
              return;
          }

          $.ajax({
              url: 'pages/admin_bengkel/api_simpan_retur.php',
              type: 'POST',
              dataType: 'json',
              data: {
                  no_retur: no_retur,
                  no_faktur: no_faktur,
                  tanggal_retur: tanggal_retur,
                  alasan: alasan,
                  total_retur: total_retur
              },
              success: function (res) {
                  if (res.status_code === 200) {
                      Swal.fire({
                          icon: 'success',
                          title: 'Berhasil',
                          text: res.message
                      }).then(() => {
                          $('#modalReturPenjualan').modal('hide'); // tutup modal


                          // reload table retur utama
                          $('#tableReturPenjualan').DataTable().ajax.reload();

                          // ==== Alternatif Opsi 2: Refresh halaman ====
                          location.reload();
                      });
                  } else {
                      Swal.fire('Gagal', res.message || 'Terjadi kesalahan saat simpan retur', 'error');
                  }
              },
              error: function () {
                  Swal.fire('Error', 'Tidak dapat terhubung ke server', 'error');
              }
          });
      });

});

// === Function inisialisasi Select2 Faktur ===
function initSelectFaktur(tablePenjualan) {
    $('#fakturPenjualan').select2({
        placeholder: "Pilih Transaksi",
        ajax: {
            url: "pages/admin_bengkel/api_get_transaksi.php",
            dataType: 'json',
            delay: 250,
            data: function () {
                return { jenis: "penjualan" };
            },
            processResults: function (data) {
                return {
                    results: data.data.map(function (item) {
                        return {
                            id: item.no_faktur,
                            text: item.no_faktur + " | " + item.tanggal + " | " + item.total
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });

    // ketika faktur dipilih
    $('#fakturPenjualan').off('select2:select').on('select2:select', function (e) {
        var noFaktur = e.params.data.id;
        $.getJSON("pages/admin_bengkel/api_get_transaksi.php?no_faktur=" + noFaktur, function (res) {
            if (res.status_code == 200 && res.data) {
                let rows = [];
                if (Array.isArray(res.data.detail_sparepart)) {
                    res.data.detail_sparepart.forEach(function (item) {
                        rows.push({
                            kode_sparepart: item.kode_sparepart,
                            nama_sparepart: item.nama_sparepart,
                            qty: item.qty,
                            harga: item.harga,
                            subtotal: item.subtotal
                        });
                    });
                }
                tablePenjualan.clear().rows.add(rows).draw();
            } else {
                tablePenjualan.clear().draw();
                alert("Data transaksi tidak ditemukan");
            }
        });
    });
}

// === Function reload tabel retur ===
function reloadTableRetur(noFakturRetur) {
    if ($.fn.DataTable.isDataTable('#tabelRetur')) {
        $('#tabelRetur').DataTable().ajax.url(
            'pages/admin_bengkel/api_get_detail_penjualan_retur.php?no_retur=' + noFakturRetur
        ).load();
    } else {
      $('#tabelRetur').DataTable({
          ajax: {
            url: 'pages/admin_bengkel/api_get_detail_penjualan_retur.php?no_retur=' + noFakturRetur,
            dataSrc: function (json) {
              console.log(json); // <-- sekarang pasti tampil di console
              if (json.total_retur !== undefined) {
                $('#tabelRetur tfoot #total_retur').text(json.total_retur);
              }
              return json.data; // isi tbody
            }
          },
          searching: false,
          paging: false,
          info: false,
          destroy: true,
          columns: [
            { data: 'kode_sparepart' },
            { data: 'nama_sparepart' },
            { data: 'qty' },
            { data: 'harga' },
            { data: 'subtotal' },
            {
              data: 'id_detail',
              render: function (data) {
                return `
                  <button type="button" class="btn btn-danger btn-sm hapus-retur" data-id="${data}">
                    <i class="fa fa-trash"></i>
                  </button>
                `;
              }
            }
          ]
        });

    }
}
</script>

