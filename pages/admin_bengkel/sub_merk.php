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
// LOGIKA PEMROSESAN SUBMERK
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=submerk';
    
    if ($action == 'tambah') {
        $id_merk = sanitize_input($_POST['id_merk']);
        $nama_submerk = sanitize_input($_POST['nama_submerk']);
        
        // Pastikan merek yang dipilih milik bengkel yang bisa diakses user
        $check_merk_query = mysqli_query($conn, "SELECT bengkel_id FROM merks WHERE id_merk = '$id_merk'");
        if ($check_merk_row = mysqli_fetch_assoc($check_merk_query)) {
            if (!in_array($check_merk_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Merek tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Merek tidak ditemukan.");
            exit();
        }

        $query_tambah = "INSERT INTO submerks (nama_submerk, id_merk) VALUES ('$nama_submerk', '$id_merk')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Submerk berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan submerk.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_submerk = sanitize_input($_POST['id_submerk']);
        $id_merk = sanitize_input($_POST['id_merk']);
        $nama_submerk = sanitize_input($_POST['nama_submerk']);
        
        // Pastikan submerk milik bengkel yang bisa diakses user
        $check_submerk_query = mysqli_query($conn, "SELECT ms.bengkel_id FROM submerks ss JOIN merks ms ON ss.id_merk = ms.id_merk WHERE ss.id_submerk = '$id_submerk'");
        if ($check_submerk_row = mysqli_fetch_assoc($check_submerk_query)) {
            if (!in_array($check_submerk_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Submerk tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Submerk tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE submerks SET nama_submerk='$nama_submerk', id_merk='$id_merk' WHERE id_submerk='$id_submerk'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Submerk berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah submerk.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_submerk = sanitize_input($_POST['id_submerk']);

        // Pastikan submerk milik bengkel yang bisa diakses user
        $check_submerk_query = mysqli_query($conn, "SELECT ms.bengkel_id FROM submerks ss JOIN merks ms ON ss.id_merk = ms.id_merk WHERE ss.id_submerk = '$id_submerk'");
        if ($check_submerk_row = mysqli_fetch_assoc($check_submerk_query)) {
            if (!in_array($check_submerk_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Submerk tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Submerk tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM submerks WHERE id_submerk='$id_submerk'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Submerk berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus submerk.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Submerk Spare Part</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahSubmerk">
                        <i class="fa fa-plus"></i> Tambah Submerk
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Submerk</th>
                                <th>Merk</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_submerk = mysqli_query($conn, "SELECT ss.*, ms.nama_merk, b.nama_bengkel FROM submerks ss JOIN merks ms ON ss.id_merk = ms.id_merk JOIN bengkels b ON ms.bengkel_id = b.id_bengkel WHERE ms.bengkel_id IN ($bengkel_ids_string) ORDER BY ss.nama_submerk ASC");
                            if (mysqli_num_rows($query_submerk) > 0) {
                                while ($data_submerk = mysqli_fetch_assoc($query_submerk)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_submerk['nama_submerk']); ?></td>
                                        <td><?= htmlspecialchars($data_submerk['nama_merk']); ?></td>
                                        <td><?= htmlspecialchars($data_submerk['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-submerk"
                                                data-id="<?= $data_submerk['id_submerk']; ?>"
                                                data-nama="<?= htmlspecialchars($data_submerk['nama_submerk']); ?>"
                                                data-merk-id="<?= htmlspecialchars($data_submerk['id_merk']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-submerk"
                                                data-id="<?= $data_submerk['id_submerk']; ?>"
                                                data-nama="<?= htmlspecialchars($data_submerk['nama_submerk']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>Tidak ada data submerk spare part.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahSubmerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Submerk Spare Part</h4>
            </div>
            <form id="formTambahSubmerk" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="id_merk_add">Merk</label>
                        <select class="form-control" id="id_merk_add" name="id_merk" required style="width:100%">
                            <?php 
                            $query_merk_add = mysqli_query($conn, "SELECT id_merk, nama_merk FROM merks WHERE bengkel_id IN ($bengkel_ids_string) ORDER BY nama_merk ASC");
                            while ($row_merk = mysqli_fetch_assoc($query_merk_add)) {
                                echo "<option value='{$row_merk['id_merk']}'>{$row_merk['nama_merk']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nama_submerk_add">Nama Submerk</label>
                        <input type="text" class="form-control" id="nama_submerk_add" name="nama_submerk" required>
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

<div class="modal fade" id="modalUbahSubmerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Submerk Spare Part</h4>
            </div>
            <form id="formUbahSubmerk" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_submerk" id="ubah_id_submerk">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_id_merk">Merk</label>
                        <select class="form-control" id="ubah_id_merk" name="id_merk" required style="width:100%">
                            <?php 
                            $query_merk_ubah = mysqli_query($conn, "SELECT id_merk, nama_merk FROM merks WHERE bengkel_id IN ($bengkel_ids_string) ORDER BY nama_merk ASC");
                            while ($row_merk = mysqli_fetch_assoc($query_merk_ubah)) {
                                echo "<option value='{$row_merk['id_merk']}'>{$row_merk['nama_merk']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ubah_nama_submerk">Nama Submerk</label>
                        <input type="text" class="form-control" id="ubah_nama_submerk" name="nama_submerk" required>
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

<div class="modal fade" id="modalHapusSubmerk" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Submerk Spare Part</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus submerk **<span id="hapus_nama_submerk"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusSubmerk" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_submerk" id="hapus_id_submerk">
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable();

    // Inisialisasi Select2 pada dropdown tambah
    $('#id_merk_add').select2({
        dropdownParent: $('#modalTambahSubmerk')
    });

    // Inisialisasi Select2 pada dropdown ubah
    $('#ubah_id_merk').select2({
        dropdownParent: $('#modalUbahSubmerk')
    });

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

    $(document).on('click', '.btn-ubah-submerk', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const merkId = $(this).data('merk-id');

        $('#ubah_id_submerk').val(id);
        $('#ubah_nama_submerk').val(nama);
        // Mengatur nilai Select2
        $('#ubah_id_merk').val(merkId).trigger('change');

        $('#modalUbahSubmerk').modal('show');
    });

    $(document).on('click', '.btn-hapus-submerk', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_submerk').val(id);
        $('#hapus_nama_submerk').text(nama);

        $('#modalHapusSubmerk').modal('show');
    });
});
</script>