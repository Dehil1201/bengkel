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
$allowed_roles = ['owner_bengkel', 'admin_bengkel', 'kasir'];

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
} else if ($user_role === 'admin_bengkel' || $user_role === 'kasir') {
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
// LOGIKA PEMROSESAN PELANGGAN
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $current_page = '?page=pelanggan';
    
    if ($action == 'tambah') {
        $nama_pelanggan = sanitize_input($_POST['nama_pelanggan']);
        $alamat = sanitize_input($_POST['alamat']);
        $telepon = sanitize_input($_POST['telepon']);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        // Pastikan bengkel ID yang dikirim valid dan sesuai dengan yang diakses user
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            header("Location: $current_page&status=error&message=Akses ditolak. Bengkel tidak valid.");
            exit();
        }

        $query_tambah = "INSERT INTO pelanggans (nama_pelanggan, alamat, telepon, bengkel_id) VALUES ('$nama_pelanggan', '$alamat', '$telepon', '$bengkel_id')";
        if (mysqli_query($conn, $query_tambah)) {
            header("Location: $current_page&status=success&message=Pelanggan berhasil ditambahkan.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menambahkan pelanggan.");
        }
        exit();

    } elseif ($action == 'ubah') {
        $id_pelanggan = sanitize_input($_POST['id_pelanggan']);
        $nama_pelanggan = sanitize_input($_POST['nama_pelanggan']);
        $alamat = sanitize_input($_POST['alamat']);
        $telepon = sanitize_input($_POST['telepon']);
        
        // Pastikan pelanggan milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM pelanggans WHERE id_pelanggan = '$id_pelanggan'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Pelanggan tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Pelanggan tidak ditemukan.");
            exit();
        }

        $query_ubah = "UPDATE pelanggans SET nama_pelanggan='$nama_pelanggan', alamat='$alamat', telepon='$telepon' WHERE id_pelanggan='$id_pelanggan'";
        if (mysqli_query($conn, $query_ubah)) {
            header("Location: $current_page&status=success&message=Pelanggan berhasil diubah.");
        } else {
            header("Location: $current_page&status=error&message=Gagal mengubah pelanggan.");
        }
        exit();

    } elseif ($action == 'hapus') {
        $id_pelanggan = sanitize_input($_POST['id_pelanggan']);

        // Pastikan pelanggan milik bengkel yang bisa diakses user
        $check_query = mysqli_query($conn, "SELECT bengkel_id FROM pelanggans WHERE id_pelanggan = '$id_pelanggan'");
        if ($check_row = mysqli_fetch_assoc($check_query)) {
            if (!in_array($check_row['bengkel_id'], $accessible_bengkel_ids)) {
                header("Location: $current_page&status=error&message=Akses ditolak. Pelanggan tidak valid.");
                exit();
            }
        } else {
            header("Location: $current_page&status=error&message=Pelanggan tidak ditemukan.");
            exit();
        }

        $query_hapus = "DELETE FROM pelanggans WHERE id_pelanggan='$id_pelanggan'";
        if (mysqli_query($conn, $query_hapus)) {
            header("Location: $current_page&status=success&message=Pelanggan berhasil dihapus.");
        } else {
            header("Location: $current_page&status=error&message=Gagal menghapus pelanggan.");
        }
        exit();
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Pelanggan</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahPelanggan">
                        <i class="fa fa-plus"></i> Tambah Pelanggan
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Pelanggan</th>
                                <th>Alamat</th>
                                <th>Telepon</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query_pelanggan = mysqli_query($conn, "SELECT p.*, b.nama_bengkel FROM pelanggans p JOIN bengkels b ON p.bengkel_id = b.id_bengkel WHERE p.bengkel_id IN ($bengkel_ids_string) ORDER BY p.nama_pelanggan ASC");
                            if (mysqli_num_rows($query_pelanggan) > 0) {
                                while ($data_pelanggan = mysqli_fetch_assoc($query_pelanggan)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_pelanggan['nama_pelanggan']); ?></td>
                                        <td><?= htmlspecialchars($data_pelanggan['alamat']); ?></td>
                                        <td><?= htmlspecialchars($data_pelanggan['telepon']); ?></td>
                                        <td><?= htmlspecialchars($data_pelanggan['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-ubah-pelanggan"
                                                data-id="<?= $data_pelanggan['id_pelanggan']; ?>"
                                                data-nama="<?= htmlspecialchars($data_pelanggan['nama_pelanggan']); ?>"
                                                data-alamat="<?= htmlspecialchars($data_pelanggan['alamat']); ?>"
                                                data-telepon="<?= htmlspecialchars($data_pelanggan['telepon']); ?>">
                                                <i class="fa fa-edit"></i> Ubah
                                            </button>
                                            <button class="btn btn-danger btn-xs btn-hapus-pelanggan"
                                                data-id="<?= $data_pelanggan['id_pelanggan']; ?>"
                                                data-nama="<?= htmlspecialchars($data_pelanggan['nama_pelanggan']); ?>">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data pelanggan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahPelanggan" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Pelanggan</h4>
            </div>
            <form id="formTambahPelanggan" method="POST" action="">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_pelanggan">Nama Pelanggan</label>
                        <input type="text" class="form-control" id="nama_pelanggan" name="nama_pelanggan" required>
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

<div class="modal fade" id="modalUbahPelanggan" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ubah Pelanggan</h4>
            </div>
            <form id="formUbahPelanggan" method="POST" action="">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_pelanggan" id="ubah_id_pelanggan">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ubah_nama_pelanggan">Nama Pelanggan</label>
                        <input type="text" class="form-control" id="ubah_nama_pelanggan" name="nama_pelanggan" required>
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

<div class="modal fade" id="modalHapusPelanggan" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hapus Pelanggan</h4>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pelanggan **<span id="hapus_nama_pelanggan"></span>**?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <form id="formHapusPelanggan" method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_pelanggan" id="hapus_id_pelanggan">
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

    $(document).on('click', '.btn-ubah-pelanggan', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const alamat = $(this).data('alamat');
        const telepon = $(this).data('telepon');

        $('#ubah_id_pelanggan').val(id);
        $('#ubah_nama_pelanggan').val(nama);
        $('#ubah_alamat').val(alamat);
        $('#ubah_telepon').val(telepon);

        $('#modalUbahPelanggan').modal('show');
    });

    $(document).on('click', '.btn-hapus-pelanggan', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        $('#hapus_id_pelanggan').val(id);
        $('#hapus_nama_pelanggan').text(nama);

        $('#modalHapusPelanggan').modal('show');
    });
});
</script>