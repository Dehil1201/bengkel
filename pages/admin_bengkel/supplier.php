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
// LOGIKA PEMROSESAN SUPPLIER
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=supplier';
    
    if ($action == 'tambah') {
        $nama_supplier = sanitize_input($_POST['nama_supplier']);
        $alamat = sanitize_input($_POST['alamat']);
        $telepon = sanitize_input($_POST['telepon']);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);

        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $query_tambah = "INSERT INTO suppliers (nama_supplier, alamat, telepon, bengkel_id) VALUES ('$nama_supplier', '$alamat', '$telepon', '$bengkel_id')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Supplier berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan supplier.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_supplier = sanitize_input($_POST['id_supplier']);
        $nama_supplier = sanitize_input($_POST['nama_supplier']);
        $alamat = sanitize_input($_POST['alamat']);
        $telepon = sanitize_input($_POST['telepon']);

        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM suppliers WHERE id_supplier = '$id_supplier'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Supplier tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Supplier tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE suppliers SET nama_supplier='$nama_supplier', alamat='$alamat', telepon='$telepon' WHERE id_supplier='$id_supplier'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Supplier berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah supplier.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_supplier = sanitize_input($_POST['id_supplier']);

        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM suppliers WHERE id_supplier = '$id_supplier'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Supplier tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Supplier tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM suppliers WHERE id_supplier='$id_supplier'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Supplier berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus supplier.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Supplier</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahSupplier">
                        <i class="fa fa-plus"></i> Tambah Supplier
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Supplier</th>
                                <th>Alamat</th>
                                <th>Telepon</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_supplier = mysqli_query($conn, "SELECT s.*, b.nama_bengkel FROM suppliers s JOIN bengkels b ON s.bengkel_id = b.id_bengkel WHERE s.bengkel_id IN ($bengkel_ids_string) ORDER BY s.nama_supplier ASC");
                            if (mysqli_num_rows($query_supplier) > 0) {
                                while ($data_supplier = mysqli_fetch_assoc($query_supplier)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_supplier['nama_supplier']); ?></td>
                                        <td><?= htmlspecialchars($data_supplier['alamat']); ?></td>
                                        <td><?= htmlspecialchars($data_supplier['telepon']); ?></td>
                                        <td><?= htmlspecialchars($data_supplier['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-supplier"
                                                data-id="<?= $data_supplier['id_supplier']; ?>"
                                                data-nama="<?= htmlspecialchars($data_supplier['nama_supplier']); ?>"
                                                data-alamat="<?= htmlspecialchars($data_supplier['alamat']); ?>"
                                                data-telepon="<?= htmlspecialchars($data_supplier['telepon']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-supplier"
                                                data-id="<?= $data_supplier['id_supplier']; ?>"
                                                data-nama="<?= htmlspecialchars($data_supplier['nama_supplier']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data supplier.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahSupplier" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Supplier</h4>
            </div>
            <form id="formTambahSupplier" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_supplier">Nama Supplier</label>
                        <input type="text" class="form-control" id="nama_supplier" name="nama_supplier" required>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
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

<div class="modal fade" id="modalUbahSupplier" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Supplier</h4>
            </div>
            <form id="formUbahSupplier" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_supplier" id="ubah_id_supplier">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_nama_supplier">Nama Supplier</label>
                        <input type="text" class="form-control" id="ubah_nama_supplier" name="nama_supplier" required>
                    </div>
                    <div class="form-group">
                        <label for="ubah_alamat">Alamat</label>
                        <textarea class="form-control" id="ubah_alamat" name="alamat" rows="3"></textarea>
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

<div class="modal fade" id="modalHapusSupplier" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Supplier</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus supplier **<span id="hapus_nama_supplier"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusSupplier" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_supplier" id="hapus_id_supplier">
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

    $(document).on('click', '.btn-ubah-supplier', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const alamat = $(this).data('alamat');
        const telepon = $(this).data('telepon');

        $('#ubah_id_supplier').val(id);
        $('#ubah_nama_supplier').val(nama);
        $('#ubah_alamat').val(alamat);
        $('#ubah_telepon').val(telepon);

        $('#modalUbahSupplier').modal('show');
    });

    $(document).on('click', '.btn-hapus-supplier', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_supplier').val(id);
        $('#hapus_nama_supplier').text(nama);

        $('#modalHapusSupplier').modal('show');
    });
});
</script>