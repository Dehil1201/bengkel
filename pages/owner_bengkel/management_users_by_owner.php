<?php
// Pastikan sesi sudah dimulai dan owner sudah login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk membersihkan dan mengamankan input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Cek peran pengguna
if (get_user_role() !== 'owner_bengkel') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit();
}

// Ambil ID owner yang sedang login
$owner_id = $_SESSION['id_user'];

// ==========================================================
// P R O S E S   A K S I   ( T A M B A H ,   E D I T ,   H A P U S )
// ==========================================================
$pesan_aksi = '';

// --- Logika Tambah Pengguna ---
if (isset($_POST['tambah_user'])) {
    $nama_lengkap = sanitize_input($_POST['nama_lengkap']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password']; 
    $role = sanitize_input($_POST['role']);
    $id_bengkel = sanitize_input($_POST['id_bengkel']);

    if (empty($nama_lengkap) || empty($email) || empty($password) || empty($role) || empty($id_bengkel)) {
        $pesan_aksi = "input_tidak_lengkap";
    } else {
        $cek_email = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
        if (mysqli_num_rows($cek_email) > 0) {
            $pesan_aksi = "email_sudah_ada";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (nama_lengkap, email, password, role, bengkel_id) VALUES ('$nama_lengkap', '$email', '$password_hash', '$role', '$id_bengkel')";
            if (mysqli_query($conn, $query)) {
                $pesan_aksi = "sukses_tambah";
            } else {
                $pesan_aksi = "gagal_tambah";
            }
        }
    }
    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
} 

// --- Logika Edit Pengguna ---
if (isset($_POST['edit_user'])) {
    $id_user = sanitize_input($_POST['id_user']);
    $nama_lengkap = sanitize_input($_POST['nama_lengkap']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize_input($_POST['role']);
    $id_bengkel = sanitize_input($_POST['id_bengkel']);
    
    $validasi_user = mysqli_query($conn, "SELECT u.id_user FROM users u JOIN bengkels b ON u.bengkel_id = b.id_bengkel WHERE u.id_user = '$id_user' AND b.owner_id = '$owner_id'");
    
    if (mysqli_num_rows($validasi_user) == 0) {
        die("Akses ditolak. Pengguna tidak valid atau tidak di bawah kendali Anda.");
    }
    
    $update_query = "UPDATE users SET nama_lengkap = '$nama_lengkap', email = '$email', role = '$role', bengkel_id = '$id_bengkel'";
    
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_query .= ", password = '$password_hash'";
    }

    $update_query .= " WHERE id_user = '$id_user'";

    if (mysqli_query($conn, $update_query)) {
        $pesan_aksi = "sukses_edit";
    } else {
        $pesan_aksi = "gagal_edit";
    }
    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
} 

// --- Logika Hapus Pengguna ---
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus') {
    $id_user = sanitize_input($_GET['id']);
    
    $validasi_user = mysqli_query($conn, "SELECT u.id_user FROM users u JOIN bengkels b ON u.bengkel_id = b.id_bengkel WHERE u.id_user = '$id_user' AND b.owner_id = '$owner_id'");
    
    if (mysqli_num_rows($validasi_user) == 0) {
        die("Akses ditolak. Pengguna tidak valid atau tidak di bawah kendali Anda.");
    }

    $query = "DELETE FROM users WHERE id_user = '$id_user'";

    if (mysqli_query($conn, $query)) {
        $pesan_aksi = "sukses_hapus";
    } else {
        $pesan_aksi = "gagal_hapus";
    }
    header("Location: index.php?page=management_users_by_owner&pesan=" . $pesan_aksi);
    exit();
}

// ==========================================================
// T A M P I L A N   H A L A M A N
// ==========================================================

// Ambil daftar bengkel yang dimiliki oleh owner
$query_bengkels = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE owner_id = '$owner_id' ORDER BY nama_bengkel ASC");
$bengkels = [];
while ($row = mysqli_fetch_assoc($query_bengkels)) {
    $bengkels[] = $row;
}

// Tentukan bengkel yang aktif berdasarkan pilihan user
$selected_bengkel_id = $_GET['id_bengkel'] ?? ($bengkels[0]['id_bengkel'] ?? null);
$selected_bengkel_name = "Semua Bengkel";

if ($selected_bengkel_id) {
    $query_selected_bengkel = mysqli_query($conn, "SELECT nama_bengkel FROM bengkels WHERE id_bengkel = '$selected_bengkel_id'");
    if (mysqli_num_rows($query_selected_bengkel) > 0) {
        $data_selected_bengkel = mysqli_fetch_assoc($query_selected_bengkel);
        $selected_bengkel_name = $data_selected_bengkel['nama_bengkel'];
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Manajemen Pengguna untuk Bengkel: **<?= htmlspecialchars($selected_bengkel_name); ?>**</h3>
                <div class="box-tools pull-right">
                    <a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahUser"><i class="fa fa-plus"></i> Tambah Pengguna</a>
                </div>
            </div>
            <div class="box-body">
                <div class="form-group col-md-4">
                    <label for="filter_bengkel">Pilih Bengkel:</label>
                    <select id="filter_bengkel" class="form-control">
                        <?php foreach ($bengkels as $bengkel): ?>
                            <option value="<?= $bengkel['id_bengkel']; ?>" <?= ($bengkel['id_bengkel'] == $selected_bengkel_id) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($bengkel['nama_bengkel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="clearfix"></div>
                <hr>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $sql_where = "WHERE b.owner_id = '$owner_id' AND u.role != 'owner'";
                            if ($selected_bengkel_id) {
                                $sql_where = "WHERE u.bengkel_id = '$selected_bengkel_id' AND u.role != 'owner' AND b.owner_id = '$owner_id'";
                            }

                            $query_users = mysqli_query($conn, "SELECT u.id_user, u.nama_lengkap, u.email, u.role, u.bengkel_id FROM users u JOIN bengkels b ON u.bengkel_id = b.id_bengkel $sql_where ORDER BY u.role, u.nama_lengkap ASC");

                            if (mysqli_num_rows($query_users) > 0) {
                                while ($data_user = mysqli_fetch_assoc($query_users)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_user['nama_lengkap']); ?></td>
                                        <td><?= htmlspecialchars($data_user['email']); ?></td>
                                        <td class="text-capitalize"><?= str_replace('_', ' ', $data_user['role']); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-warning btn-xs btn-edit" data-id="<?= $data_user['id_user']; ?>" data-nama="<?= htmlspecialchars($data_user['nama_lengkap']); ?>" data-email="<?= htmlspecialchars($data_user['email']); ?>" data-role="<?= htmlspecialchars($data_user['role']); ?>" data-bengkel-id="<?= htmlspecialchars($data_user['bengkel_id']); ?>">
                                                <i class="fa fa-pencil"></i> Edit
                                            </a>
                                            <a href="?page=management_users_by_owner&aksi=hapus&id=<?= $data_user['id_user']; ?>" onclick="return confirm('Yakin ingin menghapus pengguna ini?')" class="btn btn-danger btn-xs">
                                                <i class="fa fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>Tidak ada data pengguna.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="modalTambahUser" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Pengguna Baru</h4>
            </div>
            <form action="?page=management_users_by_owner" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_user" id="id_user_edit">

                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password (kosongkan jika tidak ingin diubah)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    <div class="form-group">
                        <label for="role">Hak Akses</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="admin_bengkel">Admin</option>
                            <option value="teknisi">Teknisi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_bengkel">Pilih Bengkel</label>
                        <select class="form-control" id="id_bengkel" name="id_bengkel" required>
                            <?php foreach ($bengkels as $bengkel): ?>
                                <option value="<?= $bengkel['id_bengkel']; ?>">
                                    <?= htmlspecialchars($bengkel['nama_bengkel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah_user" class="btn btn-primary">Simpan</button>
                    <button type="submit" name="edit_user" class="btn btn-success" style="display: none;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    $('#dataTable').DataTable();

    // Event saat dropdown filter bengkel diubah
    $('#filter_bengkel').on('change', function() {
        var bengkelId = $(this).val();
        window.location.href = '?page=management_users_by_owner&id_bengkel=' + bengkelId;
    });

    // Logika untuk menampilkan modal edit
    $('.btn-edit').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var email = $(this).data('email');
        var role = $(this).data('role');
        var bengkelId = $(this).data('bengkel-id');

        $('#myModalLabel').text('Edit Pengguna');
        $('#id_user_edit').val(id);
        $('#nama_lengkap').val(nama);
        $('#email').val(email);
        $('#role').val(role);
        $('#id_bengkel').val(bengkelId);

        $('button[name="tambah_user"]').hide();
        $('button[name="edit_user"]').show();
        $('#modalTambahUser').modal('show');
    });

    // Logika reset form modal saat ditutup
    $('#modalTambahUser').on('hidden.bs.modal', function() {
        $('#myModalLabel').text('Tambah Pengguna Baru');
        $('button[name="tambah_user"]').show();
        $('button[name="edit_user"]').hide();
        $('#id_user_edit').val('');
        $(this).find('form')[0].reset();
    });

    // ===============================================
    // Logika untuk menampilkan SweetAlert
    // ===============================================
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');

    if (pesan) {
        let title, text, icon;
        switch(pesan) {
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
                return; // Jangan tampilkan apa-apa jika pesan tidak dikenali
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            confirmButtonText: 'OK'
        }).then(() => {
            // Hapus parameter 'pesan' dari URL setelah alert ditutup
            history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/&?pesan=[^&]*/, ''));
        });
    }
});
</script>