<div class="row">
    <div class="col-sm-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h4><i class="fa fa-database"></i> Laporan Barang Keluar</h4>
            </div>
            <div class="box-body">
                <h4>Filter Data</h4>
                <form action="">
                    <div class="row">
                        <div class="col-sm-3">
                            <label for="startDate">Dari Tanggal</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-sm-3">
                            <label for="endDate">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                        <div class="col-sm-3">
                            <label for="merk">Merk</label>
                            <select name="" id="merk" class="form-control"></select>
                        </div>

                        <div class="col-sm-3 d-flex flex-column justify-content-end">
                            <button type="button" class="btn btn-primary w-100">
                                <i class="fa fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="box box-primary">
            <div class="box-body">
                <table class="table table-striped table-hovered" id="tableBarangKeluar">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Sparepart</th>
                            <th>Terjual</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

// load merk
function loadMerk() {
    $.ajax({
        url: 'pages/admin_bengkel/api_manajemen.php',
        method: 'POST',
        data: { aksi: 'get_all_merk' },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                $('#merk').html('<option value="">-- Semua Merk --</option>');
                res.data.forEach(item => {
                    $('#merk').append(`<option value="${item.id}">${item.nama}</option>`);
                });
            }
        }
    });
}

loadMerk();

const table = $('#tableBarangKeluar').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'pages/admin_bengkel/get_barang_keluar.php',
            type: 'POST',
            data: function(d) {
                d.startDate = $('#startDate').val();
                d.endDate = $('#endDate').val();
                d.merk = $('#merk').val();
            }
        },
        columns: [
            { data: 'no' },
            { data: 'kode_sparepart' },
            { data: 'nama_sparepart' },
            {
                data: 'terjual',
                className: 'text-right',
                render: function(data, type, row) {
                    if (type === 'display' || type === 'filter') {
                        return new Intl.NumberFormat('id-ID').format(data || 0);
                    }
                    return data;
                }
            }
        ]
    });

    // tombol filter
    $('.btn-primary').on('click', function() {
        table.ajax.reload();
    });

});
</script>