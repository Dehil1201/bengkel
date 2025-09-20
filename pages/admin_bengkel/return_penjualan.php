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

    <table id="tableReturPenjualan" class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>No</th>
          <th>Tanggal</th>
          <th>No Retur</th>
          <th>No Faktur</th>
          <th>Nama Barang</th>
          <th>Qty</th>
          <th>Alasan</th>
          <th>Total Retur</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function () {
  const tablePenjualan = $('#tableReturPenjualan').DataTable({
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
      { data: 'tanggal' },
      { data: 'no_retur' },
      { data: 'no_faktur' },
      { data: 'nama_barang' },
      { data: 'qty' },
      { data: 'alasan' },
      { data: 'total_retur', render: $.fn.dataTable.render.number('.', ',', 0, 'Rp ') }
    ],
    order: [[1, 'desc']]
  });

  $('#filterFormPenjualan').submit(function (e) {
    e.preventDefault();
    tablePenjualan.ajax.reload();
  });
});
</script>
