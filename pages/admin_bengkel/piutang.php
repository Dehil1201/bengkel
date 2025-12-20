<?php
$id_pelanggan = '';
$list_pelanggan = mysqli_query($conn, "SELECT id_pelanggan, nama_pelanggan FROM pelanggans WHERE bengkel_id = '$id_bengkel'");
?>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title">Daftar Piutang</h3>
  </div>

  <div class="box-body">
    <!-- Filter -->
    <form id="filterForm" class="form-inline" style="margin-bottom: 20px;">
      <div class="form-group">
        <label>Dari:</label>
        <input type="date" class="form-control" name="tanggal_dari">
      </div>
      <div class="form-group" style="margin-left:10px;">
        <label>Sampai:</label>
        <input type="date" class="form-control" name="tanggal_sampai">
      </div>
      <div class="form-group" style="margin-left:10px;">
        <label>Status:</label>
        <select name="status" class="form-control">
          <option value="">Semua</option>
          <option value="belum lunas">Belum Lunas</option>
          <option value="lunas">Lunas</option>
        </select>
      </div>
        <div class="form-group">
            <label>Pelanggan</label>
            <select id="selectPelanggan" name="id_pelanggan" class="form-control">
                <option value="">-- Semua --</option>
                <?php while ($p = mysqli_fetch_assoc($list_pelanggan)) : ?>
                    <option value="<?= $p['id_pelanggan']; ?>" <?= $id_pelanggan == $p['id_pelanggan'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nama_pelanggan']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
      <button type="submit" class="btn btn-primary" style="margin-left:10px;">Terapkan</button>
    </form>

    <!-- Tabel -->
    <table id="tabel-piutang" class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>No</th>
          <th>No Faktur</th>
          <th>Nama Pelanggan</th>
          <th>Tanggal</th>
          <th>Total</th>
          <th>Jumlah Dibayar</th>
          <th>Sisa Piutang</th>
          <th>Status</th>
          <th>Tempo</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <div class="box-footer">
    <strong>Total Piutang Belum Lunas: <span id="totalPiutang" class="text-red">Rp 0</span></strong>
  </div>
</div>

<!-- Modal Cicilan -->
<div class="modal fade" id="modalCicilan" tabindex="-1" role="dialog">
  <div class="modal-dialog">
    <form id="formCicilan">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Pembayaran Cicilan</h4>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_piutang" id="id_piutang">
          <div class="form-group">
            <label>Tanggal Bayar</label>
            <input type="date" name="tanggal_bayar" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Jumlah Bayar</label>
            <input type="text" name="jumlah_bayar_raw" class="form-control" required>
            <input type="hidden" name="jumlah_bayar" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Metode Bayar</label>
            <select name="metode_bayar" class="form-control">
              <option value="Tunai">Tunai</option>
              <option value="Transfer">Transfer</option>
              <option value="QRIS">QRIS</option>
            </select>
          </div>
          <div class="form-group">
            <label>Keterangan</label>
            <textarea name="keterangan" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Bayar</button>
          <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Script -->
<script>
  $(document).ready(function () {
    // Fungsi format ribuan
    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Fungsi hapus format → jadi integer
    function toInt(str) {
        return parseInt(str.replace(/\./g, "")) || 0;
    }
    $('input[name="jumlah_bayar_raw"]').on('input', function () {
        let value = $(this).val();

        // Konversi ke integer
        let intValue = parseInt(value || 0);

        // Update hidden
        $('input[name="jumlah_bayar"]').val(intValue);

        // Tampilkan format pada placeholder
        $(this).attr('placeholder', intValue.toLocaleString('id-ID'));
    });

    $('input[name="jumlah_bayar_raw"]').on('input', function () {
        let raw = $(this).val();

        let intValue = toInt(raw);
        $('input[name="jumlah_bayar"]').val(intValue);

        // Set kembali ke format ribuan
        $(this).val(formatRibuan(intValue));
    });


    let table = $('#tabel-piutang').DataTable({
      scrollY: true,
      dom: 'Bfrtip',   // ⬅️ WAJIB
            buttons: [
                {
                    extend: 'excelHtml5',
                    title: 'Laporan_Penjualan',
                    exportOptions: {
                        columns: ':not(:last-child)' // kecuali kolom Detail
                    }
                },
                {
                    extend: 'csvHtml5',
                    title: 'Laporan_Penjualan',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    title: 'Laporan Penjualan',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    },
                    customize: function (doc) {
                        doc.defaultStyle.fontSize = 9;
                        doc.styles.tableHeader.fontSize = 10;
                    }
                }
            ],
      ajax: {
        url: 'pages/admin_bengkel/api_get_piutang.php',
        data: function (d) {
          const formData = $('#filterForm').serializeArray();
          formData.forEach(param => d[param.name] = param.value);
        },
        dataSrc: function (json) {
          // Hitung total piutang belum lunas
          let total = 0;
          json.forEach(row => {
            if (row.status === 'belum lunas') {
              total += (parseFloat(row.jumlah) - parseFloat(row.dibayar));
            }
          });
          $('#totalPiutang').text(`Rp ${total.toLocaleString('id-ID', { minimumFractionDigits: 0 })}`);
          return json;
        }
      },
      columns: [
        { data: null },
        { data: 'no_faktur' },
        { data: 'nama_pelanggan' },
        { data: 'tanggal_piutang' },
        { data: 'jumlah', render: $.fn.dataTable.render.number('.', '.', 0, 'Rp ') },
        { data: 'dibayar', render: $.fn.dataTable.render.number('.', '.', 0, 'Rp ') },
        {
          data: null,
          render: function (data) {
            const sisa = parseFloat(data.jumlah) - parseFloat(data.dibayar);
            return $.fn.dataTable.render.number('.', '.', 0, 'Rp ').display(sisa);
          }
        },
        { data: null, 
            render: function (data) {
            if (data.status === 'lunas') {
              return `<span class="label label-success">${data.status}</span>`;
            }else {
              return `<span class="label label-danger">${data.status}</span>`;
            }
        } },
        { data: 'tanggal_pelunasan' },
        {
          data: null,
          render: function (data) {
            if (data.status === 'lunas') {
              return `<button class="btn btn-disable btn-sm btn-bayar" data-id="${data.id_piutang}" data-sisa="${data.jumlah - data.dibayar}" disabled>
                      <i class="fa fa-money"></i> Bayar
                    </button>`;
            }else {
                return `<button class="btn btn-success btn-sm btn-bayar" data-id="${data.id_piutang}" data-sisa="${data.jumlah - data.dibayar}">
                      <i class="fa fa-money"></i> Bayar
                    </button>`;
                }
            

            }
        }
      ],
      columnDefs: [{
        targets: 0,
        render: (data, type, row, meta) => meta.row + 1
      }]
    });

    // Filter submit
    $('#filterForm').submit(function (e) {
      e.preventDefault();
      table.ajax.reload();
    });

    // Buka modal bayar
    $('#tabel-piutang').on('click', '.btn-bayar', function () {
      $('#id_piutang').val($(this).data('id'));
      let sisa = $(this).data('sisa') || 0;
      $('input[name="jumlah_bayar_raw"]').val(formatRibuan(sisa));
      $('input[name="jumlah_bayar"]').val(sisa);
      $('#modalCicilan').modal('show');
    });

    // Simpan cicilan
    $('#formCicilan').submit(function (e) {
        e.preventDefault();

        $.post('pages/admin_bengkel/bayar_cicilan_piutang.php', $(this).serialize(), function (res) {
            if (res.status_code === 200) {
                $('#modalCicilan').modal('hide');
                table.ajax.reload();
                Swal.fire({
                title: 'Berhasil!',
                text: 'Cicilan berhasil disimpan.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Cetak Invoice',
                cancelButtonText: 'Tutup',
                }).then((result) => {
                if (result.isConfirmed) {
                    // Pakai id_cicilan dari response
                    const idCicilan = res.id_cicilan;
                    window.open(`pages/admin_bengkel/print_bayar_cicilan.php?id_cicilan=${idCicilan}&auto_print=1`, '_blank');
                }
                });
            } else {
                Swal.fire({
                title: 'Gagal!',
                text: res.message,
                icon: 'error'
                });
            }
            }).fail(function () {
                Swal.fire({
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat menyimpan cicilan.',
                    icon: 'error'
                });
            });
    
    })

  });
</script>
