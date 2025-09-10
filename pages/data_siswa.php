<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php
// Perhatian: password_hash() diganti dengan md5() sesuai permintaan. Sangat tidak disarankan untuk produksi.

// Cek hak akses: hanya admin yang bisa mengakses halaman ini
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit(); // Hentikan eksekusi script
} elseif (!function_exists('get_user_role')) {
        echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
        exit();
}

// Ambil data untuk dropdown Kelas
$query_kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$kelas_options = [];
while ($row = mysqli_fetch_assoc($query_kelas)) {
        $kelas_options[$row['id_kelas']] = $row['nama_kelas'];
}

// Ambil data untuk dropdown Tahun Ajaran
$query_tahun_ajaran = mysqli_query($conn, "SELECT id_tahun_ajaran, nama_tahun_ajaran FROM tahun_ajaran ORDER BY nama_tahun_ajaran DESC");
$tahun_ajaran_options = [];
while ($row = mysqli_fetch_assoc($query_tahun_ajaran)) {
        $tahun_ajaran_options[$row['id_tahun_ajaran']] = $row['nama_tahun_ajaran'];
}

// Logika Tambah/Edit/Hapus Siswa

// --- Logika Hapus Siswa ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $siswa_id_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

        // Ambil user_id terkait dari tabel Siswa
        $get_user_id_sql = mysqli_query($conn, "SELECT user_id FROM Siswa WHERE siswa_id = '$siswa_id_to_delete'");
        $siswa_data = mysqli_fetch_assoc($get_user_id_sql);
        $user_id_to_delete = $siswa_data['user_id'] ?? null;

        if ($user_id_to_delete) {
                // Hapus dari tabel Siswa (ON DELETE CASCADE pada foreign key dari Siswa ke Users akan menghapus data User juga)
                // Jika tidak ada ON DELETE CASCADE, hapus user dulu baru siswa.
                // Untuk amannya, kita hapus Siswa dulu, lalu User.
                $delete_siswa_sql = mysqli_query($conn, "DELETE FROM Siswa WHERE siswa_id = '$siswa_id_to_delete'");

                if ($delete_siswa_sql) {
                        $delete_user_sql = mysqli_query($conn, "DELETE FROM Users WHERE user_id = '$user_id_to_delete'");
                        if ($delete_user_sql) {
                                echo "<script>alert('Data siswa dan akun pengguna berhasil dihapus!');window.location.href='?page=data_siswa';</script>";
                        } else {
                                echo "<script>alert('Gagal menghapus akun pengguna: " . mysqli_error($conn) . "');window.location.href='?page=data_siswa';</script>";
                        }
                } else {
                        echo "<script>alert('Gagal menghapus data siswa: " . mysqli_error($conn) . "');window.location.href='?page=data_siswa';</script>";
                }
        } else {
                echo "<script>alert('Siswa tidak ditemukan atau user_id tidak valid!');window.location.href='?page=data_siswa';</script>";
        }
}

// --- Logika Tambah/Edit Siswa (Form Processing) ---
if (isset($_POST['submit_siswa'])) {
        $siswa_id_form = mysqli_real_escape_string($conn, $_POST['siswa_id'] ?? '');
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = $_POST['password']; // Password akan di-hash
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $nisn = mysqli_real_escape_string($conn, $_POST['nisn']);
        $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
        $id_tahun_ajaran = mysqli_real_escape_string($conn, $_POST['id_tahun_ajaran']); // Kolom baru
        $status = mysqli_real_escape_string($conn, $_POST['status']); // Kolom baru

        if (empty($siswa_id_form)) { // Mode Tambah Siswa Baru
                // Validasi: Username, Email, NISN harus unik
                $check_username = mysqli_query($conn, "SELECT user_id FROM Users WHERE username = '$username'");
                $check_email = mysqli_query($conn, "SELECT user_id FROM Users WHERE email = '$email'");
                $check_nisn = mysqli_query($conn, "SELECT siswa_id FROM Siswa WHERE nisn = '$nisn'");

                if (mysqli_num_rows($check_username) > 0) {
                        echo "<div class='alert alert-danger'>Username sudah digunakan!</div>";
                } elseif (mysqli_num_rows($check_email) > 0) {
                        echo "<div class='alert alert-danger'>Email sudah digunakan!</div>";
                } elseif (mysqli_num_rows($check_nisn) > 0) {
                        echo "<div class='alert alert-danger'>NISN sudah terdaftar!</div>";
                } elseif (empty($password)) {
                        echo "<div class='alert alert-danger'>Password wajib diisi untuk siswa baru!</div>";
                } else {
                        // Ganti ke md5()
                        $hashed_password = md5($password);

                        // 1. Insert ke tabel Users
                        $insert_user_sql = mysqli_query($conn, "INSERT INTO Users (username, password, role, nama_lengkap, email) VALUES ('$username', '$hashed_password', 'siswa', '$nama_lengkap', '$email')");

                        if ($insert_user_sql) {
                                $new_user_id = mysqli_insert_id($conn); // Ambil ID user yang baru saja di-insert

                                // 2. Insert ke tabel Siswa (dengan id_tahun_ajaran dan status)
                                $insert_siswa_sql = mysqli_query($conn, "INSERT INTO Siswa (user_id, nisn, kelas, id_tahun_ajaran, status) VALUES ('$new_user_id', '$nisn', '$id_kelas', '$id_tahun_ajaran', '$status')");

                                if ($insert_siswa_sql) {
                                        echo "<script>alert('Data siswa dan akun berhasil ditambahkan!');window.location.href='?page=data_siswa';</script>";
                                } else {
                                        // Jika insert Siswa gagal, hapus user yang sudah terlanjur dibuat
                                        mysqli_query($conn, "DELETE FROM Users WHERE user_id = '$new_user_id'");
                                        echo "<div class='alert alert-danger'>Gagal menambahkan data siswa: " . mysqli_error($conn) . "</div>";
                                }
                        } else {
                                echo "<div class='alert alert-danger'>Gagal membuat akun pengguna: " . mysqli_error($conn) . "</div>";
                        }
                }
        } else { // Mode Edit Siswa
                // Ambil user_id terkait dari siswa_id
                $get_user_id_for_edit_sql = mysqli_query($conn, "SELECT user_id FROM Siswa WHERE siswa_id = '$siswa_id_form'");
                $siswa_edit_data = mysqli_fetch_assoc($get_user_id_for_edit_sql);
                $user_id_to_edit = $siswa_edit_data['user_id'] ?? null;

                if (!$user_id_to_edit) {
                        echo "<div class='alert alert-danger'>Siswa tidak ditemukan untuk diedit!</div>";
                        exit();
                }

                // Validasi: Username, Email, NISN harus unik (kecuali untuk dirinya sendiri)
                $check_username = mysqli_query($conn, "SELECT user_id FROM Users WHERE username = '$username' AND user_id != '$user_id_to_edit'");
                $check_email = mysqli_query($conn, "SELECT user_id FROM Users WHERE email = '$email' AND user_id != '$user_id_to_edit'");
                $check_nisn = mysqli_query($conn, "SELECT siswa_id FROM Siswa WHERE nisn = '$nisn' AND siswa_id != '$siswa_id_form'");

                if (mysqli_num_rows($check_username) > 0) {
                        echo "<div class='alert alert-danger'>Username sudah digunakan oleh pengguna lain!</div>";
                } elseif (mysqli_num_rows($check_email) > 0) {
                        echo "<div class='alert alert-danger'>Email sudah digunakan oleh pengguna lain!</div>";
                } elseif (mysqli_num_rows($check_nisn) > 0) {
                        echo "<div class='alert alert-danger'>NISN sudah terdaftar pada siswa lain!</div>";
                } else {
                        // Update password jika diisi
                        $password_update_clause = "";
                        if (!empty($password)) {
                                // Ganti ke md5()
                                $hashed_password = md5($password);
                                $password_update_clause = ", password = '$hashed_password'";
                        }

                        // 1. Update tabel Users
                        $update_user_sql = mysqli_query($conn, "UPDATE Users SET username = '$username', nama_lengkap = '$nama_lengkap', email = '$email' $password_update_clause WHERE user_id = '$user_id_to_edit'");

                        // 2. Update tabel Siswa (dengan id_tahun_ajaran dan status)
                        $update_siswa_sql = mysqli_query($conn, "UPDATE Siswa SET nisn = '$nisn', kelas = '$id_kelas', id_tahun_ajaran = '$id_tahun_ajaran', status = '$status' WHERE siswa_id = '$siswa_id_form'");

                        if ($update_user_sql && $update_siswa_sql) {
                                echo "<script>alert('Data siswa dan akun berhasil diperbarui!');window.location.href='?page=data_siswa';</script>";
                        } else {
                                echo "<div class='alert alert-danger'>Gagal memperbarui data siswa: " . mysqli_error($conn) . "</div>";
                        }
                }
        }
}
?>

<div class="row">
        <div class="col-xs-12">
                <div class="box box-info">
                        <div class="box-header with-border">
                                <h3 class="box-title">Daftar Siswa</h3>
                                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#siswaModal" data-action="add">
                                        <i class="fa fa-plus"></i> Tambah Siswa Baru
                                </button>
                        </div>
                        <div class="box-body table-responsive">
                                <table id="data" class="table table-bordered table-striped">
                                        <thead>
                                                <tr>
                                                        <th>No.</th>
                                                        <th>NISN</th>
                                                        <th>Nama Lengkap</th>
                                                        <th>Kelas</th>
                                                        <th>Tahun Ajaran</th> <th>Status</th>          <th>Username</th>
                                                        <th>Email</th>
                                                        <th>Aksi</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <?php
                                                $no = 1;
                                                // Join tabel Siswa dengan Users, Kelas, dan Tahun Ajaran untuk menampilkan data lengkap
                                                $sql_siswa = mysqli_query($conn, "SELECT s.*, u.username, u.nama_lengkap, u.email, k.nama_kelas, ta.nama_tahun_ajaran
                                                                FROM Siswa s
                                                                JOIN Users u ON s.user_id = u.user_id
                                                                LEFT JOIN kelas k ON s.kelas = k.id_kelas
                                                                LEFT JOIN tahun_ajaran ta ON s.id_tahun_ajaran = ta.id_tahun_ajaran
                                                                ORDER BY s.status ASC, u.nama_lengkap ASC");
                                                
                                                while ($data = mysqli_fetch_assoc($sql_siswa)) {
                                                ?>
                                                        <tr>
                                                                <td><?= $no++; ?></td>
                                                                <td><?= htmlspecialchars($data['nisn']); ?></td>
                                                                <td><?= htmlspecialchars($data['nama_lengkap']); ?></td>
                                                                <td><?= htmlspecialchars($data['nama_kelas'] ?? '-'); ?></td> <td><?= htmlspecialchars($data['nama_tahun_ajaran'] ?? '-'); ?></td> <td>
                                                                        <?php
                                                                        $badge_class = 'default';
                                                                        switch ($data['status']) {
                                                                                case 'aktif': $badge_class = 'success'; break;
                                                                                case 'lulus': $badge_class = 'info'; break;
                                                                                case 'pindah': $badge_class = 'warning'; break;
                                                                        }
                                                                        echo '<span class="label label-' . $badge_class . '">' . ucfirst(htmlspecialchars($data['status'])) . '</span>';
                                                                        ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($data['username']); ?></td>
                                                                <td><?= htmlspecialchars($data['email']); ?></td>
                                                                <td>
                                                                        <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#siswaModal"
                                                                                data-action="edit"
                                                                                data-id="<?= $data['siswa_id']; ?>"
                                                                                data-username="<?= htmlspecialchars($data['username']); ?>"
                                                                                data-nama_lengkap="<?= htmlspecialchars($data['nama_lengkap']); ?>"
                                                                                data-email="<?= htmlspecialchars($data['email']); ?>"
                                                                                data-nisn="<?= htmlspecialchars($data['nisn']); ?>"
                                                                                data-id_kelas="<?= htmlspecialchars($data['kelas']); ?>"
                                                                                data-id_tahun_ajaran="<?= htmlspecialchars($data['id_tahun_ajaran']); ?>"
                                                                                data-status="<?= htmlspecialchars($data['status']); ?>"
                                                                                title="Edit">
                                                                                <i class="fa fa-edit"></i>
                                                                        </button>
                                                                        <a href="?page=data_siswa&action=delete&id=<?= $data['siswa_id']; ?>" onclick="return confirm('Yakin ingin menghapus siswa ini? Ini akan menghapus akun pengguna juga!')" class="btn btn-danger btn-xs" title="Hapus">
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

<div class="modal fade" id="siswaModal" tabindex="-1" role="dialog" aria-labelledby="siswaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
                <div class="modal-content">
                        <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                </button>
                                <h4 class="modal-title" id="siswaModalLabel">Form Data Siswa</h4>
                        </div>
                        <form id="siswaForm" method="POST" action="?page=data_siswa">
                                <div class="modal-body">
                                        <input type="hidden" name="siswa_id" id="siswa_id_modal">
                                        <div class="form-group">
                                                <label for="nama_lengkap">Nama Lengkap:</label>
                                                <input type="text" class="form-control" id="nama_lengkap_modal" name="nama_lengkap" required>
                                        </div>
                                        <div class="form-group">
                                                <label for="nisn">NISN:</label>
                                                <input type="text" class="form-control" id="nisn_modal" name="nisn" required>
                                        </div>
                                        <div class="form-group">
                                                <label for="id_kelas">Kelas:</label>
                                                <select class="form-control" id="id_kelas_modal" name="id_kelas" required>
                                                        <option value="">-- Pilih Kelas --</option>
                                                        <?php foreach ($kelas_options as $id => $nama): ?>
                                                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                                                        <?php endforeach; ?>
                                                </select>
                                        </div>
                                        <div class="form-group">
                                                <label for="id_tahun_ajaran">Tahun Ajaran:</label>
                                                <select class="form-control" id="id_tahun_ajaran_modal" name="id_tahun_ajaran" required>
                                                        <option value="">-- Pilih Tahun Ajaran --</option>
                                                        <?php foreach ($tahun_ajaran_options as $id => $nama): ?>
                                                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                                                        <?php endforeach; ?>
                                                </select>
                                        </div>
                                        <div class="form-group">
                                                <label for="status">Status Siswa:</label>
                                                <select class="form-control" id="status_modal" name="status" required>
                                                        <option value="aktif">Aktif</option>
                                                        <option value="lulus">Lulus (Alumni)</option>
                                                        <option value="pindah">Pindah</option>
                                                </select>
                                        </div>
                                        <hr>
                                        <h4>Informasi Akun Login</h4>
                                        <div class="form-group">
                                                <label for="username">Username:</label>
                                                <input type="text" class="form-control" id="username_modal" name="username" required>
                                        </div>
                                        <div class="form-group">
                                                <label for="email">Email:</label>
                                                <input type="email" class="form-control" id="email_modal" name="email" required>
                                        </div>
                                        <div class="form-group">
                                                <label for="password">Password: <small id="passwordHelpText"></small></label>
                                                <input type="password" class="form-control" id="password_modal" name="password">
                                        </div>
                                </div>
                                <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" name="submit_siswa" class="btn btn-primary">Simpan</button>
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
        $('#siswaModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget); // Tombol yang memicu modal
                var action = button.data('action');  // Ambil nilai dari data-action (add/edit)
                var modal = $(this);

                // Reset form setiap kali modal dibuka
                $('#siswaForm')[0].reset();
                $('#siswa_id_modal').val(''); // Kosongkan ID siswa
                $('#password_modal').attr('required', false); // Default: password tidak wajib (untuk edit)
                $('#passwordHelpText').text(''); // Kosongkan helper text

                if (action === 'add') {
                        modal.find('.modal-title').text('Tambah Siswa Baru');
                        $('#password_modal').attr('required', true); // Password wajib untuk tambah
                        $('#passwordHelpText').text(' (Wajib diisi)');
                        // Aktifkan input username, email, nisn untuk mode tambah
                        $('#username_modal').prop('readonly', false);
                        $('#email_modal').prop('readonly', false);
                        $('#nisn_modal').prop('readonly', false);
                        // Set default status ke 'aktif'
                        $('#status_modal').val('aktif');
                } else if (action === 'edit') {
                        modal.find('.modal-title').text('Edit Data Siswa');
                        $('#password_modal').attr('required', false); // Password tidak wajib untuk edit
                        $('#passwordHelpText').text(' (Kosongkan jika tidak ingin mengubah password)');
                        
                        // Ambil data dari tombol edit
                        var siswa_id = button.data('id');
                        var username = button.data('username');
                        var nama_lengkap = button.data('nama_lengkap');
                        var email = button.data('email');
                        var nisn = button.data('nisn');
                        var id_kelas = button.data('id_kelas');
                        var id_tahun_ajaran = button.data('id_tahun_ajaran'); // Data baru
                        var status = button.data('status'); // Data baru

                        // Isi form modal dengan data yang akan diedit
                        $('#siswa_id_modal').val(siswa_id);
                        $('#username_modal').val(username);
                        $('#nama_lengkap_modal').val(nama_lengkap);
                        $('#email_modal').val(email);
                        $('#nisn_modal').val(nisn);
                        $('#id_kelas_modal').val(id_kelas);
                        $('#id_tahun_ajaran_modal').val(id_tahun_ajaran); // Set nilai dropdown TA
                        $('#status_modal').val(status); // Set nilai dropdown status

                        // Nonaktifkan input username, email, nisn agar tidak diubah saat edit
                        // Ini untuk mencegah perubahan pada kolom UNIQUE yang bisa menyebabkan error duplikasi
                        // Jika ingin bisa diubah, perlu penanganan validasi yang lebih kompleks
                        $('#username_modal').prop('readonly', true);
                        $('#email_modal').prop('readonly', true);
                        $('#nisn_modal').prop('readonly', true);
                }
        });
});
</script>