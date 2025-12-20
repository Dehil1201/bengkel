<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">

            <div class="box-header with-border">
                <h3 class="box-title">Riwayat Stok</h3>
            </div>

            <!-- ===== FILTER ===== -->
            <div class="box-body">
                <div class="row" style="margin-bottom:15px;">
                    
                    <div class="col-md-3">
                        <label>Dari Tanggal</label>
                        <input type="date" id="minDate" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>Sampai Tanggal</label>
                        <input type="date" id="maxDate" class="form-control">
                    </div>

                    <!-- ✅ FILTER JENIS -->
                    <div class="col-md-3 d-none">
                        <label>Jenis Transaksi</label>
                        <select id="jenis" class="form-control">
                            <option value="penjualan">Penjualan (Stok Keluar)</option>
                        </select>
                    </div>

                    <div class="col-md-3" style="margin-top:25px;">
                        <button class="btn btn-primary" id="filterTanggal">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <button class="btn btn-default" id="resetTanggal">
                            <i class="fa fa-refresh"></i> Reset
                        </button>
                    </div>

                </div>

                <!-- ===== TABLE ===== -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTableStokMasuk">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Nama Spare Part</th>
                                <th>Jumlah</th>
                                <th>No Faktur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DATA VIA AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // === SET DEFAULT TANGGAL = HARI INI ===
    let today = new Date().toISOString().split('T')[0];
    $('#minDate').val(today);
    $('#maxDate').val(today);

    var table = $("#dataTableStokMasuk").DataTable({
        processing: true,
        serverSide: true,
        paging: true,
        searching: true,
        ordering: true,
        autoWidth: false,
        dom: '<"row"<"col-md-6"lB><"col-md-6"f>>rtip',   // ⬅️ WAJIB
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
            url: "pages/admin_bengkel/api_riwayat_stok.php",
            type: "GET",
            data: function(d) {
                d.minDate = $('#minDate').val();
                d.maxDate = $('#maxDate').val();
                d.jenis   = $('#jenis').val(); 
            }
        }
    });

    // ===== AKSI FILTER =====
    $('#filterTanggal').click(function() {
        table.ajax.reload();
    });

    $('#resetTanggal').click(function() {
        let today = new Date().toISOString().split('T')[0];
        $('#minDate').val(today);
        $('#maxDate').val(today);
        $('#jenis').val('pembelian');
        table.ajax.reload();
    });

    $('#jenis').change(function(){
        table.ajax.reload();
    });

});
</script>
