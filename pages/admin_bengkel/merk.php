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
// LOGIKA PEMROSESAN MERK SPARE PART
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=merk';
    
    if ($action == 'tambah') {
        $nama_merk = sanitize_input($_POST['nama_merk']);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        // Pastikan bengkel ID yang dikirim valid
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $query_tambah = "INSERT INTO merks (nama_merk, bengkel_id) VALUES ('$nama_merk', '$bengkel_id')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Merk spare part berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan merk spare part.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_merk = sanitize_input($_POST['id_merk']);
        $nama_merk = sanitize_input($_POST['nama_merk']);
        
        // Pastikan merek milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM merks WHERE id_merk = '$id_merk'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Merek tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Merk tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE merks SET nama_merk='$nama_merk' WHERE id_merk='$id_merk'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Merk spare part berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah merk spare part.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_merk = sanitize_input($_POST['id_merk']);

        // Pastikan merek milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM merks WHERE id_merk = '$id_merk'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Merek tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Merk tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM merks WHERE id_merk='$id_merk'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Merk spare part berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus merk spare part.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Merk Spare Part</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahMerk">
                        <i class="fa fa-plus"></i> Tambah Merk
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Merk</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_merk = mysqli_query($conn, "SELECT ms.*, b.nama_bengkel FROM merks ms JOIN bengkels b ON ms.bengkel_id = b.id_bengkel WHERE ms.bengkel_id IN ($bengkel_ids_string) ORDER BY ms.nama_merk ASC");
                            if (mysqli_num_rows($query_merk) > 0) {
                                while ($data_merk = mysqli_fetch_assoc($query_merk)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_merk['nama_merk']); ?></td>
                                        <td><?= htmlspecialchars($data_merk['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-merk"
                                                data-id="<?= $data_merk['id_merk']; ?>"
                                                data-nama="<?= htmlspecialchars($data_merk['nama_merk']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-merk"
                                                data-id="<?= $data_merk['id_merk']; ?>"
                                                data-nama="<?= htmlspecialchars($data_merk['nama_merk']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>Tidak ada data merk spare part.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahMerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Merk Spare Part</h4>
            </div>
            <form id="formTambahMerk" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_merk">Nama Merk</label>
                        <input type="text" class="form-control" id="nama_merk" name="nama_merk" required>
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

<div class="modal fade" id="modalUbahMerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Merk Spare Part</h4>
            </div>
            <form id="formUbahMerk" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_merk" id="ubah_id_merk">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_nama_merk">Nama Merk</label>
                        <input type="text" class="form-control" id="ubah_nama_merk" name="nama_merk" required>
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

<div class="modal fade" id="modalHapusMerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Merk Spare Part</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus merk **<span id="hapus_nama_merk"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusMerk" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_merk" id="hapus_id_merk">
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

    $(document).on('click', '.btn-ubah-merk', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');

        $('#ubah_id_merk').val(id);
        $('#ubah_nama_merk').val(nama);

        $('#modalUbahMerk').modal('show');
    });

    $(document).on('click', '.btn-hapus-merk', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_merk').val(id);
        $('#hapus_nama_merk').text(nama);

        $('#modalHapusMerk').modal('show');
    });
});
</script>