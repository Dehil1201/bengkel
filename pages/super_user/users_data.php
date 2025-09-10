<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php

// Cek hak akses: hanya super_user yang bisa mengakses halaman ini
if (get_user_role() !== 'super_user') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit(); // Hentikan eksekusi script
}

// Logika Tambah/Edit/Hapus Pengguna
// Pastikan untuk selalu menambahkan session_start() di awal file jika ini adalah file standalone.
// Karena di-include oleh index.php, session seharusnya sudah aktif.

// --- Logika Hapus Pengguna ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_user_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

    // Pastikan tidak menghapus akun yang sedang login
    $check_user_sql = mysqli_query($conn, "SELECT email FROM users WHERE id_user = '$id_user_to_delete'");
    $user_to_delete_data = mysqli_fetch_assoc($check_user_sql);

    if ($user_to_delete_data['email'] === $_SESSION['email']) {
        echo "<script>alert('Anda tidak bisa menghapus akun Anda sendiri!');window.location.href='?page=users_data';</script>";
    } else {
        $delete_sql = mysqli_query($conn, "DELETE FROM users WHERE id_user = '$id_user_to_delete'");
        if ($delete_sql) {
            echo "<script>alert('Data pengguna berhasil dihapus!');window.location.href='?page=users_data';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data pengguna: " . mysqli_error($conn) . "');window.location.href='?page=users_data';</script>";
        }
    }
}

// --- Logika Tambah/Edit Pengguna (Form Processing) ---
if (isset($_POST['submit_user'])) {
    $id_user_form = mysqli_real_escape_string($conn, $_POST['id_user'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];

    if (empty($id_user_form)) { // Mode Tambah
        // Cek duplikasi email
        $check_email = mysqli_query($conn, "SELECT id_user FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            echo "<div class='alert alert-danger'>Email sudah ada! Mohon gunakan email lain.</div>";
        } else {
            if (empty($password)) {
                echo "<div class='alert alert-danger'>Password harus diisi untuk pengguna baru!</div>";
            } else {
                // Menggunakan password_hash() untuk mengenkripsi password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_sql = mysqli_query($conn, "INSERT INTO users (email, password, nama_lengkap, role) VALUES ('$email', '$hashed_password', '$nama_lengkap', '$role')");
                if ($insert_sql) {
                    echo "<script>alert('Pengguna baru berhasil ditambahkan!');window.location.href='?page=users_data';</script>";
                } else {
                    echo "<div class='alert alert-danger'>Gagal menambahkan pengguna: " . mysqli_error($conn) . "</div>";
                }
            }
        }
    } else { // Mode Edit
        // Cek duplikasi email, kecuali untuk email sendiri
        $check_email = mysqli_query($conn, "SELECT id_user FROM users WHERE email = '$email' AND id_user != '$id_user_form'");
        if (mysqli_num_rows($check_email) > 0) {
            echo "<div class='alert alert-danger'>Email sudah digunakan oleh pengguna lain!</div>";
        } else {
            $update_password_clause = "";
            if (!empty($password)) { // Hanya update password jika diisi
                // Menggunakan password_hash() untuk mengenkripsi password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_password_clause = ", password = '$hashed_password'";
            }
            $update_sql = mysqli_query($conn, "UPDATE users SET email = '$email', nama_lengkap = '$nama_lengkap', role = '$role' $update_password_clause WHERE id_user = '$id_user_form'");
            if ($update_sql) {
                echo "<script>alert('Data pengguna berhasil diperbarui!');window.location.href='?page=users_data';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui pengguna: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Pengguna</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#userModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Pengguna
                </button>
            </div>
            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Email</th>
                            <th>Nama Lengkap</th>
                            <th>Peran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $sql_users = mysqli_query($conn, "SELECT id_user, email, nama_lengkap, role FROM users ORDER BY role, nama_lengkap ASC");
                        while ($data = mysqli_fetch_assoc($sql_users)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['email']); ?></td>
                                <td><?= htmlspecialchars($data['nama_lengkap']); ?></td>
                                <td><?= ucfirst(htmlspecialchars($data['role'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#userModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_user']; ?>"
                                        data-email="<?= htmlspecialchars($data['email']); ?>"
                                        data-nama_lengkap="<?= htmlspecialchars($data['nama_lengkap']); ?>"
                                        data-role="<?= htmlspecialchars($data['role']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=users_data&action=delete&id=<?= $data['id_user']; ?>" onclick="return confirm('Yakin ingin menghapus pengguna ini?')" class="btn btn-danger btn-xs" title="Hapus">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="userModalLabel">Form Pengguna</h4>
            </div>
            <form id="userForm" method="POST" action="?page=users_data">
                <div class="modal-body">
                    <input type="hidden" name="id_user" id="id_user">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="text" class="form-control" id="email_modal" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password: <small id="passwordHelpText"></small></label>
                        <input type="password" class="form-control" id="password_modal" name="password">
                    </div>
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap:</label>
                        <input type="text" class="form-control" id="nama_lengkap_modal" name="nama_lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Peran (Role):</label>
                        <select class="form-control" id="role_modal" name="role" required>
                            <option value="">-- Pilih Peran --</option>
                            <option value="owner_bengkel">Owner Bengkel</option>
                            <option value="admin_bengkel">Admin Bengkel</option>
                            <option value="teknisi">Teknisi</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_user" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#data').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": true,
        "scrollX": true
    });

    // Event listener saat modal ditampilkan
    $('#userModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);

        $('#userForm')[0].reset();
        $('#id_user').val('');
        $('#password_modal').attr('required', false);
        $('#passwordHelpText').text('');

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Pengguna Baru');
            $('#password_modal').attr('required', true);
            $('#passwordHelpText').text(' (Wajib diisi)');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Pengguna');
            $('#password_modal').attr('required', false);
            $('#passwordHelpText').text(' (Kosongkan jika tidak ingin mengubah password)');

            var id_user = button.data('id');
            var email = button.data('email');
            var nama_lengkap = button.data('nama_lengkap');
            var role = button.data('role');

            $('#id_user').val(id_user);
            $('#email_modal').val(email);
            $('#nama_lengkap_modal').val(nama_lengkap);
            $('#role_modal').val(role);
        }
    });
});
</script>