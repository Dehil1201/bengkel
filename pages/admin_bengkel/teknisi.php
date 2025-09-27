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
// LOGIKA PEMROSESAN TEKNISI
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=teknisi';
    
    if ($action == 'tambah') {
        $nama_teknisi = sanitize_input($_POST['nama_teknisi']);
        $telepon = sanitize_input($_POST['telepon']);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        // Pastikan bengkel ID yang dikirim valid dan sesuai dengan yang diakses user
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $query_tambah = "INSERT INTO teknisis (nama_teknisi, telepon, bengkel_id) VALUES ('$nama_teknisi', '$telepon', '$bengkel_id')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Teknisi berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan teknisi.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_teknisi = sanitize_input($_POST['id_teknisi']);
        $nama_teknisi = sanitize_input($_POST['nama_teknisi']);
        $telepon = sanitize_input($_POST['telepon']);
        
        // Pastikan teknisi milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM teknisi WHERE id_teknisi = '$id_teknisi'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Teknisi tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Teknisi tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE teknisis SET nama_teknisi='$nama_teknisi', telepon='$telepon' WHERE id_teknisi='$id_teknisi'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Teknisi berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah teknisi.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_teknisi = sanitize_input($_POST['id_teknisi']);

        // Pastikan teknisi milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM teknisis WHERE id_teknisi = '$id_teknisi'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Teknisi tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Teknisi tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM teknisis WHERE id_teknisi='$id_teknisi'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Teknisi berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus teknisi.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Teknisi</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahTeknisi">
                        <i class="fa fa-plus"></i> Tambah Teknisi
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Teknisi</th>
                                <th>Telepon</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_teknisi = mysqli_query($conn, "SELECT t.*, b.nama_bengkel FROM teknisis t JOIN bengkels b ON t.bengkel_id = b.id_bengkel WHERE t.bengkel_id IN ($bengkel_ids_string) ORDER BY t.nama_teknisi ASC");
                            if (mysqli_num_rows($query_teknisi) > 0) {
                                while ($data_teknisi = mysqli_fetch_assoc($query_teknisi)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_teknisi['nama_teknisi']); ?></td>
                                        <td><?= htmlspecialchars($data_teknisi['telepon']); ?></td>
                                        <td><?= htmlspecialchars($data_teknisi['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-teknisi"
                                                data-id="<?= $data_teknisi['id_teknisi']; ?>"
                                                data-nama="<?= htmlspecialchars($data_teknisi['nama_teknisi']); ?>"
                                                data-telepon="<?= htmlspecialchars($data_teknisi['telepon']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-teknisi"
                                                data-id="<?= $data_teknisi['id_teknisi']; ?>"
                                                data-nama="<?= htmlspecialchars($data_teknisi['nama_teknisi']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>Tidak ada data teknisi.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahTeknisi" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Teknisi</h4>
            </div>
            <form id="formTambahTeknisi" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_teknisi">Nama Teknisi</label>
                        <input type="text" class="form-control" id="nama_teknisi" name="nama_teknisi" required>
                    </div>
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="text" class="form-control" id="telepon" name="telepon">
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

<div class="modal fade" id="modalUbahTeknisi" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Teknisi</h4>
            </div>
            <form id="formUbahTeknisi" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_teknisi" id="ubah_id_teknisi">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_nama_teknisi">Nama Teknisi</label>
                        <input type="text" class="form-control" id="ubah_nama_teknisi" name="nama_teknisi" required>
                    </div>
                    <div class="form-group">
                        <label for="ubah_telepon">Telepon</label>
                        <input type="text" class="form-control" id="ubah_telepon" name="telepon">
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

<div class="modal fade" id="modalHapusTeknisi" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Teknisi</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus teknisi **<span id="hapus_nama_teknisi"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusTeknisi" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_teknisi" id="hapus_id_teknisi">
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

    $(document).on('click', '.btn-ubah-teknisi', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const telepon = $(this).data('telepon');

        $('#ubah_id_teknisi').val(id);
        $('#ubah_nama_teknisi').val(nama);
        $('#ubah_telepon').val(telepon);

        $('#modalUbahTeknisi').modal('show');
    });

    $(document).on('click', '.btn-hapus-teknisi', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_teknisi').val(id);
        $('#hapus_nama_teknisi').text(nama);

        $('#modalHapusTeknisi').modal('show');
    });
});
</script>