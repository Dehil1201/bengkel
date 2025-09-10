<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php

// Cek hak akses: hanya admin yang bisa mengakses halaman ini
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit();
} elseif (!function_exists('get_user_role')) {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil data unik untuk dropdown Gelar (dari data guru yang ada)
$query_gelar_unique = mysqli_query($conn, "SELECT DISTINCT gelar FROM guru WHERE gelar IS NOT NULL AND gelar != '' ORDER BY gelar ASC");
$gelar_options = [];
while ($row = mysqli_fetch_assoc($query_gelar_unique)) {
    $gelar_options[] = $row['gelar'];
}

// Logika Tambah/Edit/Hapus Guru
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $guru_id_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

        // Ambil user_id dari guru yang akan dihapus
        $query_user_id = mysqli_query($conn, "SELECT user_id FROM Guru WHERE guru_id = '$guru_id_to_delete'");
        $data_user_id = mysqli_fetch_assoc($query_user_id);
        $user_id_to_delete = $data_user_id['user_id'] ?? null;

        if ($user_id_to_delete) {
            // Hapus dari tabel users. Karena ada ON DELETE CASCADE di guru (yang terhubung ke users),
            // baris di tabel guru juga akan otomatis terhapus.
            $delete_sql = mysqli_query($conn, "DELETE FROM users WHERE user_id = '$user_id_to_delete'");
            if ($delete_sql) {
                echo "<script>alert('Data Guru berhasil dihapus!');window.location.href='?page=data_guru';</script>";
            } else {
                echo "<script>alert('Gagal menghapus data guru: " . mysqli_error($conn) . "');window.location.href='?page=data_guru';</script>";
            }
        } else {
            echo "<script>alert('Guru tidak ditemukan atau tidak memiliki user_id terkait.');window.location.href='?page=data_guru';</script>";
        }
    }
}

if (isset($_POST['submit_guru'])) {
    $guru_id_form = mysqli_real_escape_string($conn, $_POST['guru_id'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $gelar = mysqli_real_escape_string($conn, $_POST['gelar']);
    $status_guru = mysqli_real_escape_string($conn, $_POST['status_guru']); // New field for status

    // Hash password jika ada atau jika ini user baru
    if (!empty($password)) {
        // MENGGANTI password_hash() menjadi md5()
        $hashed_password = md5($password);
    }

    if (empty($guru_id_form)) { // Mode Tambah Guru Baru
        // 1. Cek duplikasi username atau email
        $check_duplicate_user = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE username = '$username' OR email = '$email'");
        $row_duplicate_user = mysqli_fetch_row($check_duplicate_user);
        
        // 2. Cek duplikasi NIP (jika NIP tidak kosong)
        $check_duplicate_nip = 0;
        if (!empty($nip)) {
            $query_check_nip = mysqli_query($conn, "SELECT COUNT(*) FROM guru WHERE nip = '$nip'");
            $row_duplicate_nip = mysqli_fetch_row($query_check_nip);
            $check_duplicate_nip = $row_duplicate_nip[0];
        }

        if ($row_duplicate_user[0] > 0) {
            echo "<div class='alert alert-danger'>Username atau Email sudah terdaftar.</div>";
        } elseif (!empty($nip) && $check_duplicate_nip > 0) {
            echo "<div class='alert alert-danger'>NIP sudah terdaftar pada guru lain.</div>";
        } elseif (empty($password)) {
            echo "<div class='alert alert-danger'>Password harus diisi untuk user baru.</div>";
        } else {
            // 3. Insert ke tabel users
            $insert_user_sql = mysqli_query($conn, "INSERT INTO users (username, password, role, nama_lengkap, email) 
                                                     VALUES ('$username', '$hashed_password', 'guru', '$nama_lengkap', '$email')");
            if ($insert_user_sql) {
                $new_user_id = mysqli_insert_id($conn); // Dapatkan user_id yang baru dibuat

                // 4. Insert ke tabel guru
                $insert_guru_sql = mysqli_query($conn, "INSERT INTO guru (user_id, nip, gelar, status) 
                                                     VALUES ('$new_user_id', " . (empty($nip) ? "NULL" : "'$nip'") . ", " . (empty($gelar) ? "NULL" : "'$gelar'") . ", '$status_guru')");
                if ($insert_guru_sql) {
                    echo "<script>alert('Guru baru berhasil ditambahkan!');window.location.href='?page=data_guru';</script>";
                } else {
                    // Jika insert guru gagal, hapus user yang sudah dibuat
                    mysqli_query($conn, "DELETE FROM users WHERE user_id = '$new_user_id'");
                    echo "<div class='alert alert-danger'>Gagal menambahkan data guru: " . mysqli_error($conn) . "</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Gagal membuat akun user untuk guru: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit Guru
        // Ambil user_id yang terkait dengan guru_id ini
        $query_get_user_id = mysqli_query($conn, "SELECT user_id FROM Guru WHERE guru_id = '$guru_id_form'");
        $data_current_user = mysqli_fetch_assoc($query_get_user_id);
        $current_user_id = $data_current_user['user_id'] ?? null;

        if ($current_user_id) {
            // Update tabel users
            $update_user_sql_part = "username = '$username', nama_lengkap = '$nama_lengkap', email = '$email'";
            if (!empty($hashed_password)) { // Jika password diisi, update password
                $update_user_sql_part .= ", password = '$hashed_password'";
            }
            
            // Cek duplikasi username atau email (kecuali untuk user_id ini sendiri)
            $check_duplicate_user = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE (username = '$username' OR email = '$email') AND user_id != '$current_user_id'");
            $row_duplicate_user = mysqli_fetch_row($check_duplicate_user);

            // Cek duplikasi NIP (kecuali untuk guru_id ini sendiri, jika NIP tidak kosong)
            $check_duplicate_nip = 0;
            if (!empty($nip)) {
                $query_check_nip_edit = mysqli_query($conn, "SELECT COUNT(*) FROM guru WHERE nip = '$nip' AND guru_id != '$guru_id_form'");
                $row_duplicate_nip_edit = mysqli_fetch_row($query_check_nip_edit);
                $check_duplicate_nip = $row_duplicate_nip_edit[0];
            }
            
            if ($row_duplicate_user[0] > 0) {
                 echo "<div class='alert alert-danger'>Username atau Email sudah terdaftar pada user lain.</div>";
            } elseif (!empty($nip) && $check_duplicate_nip > 0) {
                 echo "<div class='alert alert-danger'>NIP sudah terdaftar pada guru lain.</div>";
            } else {
                $update_user_sql = mysqli_query($conn, "UPDATE users SET $update_user_sql_part WHERE user_id = '$current_user_id'");

                // Update tabel guru
                $update_guru_sql = mysqli_query($conn, "UPDATE guru SET nip = " . (empty($nip) ? "NULL" : "'$nip'") . ", gelar = " . (empty($gelar) ? "NULL" : "'$gelar'") . ", status = '$status_guru' WHERE guru_id = '$guru_id_form'");

                if ($update_user_sql && $update_guru_sql) {
                    echo "<script>alert('Data Guru berhasil diperbarui!');window.location.href='?page=data_guru';</script>";
                } else {
                    echo "<div class='alert alert-danger'>Gagal memperbarui data guru: " . mysqli_error($conn) . "</div>";
                }
            }
        } else {
            echo "<div class='alert alert-danger'>User ID terkait tidak ditemukan untuk guru ini.</div>";
        }
    }
}

// Logika Filter
$filter_gelar = $_GET['filter_gelar'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clause = "WHERE u.role = 'guru'"; // Kondisi awal untuk memastikan hanya guru yang tampil

if (!empty($filter_gelar)) {
    $where_clause .= " AND g.gelar = '" . mysqli_real_escape_string($conn, $filter_gelar) . "'";
}
if (!empty($filter_status)) {
    $where_clause .= " AND g.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Guru</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#guruModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Guru
                </button>
            </div>
            <div class="box-body">
                <form method="GET" action="?page=data_guru" class="form-inline mb-4">
                    <input type="hidden" name="page" value="data_guru">
                    <div class="form-group mr-2">
                        <label for="filter_gelar">Gelar:</label>
                        <select class="form-control input-sm" id="filter_gelar" name="filter_gelar">
                            <option value="">Semua Gelar</option>
                            <?php foreach ($gelar_options as $gelar): ?>
                                <option value="<?= htmlspecialchars($gelar); ?>" <?= ($filter_gelar == $gelar) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($gelar); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="filter_status">Status:</label>
                        <select class="form-control input-sm" id="filter_status" name="filter_status">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($filter_status == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="non-aktif" <?= ($filter_status == 'non-aktif') ? 'selected' : ''; ?>>Non-Aktif</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info btn-sm">Filter</button>
                    <a href="?page=data_guru" class="btn btn-default btn-sm">Reset</a>
                </form>

                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Lengkap</th>
                                <th>NIP</th>
                                <th>Gelar</th>
                                <th>Status</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Join tabel users dan guru untuk mendapatkan semua informasi
                            $sql_guru = "SELECT g.guru_id, g.nip, g.gelar, g.status, u.user_id, u.username, u.nama_lengkap, u.email 
                                         FROM guru g
                                         JOIN users u ON g.user_id = u.user_id
                                         $where_clause
                                         ORDER BY u.nama_lengkap ASC";
                            $result_guru = mysqli_query($conn, $sql_guru);

                            if (mysqli_num_rows($result_guru) > 0) {
                                while ($data = mysqli_fetch_assoc($result_guru)) {
                            ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($data['nama_lengkap']); ?></td>
                                            <td><?= htmlspecialchars($data['nip'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($data['gelar'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars(ucfirst($data['status'])); ?></td>
                                            <td><?= htmlspecialchars($data['username']); ?></td>
                                            <td><?= htmlspecialchars($data['email'] ?? '-'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#guruModal"
                                                        data-action="edit"
                                                        data-guru_id="<?= $data['guru_id']; ?>"
                                                        data-username="<?= htmlspecialchars($data['username']); ?>"
                                                        data-nama_lengkap="<?= htmlspecialchars($data['nama_lengkap']); ?>"
                                                        data-email="<?= htmlspecialchars($data['email']); ?>"
                                                        data-nip="<?= htmlspecialchars($data['nip']); ?>"
                                                        data-gelar="<?= htmlspecialchars($data['gelar']); ?>"
                                                        data-status_guru="<?= htmlspecialchars($data['status']); ?>"
                                                        title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <a href="?page=data_guru&action=delete&id=<?= $data['guru_id']; ?>" onclick="return confirm('Yakin ingin menghapus data guru ini? Semua data terkait (materi, kuis, dll.) juga akan ikut terhapus.')" class="btn btn-danger btn-xs" title="Hapus">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>Tidak ada data guru yang ditemukan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="guruModal" tabindex="-1" role="dialog" aria-labelledby="guruModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="guruModalLabel">Form Guru</h4>
            </div>
            <form id="guruForm" method="POST" action="?page=data_guru">
                <div class="modal-body">
                    <input type="hidden" name="guru_id" id="guru_id_modal">
                    <div class="form-group">
                        <label for="nama_lengkap_modal">Nama Lengkap:</label>
                        <input type="text" class="form-control" id="nama_lengkap_modal" name="nama_lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="username_modal">Username:</label>
                        <input type="text" class="form-control" id="username_modal" name="username" required>
                        <small class="text-muted">Username digunakan untuk login.</small>
                    </div>
                    <div class="form-group">
                        <label for="email_modal">Email:</label>
                        <input type="email" class="form-control" id="email_modal" name="email">
                        <small class="text-muted">Opsional, tapi disarankan.</small>
                    </div>
                    <div class="form-group">
                        <label for="password_modal">Password:</label>
                        <input type="password" class="form-control" id="password_modal" name="password">
                        <small class="text-muted" id="password_help_text">Kosongkan jika tidak ingin mengubah password.</small>
                    </div>
                    <div class="form-group">
                        <label for="nip_modal">NIP:</label>
                        <input type="text" class="form-control" id="nip_modal" name="nip">
                        <small class="text-muted">Nomor Induk Pegawai (opsional).</small>
                    </div>
                    <div class="form-group">
                        <label for="gelar_modal">Gelar:</label>
                        <input type="text" class="form-control" id="gelar_modal" name="gelar">
                        <small class="text-muted">Contoh: S.Pd., M.Kom (opsional).</small>
                    </div>
                    <div class="form-group">
                        <label for="status_guru_modal">Status:</label>
                        <select class="form-control" id="status_guru_modal" name="status_guru" required>
                            <option value="aktif">Aktif</option>
                            <option value="non-aktif">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_guru" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#data').DataTable({
        "paging": true, "lengthChange": true, "searching": true,
        "ordering": true, "info": true, "autoWidth": true, "scrollX": true
    });

    // Logika untuk Modal Tambah/Edit
    $('#guruModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);
        $('#guruForm')[0].reset(); // Reset form
        $('#guru_id_modal').val(''); // Clear hidden ID

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Guru Baru');
            $('#password_modal').prop('required', true); // Password wajib diisi saat tambah
            $('#password_help_text').text('Password wajib diisi.');
            $('#status_guru_modal').val('aktif'); // Default status 'aktif'
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Data Guru');
            var guru_id = button.data('guru_id');
            var username = button.data('username');
            var nama_lengkap = button.data('nama_lengkap');
            var email = button.data('email');
            var nip = button.data('nip');
            var gelar = button.data('gelar');
            var status_guru = button.data('status_guru');

            $('#guru_id_modal').val(guru_id);
            $('#username_modal').val(username);
            $('#nama_lengkap_modal').val(nama_lengkap);
            $('#email_modal').val(email);
            $('#nip_modal').val(nip);
            $('#gelar_modal').val(gelar);
            $('#status_guru_modal').val(status_guru);
            $('#password_modal').val(''); // Kosongkan password saat edit untuk keamanan
            $('#password_modal').prop('required', false); // Password tidak wajib diisi saat edit
            $('#password_help_text').text('Kosongkan jika tidak ingin mengubah password.');
        }
    });
});
</script>