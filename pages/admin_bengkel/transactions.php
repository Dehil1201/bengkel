

    <!-- Filter -->
    <div class="box shadow-sm mb-4 border-0">
        <div class="box-header">
            <i class="bi bi-funnel-fill me-2"></i> Filter Laporan
        </div>

        <div class="box-body">
            <div class="row g-3">

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal Mulai</label>
                    <input type="date" id="tgl_mulai" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal Selesai</label>
                    <input type="date" id="tgl_selesai" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Jenis Transaksi</label>
                    <select id="jenis_transaksi" class="form-select form-control">
                        <option value="">Semua</option>
                        <option value="penjualan">Penjualan</option>
                        <option value="pembelian">Pembelian</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button id="btnFilter" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div class="row mb-4" id="summaryBox">

        <div class="col-md-4">
            <div class="callout callout-success">
                <h6 class="fw-bold mb-1"><i class="bi bi-cart-check me-2"></i>Total Penjualan</h6>
                <h4 class="fw-bold" id="totalPenjualan">Rp 0</h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="callout callout-warning">
                <h6 class="fw-bold mb-1"><i class="bi bi-bag me-2"></i>Total Pembelian</h6>
                <h4 class="fw-bold" id="totalPembelian">Rp 0</h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="callout callout-info">
                <h6 class="fw-bold mb-1"><i class="bi bi-cash-coin me-2"></i>Sisa Saldo</h6>
                <h4 class="fw-bold" id="sisaSaldo">Rp 0</h4>
            </div>
        </div>

    </div>

    <!-- Tabel -->
    <div class="box shadow-sm border-0">
        <div class="box-header bg-dark text-white fw-semibold">
            <i class="bi bi-table me-2"></i> Data Laporan Transaksi
        </div>

        <div class="box-body">
            <table id="tabelLaporan" class="table table-striped table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Faktur</th>
                        <th>Customer / Supplier</th>
                        <th>Jenis</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

<script>
    $(document).ready(function() {
        let today = new Date().toISOString().split('T')[0];
        $('#tgl_mulai').val(today);
        $('#tgl_selesai').val(today);

        const table = $('#tabelLaporan').DataTable({
            processing: true,
            serverSide: true,
            scrollY: true, 
            ajax: {
                url: 'pages/admin_bengkel/api_laporan_transaksi.php',
                type: 'POST',
                data: function(d) {
                    d.tgl_mulai = $('#tgl_mulai').val();
                    d.tgl_selesai = $('#tgl_selesai').val();
                    d.jenis_transaksi = $('#jenis_transaksi').val();
                }
            },
            columnDefs: [
                {
                    targets: -1,   // kolom terakhir
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {

                        const id = row[2]; 

                        return `
                            <button class="btn btn-default btn-sm btnHapus" data-id="${id}">
                                <i class="fa fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ]
        });


        loadSummary();

        function loadSummary() {
            $.post("pages/admin_bengkel/api_laporan_transaksi_summary.php", {
                tgl_mulai: $('#tgl_mulai').val(),
                tgl_selesai: $('#tgl_selesai').val()
            }, function(res) {

                $('#totalPenjualan').text(res.total_penjualan_formatted);
                $('#totalPembelian').text(res.total_pembelian_formatted);
                $('#sisaSaldo').text(res.sisa_saldo_formatted);

            }, 'json');
        }

        $('#btnFilter').on('click', function() {
            loadSummary();
            table.ajax.reload();
        });

        $(document).on("click", ".btnHapus", function() {
            let id = $(this).data("id");

            Swal.fire({
                title: "Hapus Transaksi?",
                text: "Transaksi yang dihapus tidak dapat dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {

                    $.post("pages/admin_bengkel/api_hapus_transaksi.php", { no_faktur: id }, function(res) {

                        if (res.success) {
                            Swal.fire("Berhasil!", res.message, "success");
                            table.ajax.reload();
                            loadSummary();
                        } else {
                            Swal.fire("Gagal!", res.message, "error");
                        }

                    }, "json");

                }
            });
        });

    });
</script>
