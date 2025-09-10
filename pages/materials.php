<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan.</div>";
    exit();
}

// Dapatkan user_id dari sesi
$current_user_id = $_SESSION['user_id'] ?? null;
$guru_id_loggedin = null;

// Jika user adalah guru, dapatkan guru_id-nya
if ($user_role === 'guru' && $current_user_id) {
    $query_guru_id = mysqli_query($conn, "SELECT guru_id FROM Guru WHERE user_id = '$current_user_id'");
    if ($row_guru_id = mysqli_fetch_assoc($query_guru_id)) {
        $guru_id_loggedin = $row_guru_id['guru_id'];
    } else {
        echo "<div class='alert alert-danger'>Error: ID Guru tidak ditemukan untuk user ini.</div>";
        exit();
    }
}

// Ambil data untuk dropdown Mapel
$query_mapel = mysqli_query($conn, "SELECT id_mapel, nama_mapel FROM mapel ORDER BY nama_mapel ASC");
$mapel_options = [];
while ($row = mysqli_fetch_assoc($query_mapel)) {
    $mapel_options[$row['id_mapel']] = $row['nama_mapel'];
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

// Ambil data untuk dropdown Jurusan
$query_jurusan = mysqli_query($conn, "SELECT id_jurusan, nama_jurusan FROM Jurusan ORDER BY nama_jurusan ASC");
$jurusan_options = [];
while ($row = mysqli_fetch_assoc($query_jurusan)) {
    $jurusan_options[$row['id_jurusan']] = $row['nama_jurusan'];
}


// Logika Tambah/Edit/Hapus Materi
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $materi_id_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

        // Dapatkan path file sebelum menghapus dari DB
        $query_file = mysqli_query($conn, "SELECT file_path FROM materi WHERE materi_id = '$materi_id_to_delete'");
        $file_data = mysqli_fetch_assoc($query_file);
        $file_to_delete = $file_data['file_path'] ?? null;

        // Tambahkan kondisi user_id jika guru
        $delete_condition = ($user_role === 'guru') ? " AND guru_id = '$guru_id_loggedin'" : "";

        $delete_sql = mysqli_query($conn, "DELETE FROM materi WHERE materi_id = '$materi_id_to_delete'" . $delete_condition);

        if ($delete_sql) {
            // Hapus file fisik jika ada
            if ($file_to_delete && file_exists(__DIR__ . '/../uploads/materi/' . basename($file_to_delete))) {
                unlink(__DIR__ . '/../uploads/materi/' . basename($file_to_delete));
            }
            echo "<script>alert('Materi berhasil dihapus!');window.location.href='?page=materials';</script>";
        } else {
            echo "<script>alert('Gagal menghapus materi: " . mysqli_error($conn) . "');window.location.href='?page=materials';</script>";
        }
    }
}

if (isset($_POST['submit_materi'])) {
    $materi_id_form = mysqli_real_escape_string($conn, $_POST['materi_id'] ?? '');
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $id_mapel = mysqli_real_escape_string($conn, $_POST['id_mapel']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $id_tahun_ajaran = mysqli_real_escape_string($conn, $_POST['id_tahun_ajaran']);
    
    // Asumsi guru_id sudah didapatkan di awal script
    $guru_id = $guru_id_loggedin;
    if ($user_role === 'admin' && isset($_POST['guru_id'])) { // Admin bisa memilih guru
        $guru_id = mysqli_real_escape_string($conn, $_POST['guru_id']);
    } elseif ($user_role === 'guru' && empty($guru_id)) {
        echo "<div class='alert alert-danger'>Error: Guru ID tidak ditemukan untuk pengunggahan materi.</div>";
        exit();
    }


    $file_path = '';
    $tipe_file = '';

    // Penanganan upload file
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/../uploads/materi/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $original_filename = basename($_FILES["file_materi"]["name"]);
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid('materi_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $tipe_file = $_FILES["file_materi"]["type"];

        if (move_uploaded_file($_FILES["file_materi"]["tmp_name"], $target_file)) {
            $file_path = 'uploads/materi/' . $new_filename;
        } else {
            echo "<div class='alert alert-danger'>Gagal mengunggah file materi.</div>";
            // Jika upload gagal, hentikan proses atau berikan pesan error
            if (empty($materi_id_form)) { // Jika ini adalah materi baru dan upload gagal
                exit();
            }
        }
    }

    if (empty($materi_id_form)) { // Mode Tambah
        $insert_sql = mysqli_query($conn, "INSERT INTO materi (judul, deskripsi, file_path, tipe_file, guru_id, id_mapel, id_kelas, id_tahun_ajaran) 
                                            VALUES ('$judul', '$deskripsi', '$file_path', '$tipe_file', '$guru_id', '$id_mapel', '$id_kelas', '$id_tahun_ajaran')");
        if ($insert_sql) {
            echo "<script>alert('Materi baru berhasil ditambahkan!');window.location.href='?page=materials';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal menambahkan materi: " . mysqli_error($conn) . "</div>";
        }
    } else { // Mode Edit
        $update_sql_part = "judul = '$judul', deskripsi = '$deskripsi', id_mapel = '$id_mapel', id_kelas = '$id_kelas', id_tahun_ajaran = '$id_tahun_ajaran'";
        if (!empty($file_path)) {
            // Hapus file lama jika ada file baru diupload
            $query_old_file = mysqli_query($conn, "SELECT file_path FROM materi WHERE materi_id = '$materi_id_form'");
            $old_file_data = mysqli_fetch_assoc($query_old_file);
            $old_file_path = $old_file_data['file_path'] ?? null;
            if ($old_file_path && file_exists(__DIR__ . '/../' . $old_file_path)) {
                unlink(__DIR__ . '/../' . $old_file_path);
            }
            $update_sql_part .= ", file_path = '$file_path', tipe_file = '$tipe_file'";
        }
        
        // Tambahkan kondisi user_id jika guru
        $update_condition = ($user_role === 'guru') ? " AND guru_id = '$guru_id_loggedin'" : "";

        $update_sql = mysqli_query($conn, "UPDATE materi SET $update_sql_part WHERE materi_id = '$materi_id_form'" . $update_condition);
        if ($update_sql) {
            echo "<script>alert('Data materi berhasil diperbarui!');window.location.href='?page=materials';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal memperbarui materi: " . mysqli_error($conn) . "</div>";
        }
    }
}

// Logika Filter
$filter_mapel = $_GET['filter_mapel'] ?? '';
$filter_kelas = $_GET['filter_kelas'] ?? '';
$filter_tahun_ajaran = $_GET['filter_tahun_ajaran'] ?? '';
$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$filter_guru = $_GET['filter_guru'] ?? '';


$where_clause = "WHERE 1=1"; // Kondisi awal true

// Filter berdasarkan peran pengguna
if ($user_role === 'guru') {
    $where_clause .= " AND m.guru_id = '$guru_id_loggedin'";
}

if (!empty($filter_mapel)) {
    $where_clause .= " AND m.id_mapel = '" . mysqli_real_escape_string($conn, $filter_mapel) . "'";
}
if (!empty($filter_kelas)) {
    $where_clause .= " AND m.id_kelas = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'";
}
if (!empty($filter_tahun_ajaran)) {
    $where_clause .= " AND m.id_tahun_ajaran = '" . mysqli_real_escape_string($conn, $filter_tahun_ajaran) . "'";
}
if (!empty($filter_jurusan)) {
    // Untuk filter jurusan, kita perlu join ke tabel kelas terlebih dahulu
    // Ini akan dilakukan di query utama
    $where_clause .= " AND k.id_jurusan = '" . mysqli_real_escape_string($conn, $filter_jurusan) . "'";
}
if ($user_role === 'admin' && !empty($filter_guru)) {
    $where_clause .= " AND m.guru_id = '" . mysqli_real_escape_string($conn, $filter_guru) . "'";
}

// Ambil data guru untuk dropdown filter (hanya untuk admin)
$guru_options = [];
if ($user_role === 'admin') {
    $query_all_guru = mysqli_query($conn, "SELECT g.guru_id, u.nama_lengkap FROM Guru g JOIN Users u ON g.user_id = u.user_id ORDER BY u.nama_lengkap ASC");
    while ($row_guru = mysqli_fetch_assoc($query_all_guru)) {
        $guru_options[$row_guru['guru_id']] = $row_guru['nama_lengkap'];
    }
}

?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Materi Pembelajaran</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#materiModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Materi
                </button>
            </div>
            <div class="box-body">
                <form method="GET" action="?page=materials" class="form-inline mb-4">
                    <input type="hidden" name="page" value="materials">
                    <div class="form-group mr-2">
                        <label for="filter_mapel">Mapel:</label>
                        <select class="form-control input-sm" id="filter_mapel" name="filter_mapel">
                            <option value="">Semua Mapel</option>
                            <?php foreach ($mapel_options as $id => $nama): ?>
                                <option value="<?= $id; ?>" <?= ($filter_mapel == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="filter_kelas">Kelas:</label>
                        <select class="form-control input-sm" id="filter_kelas" name="filter_kelas">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelas_options as $id => $nama): ?>
                                <option value="<?= $id; ?>" <?= ($filter_kelas == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="filter_jurusan">Jurusan:</label>
                        <select class="form-control input-sm" id="filter_jurusan" name="filter_jurusan">
                            <option value="">Semua Jurusan</option>
                            <?php foreach ($jurusan_options as $id => $nama): ?>
                                <option value="<?= $id; ?>" <?= ($filter_jurusan == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="filter_tahun_ajaran">Tahun Ajaran:</label>
                        <select class="form-control input-sm" id="filter_tahun_ajaran" name="filter_tahun_ajaran">
                            <option value="">Semua TA</option>
                            <?php foreach ($tahun_ajaran_options as $id => $nama): ?>
                                <option value="<?= $id; ?>" <?= ($filter_tahun_ajaran == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                    <div class="form-group mr-2">
                        <label for="filter_guru">Guru:</label>
                        <select class="form-control input-sm" id="filter_guru" name="filter_guru">
                            <option value="">Semua Guru</option>
                            <?php foreach ($guru_options as $id => $nama): ?>
                                <option value="<?= $id; ?>" <?= ($filter_guru == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-info btn-sm">Filter</button>
                    <a href="?page=materials" class="btn btn-default btn-sm">Reset</a>
                </form>

                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Judul Materi</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Jurusan</th> <th>Tahun Ajaran</th> <th>Diunggah Oleh</th>
                                <th>Tanggal Unggah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $sql_materi = "SELECT 
                                                m.materi_id, m.judul, m.deskripsi, m.file_path, m.tipe_file, m.tanggal_unggah,
                                                mp.nama_mapel, k.nama_kelas, ta.nama_tahun_ajaran, u.nama_lengkap AS nama_guru,
                                                j.nama_jurusan, k.id_kelas, ta.id_tahun_ajaran, mp.id_mapel
                                            FROM materi m
                                            JOIN mapel mp ON m.id_mapel = mp.id_mapel
                                            JOIN kelas k ON m.id_kelas = k.id_kelas
                                            JOIN tahun_ajaran ta ON m.id_tahun_ajaran = ta.id_tahun_ajaran
                                            JOIN guru g ON m.guru_id = g.guru_id
                                            JOIN users u ON g.user_id = u.user_id
                                            LEFT JOIN jurusan j ON k.id_jurusan = j.id_jurusan
                                            $where_clause
                                            ORDER BY m.tanggal_unggah DESC";
                            
                            $result_materi = mysqli_query($conn, $sql_materi);
                            if (mysqli_num_rows($result_materi) > 0) {
                                while ($data = mysqli_fetch_assoc($result_materi)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data['judul']); ?></td>
                                        <td><?= htmlspecialchars($data['nama_mapel']); ?></td>
                                        <td><?= htmlspecialchars($data['nama_kelas']); ?></td>
                                        <td><?= htmlspecialchars($data['nama_jurusan'] ?? '-'); ?></td> <td><?= htmlspecialchars($data['nama_tahun_ajaran']); ?></td>
                                        <td><?= htmlspecialchars($data['nama_guru']); ?></td>
                                        <td><?= date('d M Y H:i', strtotime($data['tanggal_unggah'])); ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($data['file_path']); ?>" target="_blank" class="btn btn-info btn-xs" title="Unduh/Lihat File">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#materiModal"
                                                data-action="edit"
                                                data-materi_id="<?= $data['materi_id']; ?>"
                                                data-judul="<?= htmlspecialchars($data['judul']); ?>"
                                                data-deskripsi="<?= htmlspecialchars($data['deskripsi']); ?>" 
                                                data-id_mapel="<?= $data['id_mapel']; ?>" 
                                                data-id_kelas="<?= $data['id_kelas']; ?>" 
                                                data-id_tahun_ajaran="<?= $data['id_tahun_ajaran']; ?>" 
                                                title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <a href="?page=materials&action=delete&id=<?= $data['materi_id']; ?>" onclick="return confirm('Yakin ingin menghapus materi ini? File juga akan dihapus.')" class="btn btn-danger btn-xs" title="Hapus">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center'>Tidak ada materi yang ditemukan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="materiModal" tabindex="-1" role="dialog" aria-labelledby="materiModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="materiModalLabel">Form Materi</h4>
            </div>
            <form id="materiForm" method="POST" action="?page=materials" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="materi_id" id="materi_id_modal">
                    <?php if ($user_role === 'admin'): // Admin bisa memilih guru pengunggah ?>
                    <div class="form-group">
                        <label for="guru_id_modal">Guru Pengunggah:</label>
                        <select class="form-control" id="guru_id_modal" name="guru_id" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($guru_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="judul_modal">Judul Materi:</label>
                        <input type="text" class="form-control" id="judul_modal" name="judul" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_modal">Deskripsi:</label>
                        <textarea class="form-control" id="deskripsi_modal" name="deskripsi" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="id_mapel_modal">Mata Pelajaran:</label>
                        <select class="form-control" id="id_mapel_modal" name="id_mapel" required>
                            <option value="">-- Pilih Mapel --</option>
                            <?php foreach ($mapel_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_kelas_modal">Kelas:</label>
                        <select class="form-control" id="id_kelas_modal" name="id_kelas" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($kelas_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_tahun_ajaran_modal">Tahun Ajaran:</label>
                        <select class="form-control" id="id_tahun_ajaran_modal" name="id_tahun_ajaran" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php foreach ($tahun_ajaran_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="file_materi_modal">Unggah File Materi (PDF, DOCX, PPTX, JPG, PNG, MP4, dll.):</label>
                        <input type="file" class="form-control" id="file_materi_modal" name="file_materi">
                        <p class="help-block" id="current_file_info"></p>
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah file.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_materi" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#data').DataTable({
        "paging": true, "lengthChange": true, "searching": true,
        "ordering": true, "info": true, "autoWidth": true, "scrollX": true
    });

    $('#materiModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);
        $('#materiForm')[0].reset();
        $('#materi_id_modal').val('');
        $('#current_file_info').text(''); // Clear previous file info

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Materi Baru');
            // Untuk mode tambah, guru_id harus default ke guru yang login jika bukan admin
            <?php if ($user_role === 'guru'): ?>
                // Ini akan dihandle di sisi server dengan $guru_id_loggedin
            <?php endif; ?>
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Materi');
            var materi_id = button.data('materi_id');
            var judul = button.data('judul');
            var deskripsi = button.data('deskripsi');
            var id_mapel = button.data('id_mapel');
            var id_kelas = button.data('id_kelas');
            var id_tahun_ajaran = button.data('id_tahun_ajaran');

            $('#materi_id_modal').val(materi_id);
            $('#judul_modal').val(judul);
            $('#deskripsi_modal').val(deskripsi);
            $('#id_mapel_modal').val(id_mapel);
            $('#id_kelas_modal').val(id_kelas);
            $('#id_tahun_ajaran_modal').val(id_tahun_ajaran);

            // Ambil dan tampilkan info file yang sudah ada (jika ada)
            // Ini butuh AJAX call atau tambahkan data-file_path ke button edit
            // Untuk sementara, kita hanya beri tahu user untuk mengunggah ulang jika ingin mengubah
            $('#current_file_info').html('File saat ini: <span class="text-muted">Unggah ulang untuk mengganti.</span>');
            
            // Jika admin, perlu ambil guru_id dari materi yang diedit
            <?php if ($user_role === 'admin'): ?>
                // Ini perlu query AJAX tambahan atau tambahkan data-guru_id di button edit
                // Untuk contoh ini, kita asumsikan materi_id sudah cukup untuk server fetch guru_id
                // Atau lebih baik, tambahkan data-guru_id ke button edit dari query SQL
            <?php endif; ?>
        }
    });
});
</script>