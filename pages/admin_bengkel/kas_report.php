<?php
// Filter
$tgl_dari = $_GET['tgl_dari'] ?? date('Y-m-d');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-d');
$id_supplier = $_GET['id_supplier'] ?? '';
$id_user = $_GET['id_user'] ?? '';

function generateNoFaktur($conn, $tanggal_transaksi = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $tanggal_transaksi ??= date("Y-m-d");

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
    $user = mysqli_fetch_assoc($resUser);

    $id_bengkel = $user['bengkel_id'] ?? null;
    if (!$id_bengkel) {
        throw new Exception("Bengkel user tidak ditemukan");
    }

    $ymd = date("Ymd", strtotime($tanggal_transaksi));

    mysqli_begin_transaction($conn);

    try {
        // 🔒 LOCK BARIS FAKTUR
        $stmt = mysqli_prepare($conn, "
            SELECT MAX(
                CAST(SUBSTRING_INDEX(no_faktur, '.', -1) AS UNSIGNED)
            ) AS max_urut
            FROM transaksi
            WHERE id_bengkel = ?
              AND no_faktur LIKE CONCAT('PB.', ?, '.', ?, '.', ?, '.%')
            FOR UPDATE
        ");

        mysqli_stmt_bind_param(
            $stmt,
            "isii",
            $id_bengkel,
            $ymd,
            $id_user,
            $id_bengkel
        );

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);

        $next = ((int)($row['max_urut'] ?? 0)) + 1;
        $urut = str_pad($next, 4, '0', STR_PAD_LEFT);

        mysqli_commit($conn);

        return "KS.$ymd.$id_user.$id_bengkel.$urut";

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}





// Dropdown data
$list_supplier = mysqli_query($conn, "SELECT id_supplier, nama_supplier FROM suppliers WHERE bengkel_id = '$id_bengkel'");
$list_user = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users WHERE bengkel_id = '$id_bengkel'");
?>

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
                    <select id="jenis" class="form-select form-control">
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
            <i class="bi bi-table me-2"></i> Data Laporan KAS
        </div>

        <div class="box-body">
            
            <button class="btn btn-success" data-toggle="modal" data-target="#modalTransaksiKas">
                <i class="fa fa-plus"></i> Tambah Kas
            </button>
            <br>    
            <table id="tabelLaporan" class="table table-striped table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Faktur</th>
                        <th>Customer / Supplier</th>
                        <th>Jenis</th>
                        <th>HPP</th>
                        <th>Jumlah</th>
                        <th>Laba</th>
                        <th>Status</th>
                        <th>Jenis Kas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalTransaksiKas" tabindex="-1" role="dialog" aria-labelledby="modalTransaksiKas" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <form id="formTransaksiKas" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTransaksiKas">Transaksi Kas</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <div class="row">
            <!-- LEFT SIDE -->
            <div class="col-md-12">
              <div class="row">
                <div class="col-md-12">
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
                        <label>Tipe</label>
                        <select id='selectTipe' name="tipe" class="form-control" style="width:100%">
                            <option value="">-- Pilih Tipe --</option>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="text" name="nominal" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kas</label>
                        <select id='selectJenisKas' name="kas_id" class="form-control" style="width:100%">
                            <option value="">-- Pilih Jenis --</option>
                            <?php
                                $qJenisKas = "select * from kas";
                                $jenisKas = mysqli_query($conn,$qJenisKas);
                                while ($result = mysqli_fetch_array($jenisKas)) {
                                    ?>
                                        <option value="<?= $result['kas_id'] ?>"><?= $result['nama_kas']; ?></option>
                                    <?php
                                }

                            ?>
                        
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="keterangan" width="100%" style="width:100%"></textarea>
                    </div>
                </div>
              </div>
              
            </div>
          </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
          <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Tutup</button>
        </div>
      </form>
    </div>
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
            dom: '<"row"<"col-md-6"lB><"col-md-6"f>>rtip',   // ⬅️ WAJIB
            buttons: [
                {
                    extend: 'excelHtml5',
                    title: 'Laporan KAS',
                    exportOptions: {
                        columns: ':not(:last-child)' // kecuali kolom Detail
                    }
                },
                {
                    extend: 'csvHtml5',
                    title: 'Laporan KAS',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    title: 'Laporan KAS',
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
                url: 'pages/admin_bengkel/api_laporan_transaksi.php',
                type: 'POST',
                data: function(d) {
                    d.tgl_mulai = $('#tgl_mulai').val();
                    d.tgl_selesai = $('#tgl_selesai').val();
                    d.jenis = $('#jenis').val();
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

        $("#formTransaksiKas").submit(function(e){

        e.preventDefault();

        $.ajax({

            url:"pages/admin_bengkel/api_insert_kas.php",
            type:"POST",
            data:$(this).serialize(),
            dataType:"json",
            beforeSend:function(){

                Swal.fire({
                    title:"Menyimpan...",
                    allowOutsideClick:false,
                    didOpen:()=>Swal.showLoading()
                });

            },
            success:function(res){

                if(res.success){

                    Swal.fire({
                        icon:"success",
                        title:"Berhasil",
                        text:res.message
                    });

                    $("#modalTransaksiKas").modal("hide");

                    $("#formTransaksiKas")[0].reset();

                    $("#noFakturText").val(res.no_faktur);

                    table.ajax.reload();

                    loadSummary();

                }else{

                    Swal.fire({
                        icon:"error",
                        title:"Gagal",
                        text:res.message
                    });

                }

            }

        });

        });

    });
    
</script>
