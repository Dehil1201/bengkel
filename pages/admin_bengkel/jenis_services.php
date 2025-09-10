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
// LOGIKA PEMROSESAN JENIS SERVICE
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=jenis_services';
    
    if ($action == 'tambah') {
        $nama_servis = sanitize_input($_POST['nama_servis']);
        $biaya = sanitize_input($_POST['biaya']);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        // Pastikan bengkel ID yang dikirim valid dan sesuai dengan yang diakses user
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $query_tambah = "INSERT INTO jenis_servis (nama_servis, biaya, bengkel_id) VALUES ('$nama_servis', '$biaya', '$bengkel_id')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Jenis Service berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan Jenis Service.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_servis = sanitize_input($_POST['id_servis']);
        $nama_servis = sanitize_input($_POST['nama_servis']);
        $biaya = sanitize_input($_POST['biaya']);
        
        // Pastikan jenis service milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM jenis_servis WHERE id_servis = '$id_servis'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Jenis Service tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Jenis Service tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE jenis_servis SET nama_servis='$nama_servis', biaya='$biaya' WHERE id_servis='$id_servis'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Jenis Service berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah Jenis Service.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_servis = sanitize_input($_POST['id_servis']);

        // Pastikan jenis service milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM jenis_servis WHERE id_servis = '$id_servis'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Jenis Service tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Jenis Service tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM jenis_servis WHERE id_servis='$id_servis'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Jenis Service berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus Jenis Service.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Jenis Service</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahJenisService">
                        <i class="fa fa-plus"></i> Tambah Jenis Service
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Service</th>
                                <th>Biaya</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_jenis_servis = mysqli_query($conn, "SELECT js.*, b.nama_bengkel FROM jenis_servis js JOIN bengkels b ON js.bengkel_id = b.id_bengkel WHERE js.bengkel_id IN ($bengkel_ids_string) ORDER BY js.nama_servis ASC");
                            if (mysqli_num_rows($query_jenis_servis) > 0) {
                                while ($data_jenis = mysqli_fetch_assoc($query_jenis_servis)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_jenis['nama_servis']); ?></td>
                                        <td>Rp <?= number_format($data_jenis['biaya'], 0, ',', '.'); ?></td>
                                        <td><?= htmlspecialchars($data_jenis['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-jenis"
                                                data-id="<?= $data_jenis['id_servis']; ?>"
                                                data-nama="<?= htmlspecialchars($data_jenis['nama_servis']); ?>"
                                                data-biaya="<?= htmlspecialchars($data_jenis['biaya']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-jenis"
                                                data-id="<?= $data_jenis['id_servis']; ?>"
                                                data-nama="<?= htmlspecialchars($data_jenis['nama_servis']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>Tidak ada data jenis service.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahJenisService" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Jenis Service</h4>
            </div>
            <form id="formTambahJenisService" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_servis">Nama Service</label>
                        <input type="text" class="form-control" id="nama_servis" name="nama_servis" required>
                    </div>
                    <div class="form-group">
                        <label for="biaya">Biaya</label>
                        <div class="input-group">
                            <span class="input-group-addon">Rp</span>
                            <input type="number" class="form-control" id="biaya" name="biaya" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bengkel_id_add">Bengkel</label>
                        <select class="form-control" id="bengkel_id_add" name="bengkel_id" required>
                            <?php 
                            $query_bengkel_add = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE id_bengkel IN ($bengkel_ids_string)");
                            while ($row_bengkel = mysqli_fetch_assoc($query_bengkel_add)) {
                                echo "<option value='{$row_bengkel['id_bengkel']}'>{$row_bengkel['nama_bengkel']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUbahJenisService" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Jenis Service</h4>
            </div>
            <form id="formUbahJenisService" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_servis" id="ubah_id_servis">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_nama_servis">Nama Service</label>
                        <input type="text" class="form-control" id="ubah_nama_servis" name="nama_servis" required>
                    </div>
                    <div class="form-group">
                        <label for="ubah_biaya">Biaya</label>
                        <div class="input-group">
                            <span class="input-group-addon">Rp</span>
                            <input type="number" class="form-control" id="ubah_biaya" name="biaya" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHapusJenisService" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Jenis Service</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus jenis service **<span id="hapus_nama_servis"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusJenisService" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_servis" id="hapus_id_servis">
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('#dataTable').DataTable();

    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');

    if (status && message) {
        Swal.fire({
            icon: status === 'success' ? 'success' : 'error',
            title: status === 'success' ? 'Berhasil!' : 'Gagal!',
            text: message,
            showConfirmButton: false,
            timer: 2000
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    $(document).on('click', '.btn-ubah-jenis', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const biaya = $(this).data('biaya');

        $('#ubah_id_servis').val(id);
        $('#ubah_nama_servis').val(nama);
        $('#ubah_biaya').val(biaya);

        $('#modalUbahJenisService').modal('show');
    });

    $(document).on('click', '.btn-hapus-jenis', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_servis').val(id);
        $('#hapus_nama_servis').text(nama);

        $('#modalHapusJenisService').modal('show');
    });
});
</script>