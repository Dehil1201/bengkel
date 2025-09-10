<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php

// Cek hak akses: hanya admin yang bisa mengakses halaman ini
if (get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit(); // Hentikan eksekusi script
}

// Logika Tambah/Edit/Hapus Pengguna
// Pastikan untuk selalu menambahkan session_start() di awal file jika ini adalah file standalone.
// Karena di-include oleh index.php, session seharusnya sudah aktif.

// --- Logika Hapus Pengguna ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

    // Pastikan tidak menghapus akun yang sedang login
    $check_user_sql = mysqli_query($conn, "SELECT username FROM users WHERE user_id = '$user_id_to_delete'");
    $user_to_delete_data = mysqli_fetch_assoc($check_user_sql);

    if ($user_to_delete_data['username'] === $_SESSION['username']) {
        echo "<script>alert('Anda tidak bisa menghapus akun Anda sendiri!');window.location.href='?page=users_data';</script>";
    } else {
        $delete_sql = mysqli_query($conn, "DELETE FROM users WHERE user_id = '$user_id_to_delete'");
        if ($delete_sql) {
            echo "<script>alert('Data pengguna berhasil dihapus!');window.location.href='?page=users_data';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data pengguna: " . mysqli_error($conn) . "');window.location.href='?page=users_data';</script>";
        }
    }
}

// --- Logika Tambah/Edit Pengguna (Form Processing) ---
if (isset($_POST['submit_user'])) {
    $user_id_form = mysqli_real_escape_string($conn, $_POST['user_id'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']); // Menggunakan nama_lengkap
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password']; // Password bisa kosong jika edit dan tidak diubah

    if (empty($user_id_form)) { // Mode Tambah
        // Cek duplikasi username
        $check_username = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            echo "<div class='alert alert-danger'>Username sudah ada! Mohon gunakan username lain.</div>";
        } else {
            if (empty($password)) {
                echo "<div class='alert alert-danger'>Password harus diisi untuk pengguna baru!</div>";
            } else {
                $hashed_password = md5($password); // Enkripsi password dengan MD5
                $insert_sql = mysqli_query($conn, "INSERT INTO users (username, password, nama_lengkap, role) VALUES ('$username', '$hashed_password', '$nama_lengkap', '$role')");
                if ($insert_sql) {
                    echo "<script>alert('Pengguna baru berhasil ditambahkan!');window.location.href='?page=users_data';</script>";
                } else {
                    echo "<div class='alert alert-danger'>Gagal menambahkan pengguna: " . mysqli_error($conn) . "</div>";
                }
            }
        }
    } else { // Mode Edit
        // Cek duplikasi username, kecuali untuk username sendiri
        $check_username = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username' AND user_id != '$user_id_form'");
        if (mysqli_num_rows($check_username) > 0) {
            echo "<div class='alert alert-danger'>Username sudah digunakan oleh pengguna lain!</div>";
        } else {
            $update_password_clause = "";
            if (!empty($password)) { // Hanya update password jika diisi
                $hashed_password = md5($password);
                $update_password_clause = ", password = '$hashed_password'";
            }
            // Menggunakan nama_lengkap di UPDATE
            $update_sql = mysqli_query($conn, "UPDATE users SET username = '$username', nama_lengkap = '$nama_lengkap', role = '$role' $update_password_clause WHERE user_id = '$user_id_form'");
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
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Peran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        // Mengambil nama_lengkap dan user_id
                        $sql_users = mysqli_query($conn, "SELECT user_id, username, nama_lengkap, role FROM users ORDER BY role, nama_lengkap ASC");
                        while ($data = mysqli_fetch_assoc($sql_users)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['username']); ?></td>
                                <td><?= htmlspecialchars($data['nama_lengkap']); ?></td>
                                <td><?= ucfirst(htmlspecialchars($data['role'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#userModal"
                                        data-action="edit"
                                        data-id="<?= $data['user_id']; ?>"
                                        data-username="<?= htmlspecialchars($data['username']); ?>"
                                        data-nama_lengkap="<?= htmlspecialchars($data['nama_lengkap']); ?>"
                                        data-role="<?= htmlspecialchars($data['role']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=users_data&action=delete&id=<?= $data['user_id']; ?>" onclick="return confirm('Yakin ingin menghapus pengguna ini?')" class="btn btn-danger btn-xs" title="Hapus">
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
                    <input type="hidden" name="user_id" id="user_id">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" id="username_modal" name="username" required>
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
                            <option value="admin">Admin</option>
                            <option value="guru">Guru</option>
                            <option value="siswa">Siswa</option>
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
        "scrollX": true // Tetap gunakan scrollX jika ada banyak kolom
    });

    // Event listener saat modal ditampilkan
    $('#userModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Tombol yang memicu modal
        var action = button.data('action');  // Ambil nilai dari data-action (add/edit)
        var modal = $(this);

        // Reset form setiap kali modal dibuka
        $('#userForm')[0].reset();
        $('#user_id').val(''); // Kosongkan ID
        $('#password_modal').attr('required', false); // Default: password tidak wajib (untuk edit)
        $('#passwordHelpText').text(''); // Kosongkan helper text

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Pengguna Baru');
            $('#password_modal').attr('required', true); // Password wajib untuk tambah
            $('#passwordHelpText').text(' (Wajib diisi)');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Pengguna');
            $('#password_modal').attr('required', false); // Password tidak wajib untuk edit
            $('#passwordHelpText').text(' (Kosongkan jika tidak ingin mengubah password)');

            // Ambil data dari tombol edit
            var user_id = button.data('id');
            var username = button.data('username');
            var nama_lengkap = button.data('nama_lengkap');
            var role = button.data('role');

            // Isi form modal dengan data yang akan diedit
            $('#user_id').val(user_id);
            $('#username_modal').val(username);
            $('#nama_lengkap_modal').val(nama_lengkap);
            $('#role_modal').val(role);
        }
    });
});
</script>