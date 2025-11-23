<?php
// pastikan koneksi $conn sudah di-include sebelum file ini
// contoh: include '../../inc/koneksi.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$id_user = $_SESSION['id_user'];
$qbengkel = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user'");
$dbengkel = mysqli_fetch_array($qbengkel);
$id_bengkel = $dbengkel['bengkel_id'];
$id_bengkel_login = $id_bengkel; // AMBIL ID BENGKEL DARI LOGIN


// Fungsi untuk membersihkan dan mengamankan input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// -----------------------------
// PROSES TAMBAH / EDIT / HAPUS
// -----------------------------
$pesan_aksi = '';

// Tambah user
if (isset($_POST['tambah_user'])) {
    $nama_lengkap = sanitize_input($_POST['nama_lengkap'] ?? '');
    $email        = sanitize_input($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = sanitize_input($_POST['role'] ?? '');
    $id_bengkel = $id_bengkel_login;


    if (empty($nama_lengkap) || empty($email) || empty($password) || empty($role) || empty($id_bengkel)) {
        $pesan_aksi = "input_tidak_lengkap";
    } else {
        $cek_email = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
        if ($cek_email && mysqli_num_rows($cek_email) > 0) {
            $pesan_aksi = "email_sudah_ada";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (nama_lengkap, email, password, role, bengkel_id) 
                      VALUES ('$nama_lengkap', '$email', '$password_hash', '$role', '$id_bengkel')";
            $pesan_aksi = (mysqli_query($conn, $query)) ? "sukses_tambah" : "gagal_tambah";
        }
    }

    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
}

// Edit user
if (isset($_POST['edit_user'])) {
    $id_user      = sanitize_input($_POST['id_user'] ?? '');
    $nama_lengkap = sanitize_input($_POST['nama_lengkap'] ?? '');
    $email        = sanitize_input($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = sanitize_input($_POST['role'] ?? '');
    $id_bengkel   = sanitize_input($_POST['id_bengkel'] ?? '');

    if (empty($id_user) || empty($nama_lengkap) || empty($email) || empty($role) || empty($id_bengkel)) {
        $pesan_aksi = "input_tidak_lengkap";
    } else {
        $update_query = "UPDATE users 
                         SET nama_lengkap = '$nama_lengkap', email = '$email', role = '$role', bengkel_id = '$id_bengkel'";

        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_query .= ", password = '$password_hash'";
        }

        $update_query .= " WHERE id_user = '$id_user'";

        $pesan_aksi = (mysqli_query($conn, $update_query)) ? "sukses_edit" : "gagal_edit";
    }

    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
}

// Hapus user
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    $id_user = sanitize_input($_GET['id']);

    if (empty($id_user)) {
        $pesan_aksi = "gagal_hapus";
    } else {
        $query = "DELETE FROM users WHERE id_user = '$id_user'";
        $pesan_aksi = (mysqli_query($conn, $query)) ? "sukses_hapus" : "gagal_hapus";
    }

    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
}

// ----------------------------------------------------
// AMBIL DATA BENGKEL (TAMPILAN FILTER) DAN DAFTAR USER
// ----------------------------------------------------
$query_bengkels = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels ORDER BY nama_bengkel ASC");
$bengkels = [];
while ($row = mysqli_fetch_assoc($query_bengkels)) {
    $bengkels[] = $row;
}

if ($id_bengkel_login) {
    $q = mysqli_query($conn, "SELECT nama_bengkel FROM bengkels WHERE id_bengkel = '$id_bengkel_login' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $selected_bengkel_name = mysqli_fetch_assoc($q)['nama_bengkel'];
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Manajemen Pengguna</h3>
                <div class="box-tools pull-right">
                    <a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahUser"><i class="fa fa-plus"></i> Tambah Pengguna</a>
                </div>
            </div>

            <div class="box-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $sql_where = "";
                            if ($id_bengkel_login) {
                                $sql_where = "WHERE u.bengkel_id = '$id_bengkel_login'";
                            }

                            $query_users = mysqli_query($conn, "
                                SELECT u.id_user, u.nama_lengkap, u.email, u.role, u.bengkel_id, b.nama_bengkel
                                FROM users u
                                LEFT JOIN bengkels b ON u.bengkel_id = b.id_bengkel
                                $sql_where
                                ORDER BY u.role, u.nama_lengkap ASC
                            ");

                            if ($query_users && mysqli_num_rows($query_users) > 0) {
                                while ($data_user = mysqli_fetch_assoc($query_users)) {
                                    ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_user['nama_lengkap']); ?></td>
                                        <td><?= htmlspecialchars($data_user['email']); ?></td>
                                        <td class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $data_user['role'])); ?></td>
                                        <td><?= htmlspecialchars($data_user['nama_bengkel'] ?? '-'); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-warning btn-xs btn-edit"
                                               data-id="<?= $data_user['id_user']; ?>"
                                               data-nama="<?= htmlspecialchars($data_user['nama_lengkap']); ?>"
                                               data-email="<?= htmlspecialchars($data_user['email']); ?>"
                                               data-role="<?= htmlspecialchars($data_user['role']); ?>"
                                               data-bengkel-id="<?= htmlspecialchars($data_user['bengkel_id']); ?>">
                                                <i class="fa fa-pencil"></i> Edit
                                            </a>

                                            <a href="?page=management_users_by_owner&aksi=hapus&id=<?= $data_user['id_user']; ?>"
                                               onclick="return confirm('Yakin ingin menghapus pengguna ini?')"
                                               class="btn btn-danger btn-xs">
                                                <i class="fa fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data pengguna.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div> <!-- /.table-responsive -->
            </div> <!-- /.box-body -->
        </div> <!-- /.box -->
    </div> <!-- /.col -->
</div> <!-- /.row -->

<!-- MODAL TAMBAH / EDIT USER -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" role="dialog" aria-labelledby="modalUserLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="formUser" method="POST" action="?page=management_users_by_owner">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title" id="modalUserLabel">Tambah Pengguna Baru</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_user" id="id_user_edit" value="">
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                    </div>

                    <div class="form-group">
                        <label for="email_user">Email</label>
                        <input type="email" class="form-control" id="email_user" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password_user">Password <small>(kosongkan jika tidak ingin mengubah)</small></label>
                        <input type="password" class="form-control" id="password_user" name="password">
                    </div>

                    <div class="form-group">
                        <label for="role_user">Hak Akses</label>
                        <select class="form-control" id="role_user" name="role" required>
                            <option value="admin_bengkel">Admin</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="kasir">Kasir</option>
                        </select>
                    </div>
                    <input type="hidden" name="id_bengkel" value="<?= $id_bengkel_login ?>">



                </div> <!-- /.modal-body -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah_user" class="btn btn-primary" id="btnTambahUser">Simpan</button>
                    <button type="submit" name="edit_user" class="btn btn-success" id="btnEditUser" style="display:none;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SWEETALERT -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // DataTable init (pastikan DataTables sudah include)
    if ($.fn.DataTable) {
        $('#dataTable').DataTable();
    }


    // Tombol edit - isi modal dengan data
    $('.btn-edit').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var email = $(this).data('email');
        var role = $(this).data('role');
        var bengkelId = $(this).data('bengkel-id');

        $('#modalUserLabel').text('Edit Pengguna');
        $('#id_user_edit').val(id);
        $('#nama_lengkap').val(nama);
        $('#email_user').val(email);
        $('#role_user').val(role);
        $('#id_bengkel_select').val(bengkelId);

        $('#btnTambahUser').hide();
        $('#btnEditUser').show();
        $('#modalTambahUser').modal('show');
    });

    // Reset modal saat ditutup
    $('#modalTambahUser').on('hidden.bs.modal', function() {
        $('#modalUserLabel').text('Tambah Pengguna Baru');
        $('#id_user_edit').val('');
        $('#nama_lengkap').val('');
        $('#email_user').val('');
        $('#password_user').val('');
        $('#role_user').val('admin_bengkel');
        $('#id_bengkel_select').val($('#id_bengkel_select option:first').val());

        $('#btnTambahUser').show();
        $('#btnEditUser').hide();
    });

    // SweetAlert notifikasi berdasarkan param 'pesan'
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');

    if (pesan) {
        let title = '', text = '', icon = 'info';
        switch (pesan) {
            case 'sukses_tambah':
                title = 'Berhasil!';
                text = 'Pengguna baru berhasil ditambahkan.';
                icon = 'success';
                break;
            case 'gagal_tambah':
                title = 'Gagal!';
                text = 'Terjadi kesalahan saat menambahkan pengguna.';
                icon = 'error';
                break;
            case 'email_sudah_ada':
                title = 'Peringatan!';
                text = 'Email sudah terdaftar. Gunakan email lain.';
                icon = 'warning';
                break;
            case 'input_tidak_lengkap':
                title = 'Peringatan!';
                text = 'Mohon isi semua data yang diperlukan.';
                icon = 'warning';
                break;
            case 'sukses_edit':
                title = 'Berhasil!';
                text = 'Data pengguna berhasil diubah.';
                icon = 'success';
                break;
            case 'gagal_edit':
                title = 'Gagal!';
                text = 'Terjadi kesalahan saat mengubah data pengguna.';
                icon = 'error';
                break;
            case 'sukses_hapus':
                title = 'Berhasil!';
                text = 'Pengguna berhasil dihapus.';
                icon = 'success';
                break;
            case 'gagal_hapus':
                title = 'Gagal!';
                text = 'Terjadi kesalahan saat menghapus pengguna.';
                icon = 'error';
                break;
            default:
                title = '';
                text = '';
                icon = 'info';
                break;
        }

        if (title !== '') {
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                confirmButtonText: 'OK'
            }).then(() => {
                // Hapus param pesan dari URL
                const cleanUrl = window.location.origin + window.location.pathname + window.location.search.replace(/(&|\?)?pesan=[^&]*/, '');
                window.history.replaceState({}, document.title, cleanUrl);
            });
        }
    }
});
</script>
