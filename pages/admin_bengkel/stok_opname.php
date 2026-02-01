<?php
// Pastikan sesi sudah dimulai dan file koneksi disertakan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk membersihkan dan mengamankan input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// ==========================================================
// Pengecekan Akses Berdasarkan Role dan Bengkel ID
// ==========================================================
$user_role = get_user_role();
$allowed_roles = ['owner_bengkel', 'admin_bengkel'];

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
} else if ($user_role === 'admin_bengkel') {
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

// ==========================================================
// LOGIKA PEMROSESAN STOK OPNAME
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=stok_opname';

    if ($action == 'simpan_opname') {
        $data_opname = json_decode($_POST['data_opname'], true);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        // Pastikan bengkel ID valid
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $tanggal_opname = date('Y-m-d H:i:s');
        $success_count = 0;
        $error_count = 0;

        foreach ($data_opname as $item) {
            $spare_part_id = sanitize_input($item['spare_part_id']);
            $stok_fisik = (int)sanitize_input($item['stok_fisik']);
            $keterangan = sanitize_input($item['keterangan']);

            // Dapatkan stok dari sistem
            $query_get_stok = "SELECT stok_pcs, bengkel_id FROM sparepart WHERE id_sparepart = '$spare_part_id'";
            $result_get_stok = mysqli_query($conn, $query_get_stok);
            $row_stok = mysqli_fetch_assoc($result_get_stok);

            if ($row_stok && in_array($row_stok['bengkel_id'], $accessible_bengkel_ids)) {
                $stok_sistem = (int)$row_stok['stok_pcs'];
                $selisih = $stok_fisik - $stok_sistem;

                // Transaksi: Mulai
                mysqli_begin_transaction($conn);
                try {
                    // 1. Simpan ke tabel stok_opname
                    $query_insert_opname = "INSERT INTO stok_opnames (tanggal_opname, spare_part_id, stok_sistem, stok_fisik, selisih, keterangan, bengkel_id) VALUES ('$tanggal_opname', '$spare_part_id', '$stok_sistem', '$stok_fisik', '$selisih', '$keterangan', '$bengkel_id')";
                    
                    if (mysqli_query($conn, $query_insert_opname)) {
                        // 2. Perbarui stok di tabel sparepart
                        $query_update_stok = "UPDATE sparepart SET stok_pcs = '$stok_fisik' WHERE id_sparepart = '$spare_part_id'";
                        if (mysqli_query($conn, $query_update_stok)) {
                            mysqli_commit($conn);
                            $success_count++;
                        } else {
                            throw new Exception("Gagal memperbarui stok sparepart.");
                        }
                    } else {
                        throw new Exception("Gagal menyimpan data opname.");
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_count++;
                    // Anda bisa menambahkan log di sini
                }
            } else {
                $error_count++;
            }
        }
        
        $message = "Stok Opname Selesai. Berhasil: $success_count, Gagal: $error_count.";
        $status = ($error_count > 0) ? 'warning' : 'success';
        header("Location: $current_page&status=$status&message=" . urlencode($message));
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Stok Opname Spare Part</h3>
                <div class="box-tools pull-right">
                    <button id="printPdfOpname" type="button" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print</button>
                    <button id="btnSelesaiOpname" type="button" class="btn btn-success btn-sm"><i class="fa fa-check"></i> Selesaikan Opname</button>
                </div>
            </div>
            <div class="box-body">
                <p>Pilih bengkel yang akan di-opname:</p>
                <div class="form-group">
                    <select class="form-control" id="selectBengkel">
                        <?php
                        $query_bengkel = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE id_bengkel IN ($bengkel_ids_string)");
                        if (mysqli_num_rows($query_bengkel) > 0) {
                            while ($row_bengkel = mysqli_fetch_assoc($query_bengkel)) {
                                echo "<option value='{$row_bengkel['id_bengkel']}'>{$row_bengkel['nama_bengkel']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTableStokOpname">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Spare Part</th>
                                <th>Stok Sistem</th>
                                <th>Stok Fisik</th>
                                <th>Selisih</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let dataTable;

    // Fungsi untuk memuat data spare part
    function loadSparepartData(bengkelId) {

        // Destroy dulu kalau sudah pernah dibuat
        if ($.fn.DataTable.isDataTable('#dataTableStokOpname')) {
            dataTable.clear().destroy();
        }

        dataTable = $('#dataTableStokOpname').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'pages/admin_bengkel/get_sparepart_by_bengkel.php',
                type: 'POST',
                data: function (d) {
                    d.bengkel_id = bengkelId;
                },
                error: function () {
                    Swal.fire('Error!', 'Gagal memuat data spare part.', 'error');
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'nama_sparepart',
                    render: function (data, type, row) {
                        return `
                            <input type="hidden" class="sparepart-id" value="${row.id_sparepart}">
                            ${data}
                        `;
                    }
                },
                {
                    data: 'stok_pcs',
                    className: 'stok-sistem'
                },
                {
                    data: 'stok_pcs',
                    orderable: false,
                    searchable: false,
                    render: function (data) {
                        return `
                            <input type="number"
                                class="form-control stok-fisik"
                                value="${data}"
                                min="0">
                        `;
                    }
                },
                {
                    data: null,
                    className: 'selisih',
                    orderable: false,
                    searchable: false,
                    render: function () {
                        return 0;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function () {
                        return `<input type="text" class="form-control keterangan">`;
                    }
                }
            ],
            paging: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            order: [[1, 'asc']]
        });
    }


    // Panggil fungsi saat halaman pertama kali dimuat
    const initialBengkelId = $('#selectBengkel').val();
    if (initialBengkelId) {
        loadSparepartData(initialBengkelId);
    }

    // Panggil fungsi saat bengkel diubah
    $('#selectBengkel').on('change', function() {
        const bengkelId = $(this).val();
        loadSparepartData(bengkelId);
    });

    // Menghitung selisih saat stok fisik berubah
    $(document).on('input', '.stok-fisik', function() {
        const stokSistem = parseInt($(this).closest('tr').find('.stok-sistem').text());
        const stokFisik = parseInt($(this).val());
        const selisih = stokFisik - stokSistem;
        $(this).closest('tr').find('.selisih').text(selisih);
    });
    // Tombol Selesaikan Opname
    $('#btnSelesaiOpname').on('click', function () {
        Swal.fire({
            title: 'Selesaikan Stok Opname?',
            text: 'Jumlah stok di sistem akan diperbarui.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Selesaikan!'
        }).then((result) => {
            if (!result.isConfirmed) return;

            // 🔒 Pastikan DataTable sudah terinisialisasi
            if (!$.fn.DataTable.isDataTable('#dataTableStokOpname')) {
                Swal.fire('Error', 'Data tabel belum siap.', 'error');
                return;
            }

            let dataOpname = [];

            // 🔥 AMAN UNTUK DATATABLE (server / client)
            dataTable.rows({ page: 'current' }).every(function () {
                let row = $(this.node());

                let id = row.find('.sparepart-id').val();
                let stokFisik = row.find('.stok-fisik').val();
                let keterangan = row.find('.keterangan').val();

                if (id && stokFisik !== '') {
                    dataOpname.push({
                        spare_part_id: id,
                        stok_fisik: parseInt(stokFisik),
                        keterangan: keterangan || ''
                    });
                }
            });

            if (dataOpname.length === 0) {
                Swal.fire('Info', 'Tidak ada data untuk diproses.', 'info');
                return;
            }

            // 🔒 Disable tombol biar tidak double submit
            $('#btnSelesaiOpname').prop('disabled', true);

            $.ajax({
                url: 'pages/admin_bengkel/proses_opname.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'simpan_opname',
                    data_opname: JSON.stringify(dataOpname),
                    bengkel_id: $('#selectBengkel').val()
                },
                success: function (res) {
                    if (res.success) {
                        Swal.fire('Berhasil!', res.message, 'success')
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire('Gagal!', res.message || 'Terjadi kesalahan', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error!', 'Gagal menyimpan data stok opname.', 'error');
                },
                complete: function () {
                    $('#btnSelesaiOpname').prop('disabled', false);
                }
            });
        });
    });


    // ============================
    // NOTIFIKASI DARI URL
    // ============================
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');

    if (status && message) {
        Swal.fire({
            icon: status === 'success'
                ? 'success'
                : status === 'warning'
                ? 'warning'
                : 'error',
            title: status === 'success' ? 'Berhasil!' : 'Peringatan!',
            text: decodeURIComponent(message),
            showConfirmButton: false,
            timer: 3000
        });

        window.history.replaceState({}, document.title, window.location.pathname);
    }

    $('#printPdfOpname').on('click', function () {
        const bengkelId = $('#selectBengkel').val();
        window.open(
            'pages/admin_bengkel/print_opname_pdf.php?bengkel_id=' + bengkelId,
            '_blank'
        );
    });



});
</script>