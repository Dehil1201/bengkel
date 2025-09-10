<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya admin dan guru yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk mengelola kuis.</div></div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil user_id dari session untuk user yang login
$current_user_id = $_SESSION['user_id'] ?? null;
$guru_id_loggedin = null; // Default value, hanya relevan jika user adalah guru

// Ambil semua data guru untuk dropdown (digunakan oleh admin atau untuk mengambil guru_id saat guru login)
$query_all_guru = mysqli_query($conn, "SELECT g.guru_id, u.nama_lengkap FROM Guru g JOIN Users u ON g.user_id = u.user_id ORDER BY u.nama_lengkap ASC");
$all_guru_options = [];
if ($query_all_guru) {
    while ($row_guru = mysqli_fetch_assoc($query_all_guru)) {
        $all_guru_options[$row_guru['guru_id']] = $row_guru['nama_lengkap'];
    }
} else {
    error_log("Error fetching guru options: " . mysqli_error($conn));
}


// Jika user adalah guru, dapatkan guru_id-nya untuk filtering tugas
if ($user_role === 'guru' && $current_user_id) {
    $query_guru_id_current = mysqli_query($conn, "SELECT guru_id FROM Guru WHERE user_id = '$current_user_id'");
    if ($row_guru_id_current = mysqli_fetch_assoc($query_guru_id_current)) {
        $guru_id_loggedin = $row_guru_id_current['guru_id'];
    } else {
        echo "<div class='alert alert-danger'>Error: Data guru Anda tidak ditemukan di tabel Guru. Silakan hubungi administrator.</div>";
        exit();
    }
}

// --- LOGIKA TAMBAH/EDIT/HAPUS KUIS ---

// Logika Tambah Kuis
if (isset($_POST['add_quiz'])) {
    $judul_kuis = mysqli_real_escape_string($conn, $_POST['judul_kuis']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    $durasi_menit = mysqli_real_escape_string($conn, $_POST['durasi_menit']);
    
    $target_guru_id = null;
    if ($user_role === 'admin') {
        $target_guru_id = mysqli_real_escape_string($conn, $_POST['guru_id_for_quiz']); // Ambil dari dropdown
    } elseif ($user_role === 'guru') {
        $target_guru_id = $guru_id_loggedin; // Otomatis dari guru yang login
    }

    // Validasi input
    if (empty($judul_kuis) || empty($waktu_mulai) || empty($waktu_selesai) || empty($durasi_menit) || empty($target_guru_id)) {
        echo "<div class='alert alert-danger'>Judul, waktu mulai, waktu selesai, durasi kuis, dan guru pemilik kuis tidak boleh kosong.</div>";
    } elseif ($durasi_menit <= 0) {
        echo "<div class='alert alert-danger'>Durasi kuis harus lebih dari 0 menit.</div>";
    } elseif (strtotime($waktu_mulai) >= strtotime($waktu_selesai)) {
        echo "<div class='alert alert-danger'>Waktu mulai harus sebelum waktu selesai.</div>";
    } else {
        $insert_sql = "INSERT INTO Kuis (judul_kuis, deskripsi, waktu_mulai, waktu_selesai, durasi_menit, guru_id) 
                       VALUES ('$judul_kuis', '" . (empty($deskripsi) ? NULL : $deskripsi) . "', '$waktu_mulai', '$waktu_selesai', '$durasi_menit', '$target_guru_id')";
        
        if (mysqli_query($conn, $insert_sql)) {
            echo "<script>alert('Kuis berhasil ditambahkan!');window.location.href='?page=quizzes_overview';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal menambahkan kuis: " . mysqli_error($conn) . "</div>";
            error_log("SQL Error adding quiz: " . mysqli_error($conn) . " Query: " . $insert_sql);
        }
    }
}

// Logika Edit Kuis
if (isset($_POST['edit_quiz'])) {
    $kuis_id = mysqli_real_escape_string($conn, $_POST['kuis_id']);
    $judul_kuis = mysqli_real_escape_string($conn, $_POST['judul_kuis']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    $durasi_menit = mysqli_real_escape_string($conn, $_POST['durasi_menit']);
    
    $target_guru_id_edit = null;
    if ($user_role === 'admin') {
        $target_guru_id_edit = mysqli_real_escape_string($conn, $_POST['guru_id_for_quiz_edit']); // Ambil dari dropdown
    } else {
        // Jika guru yang edit, pastikan dia hanya bisa mengedit kuis miliknya
        // dan guru_id tidak diubah melalui form (tetap guru_id_loggedin)
        $target_guru_id_edit = $guru_id_loggedin;
    }

    // Validasi input
    if (empty($judul_kuis) || empty($waktu_mulai) || empty($waktu_selesai) || empty($durasi_menit) || empty($target_guru_id_edit)) {
        echo "<div class='alert alert-danger'>Judul, waktu mulai, waktu selesai, durasi kuis, dan guru pemilik kuis tidak boleh kosong.</div>";
    } elseif ($durasi_menit <= 0) {
        echo "<div class='alert alert-danger'>Durasi kuis harus lebih dari 0 menit.</div>";
    } elseif (strtotime($waktu_mulai) >= strtotime($waktu_selesai)) {
        echo "<div class='alert alert-danger'>Waktu mulai harus sebelum waktu selesai.</div>";
    } else {
        $update_sql = "UPDATE Kuis SET 
                        judul_kuis = '$judul_kuis', 
                        deskripsi = '" . (empty($deskripsi) ? NULL : $deskripsi) . "', 
                        waktu_mulai = '$waktu_mulai', 
                        waktu_selesai = '$waktu_selesai', 
                        durasi_menit = '$durasi_menit',
                        guru_id = '$target_guru_id_edit'
                       WHERE kuis_id = '$kuis_id'";
        
        // Jika user adalah guru, pastikan hanya kuis miliknya yang bisa diedit
        if ($user_role === 'guru') {
            $update_sql .= " AND guru_id = '$guru_id_loggedin'";
        }

        if (mysqli_query($conn, $update_sql)) {
            echo "<script>alert('Kuis berhasil diperbarui!');window.location.href='?page=quizzes_overview';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal memperbarui kuis: " . mysqli_error($conn) . "</div>";
            error_log("SQL Error updating quiz: " . mysqli_error($conn) . " Query: " . $update_sql);
        }
    }
}

// Logika Hapus Kuis
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $kuis_id_delete = mysqli_real_escape_string($conn, $_GET['kuis_id']);
    
    $delete_sql = "DELETE FROM Kuis WHERE kuis_id = '$kuis_id_delete'";
    
    // Jika user adalah guru, pastikan hanya kuis miliknya yang bisa dihapus
    if ($user_role === 'guru') {
        $delete_sql .= " AND guru_id = '$guru_id_loggedin'";
    }

    if (mysqli_query($conn, $delete_sql)) {
        echo "<script>alert('Kuis berhasil dihapus!');window.location.href='?page=quizzes_overview';</script>";
    } else {
        echo "<div class='alert alert-danger'>Gagal menghapus kuis: " . mysqli_error($conn) . "</div>";
        error_log("SQL Error deleting quiz: " . mysqli_error($conn) . " Query: " . $delete_sql);
    }
}

// --- TAMPILAN HALAMAN ---
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Kuis</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuizModal">
                        <i class="fa fa-plus"></i> Tambah Kuis Baru
                    </button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Judul Kuis</th>
                                <th>Guru Pembuat</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th>Durasi (Menit)</th>
                                <th>Deskripsi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $where_clause_kuis = "WHERE 1";
                            if ($user_role === 'guru') {
                                $where_clause_kuis .= " AND K.guru_id = '" . mysqli_real_escape_string($conn, $guru_id_loggedin) . "'";
                            }

                            $sql_quizzes = "SELECT 
                                                K.kuis_id, K.judul_kuis, K.deskripsi, K.waktu_mulai, K.waktu_selesai, K.durasi_menit,
                                                U.nama_lengkap AS nama_guru,
                                                K.guru_id AS current_guru_id_kuis
                                            FROM Kuis K
                                            JOIN Guru G ON K.guru_id = G.guru_id
                                            JOIN Users U ON G.user_id = U.user_id
                                            $where_clause_kuis
                                            ORDER BY K.waktu_mulai DESC";
                            $result_quizzes = mysqli_query($conn, $sql_quizzes);

                            if ($result_quizzes && mysqli_num_rows($result_quizzes) > 0) {
                                while ($kuis = mysqli_fetch_assoc($result_quizzes)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($kuis['judul_kuis']); ?></td>
                                        <td><?= htmlspecialchars($kuis['nama_guru']); ?></td>
                                        <td><?= date('d-m-Y H:i', strtotime($kuis['waktu_mulai'])); ?></td>
                                        <td><?= date('d-m-Y H:i', strtotime($kuis['waktu_selesai'])); ?></td>
                                        <td><?= htmlspecialchars($kuis['durasi_menit']); ?></td>
                                        <td><?= nl2br(htmlspecialchars(substr($kuis['deskripsi'] ?? '', 0, 100))); ?><?= (strlen($kuis['deskripsi'] ?? '') > 100) ? '...' : ''; ?></td>
                                        <td>
                                            <a href="?page=quiz_questions&kuis_id=<?= $kuis['kuis_id']; ?>" class="btn btn-info btn-xs" title="Kelola Pertanyaan">
                                                <i class="fa fa-question-circle"></i> Pertanyaan
                                            </a>
                                            <button type="button" class="btn btn-warning btn-xs edit-quiz-btn" 
                                                    data-toggle="modal" data-target="#editQuizModal"
                                                    data-kuis_id="<?= $kuis['kuis_id']; ?>"
                                                    data-judul_kuis="<?= htmlspecialchars($kuis['judul_kuis']); ?>"
                                                    data-deskripsi="<?= htmlspecialchars($kuis['deskripsi'] ?? ''); ?>"
                                                    data-waktu_mulai="<?= date('Y-m-d\TH:i', strtotime($kuis['waktu_mulai'])); ?>"
                                                    data-waktu_selesai="<?= date('Y-m-d\TH:i', strtotime($kuis['waktu_selesai'])); ?>"
                                                    data-durasi_menit="<?= htmlspecialchars($kuis['durasi_menit']); ?>"
                                                    data-guru_id="<?= htmlspecialchars($kuis['current_guru_id_kuis']); ?>"
                                                    title="Edit Kuis">
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                            <a href="?page=quizzes_overview&action=delete&kuis_id=<?= $kuis['kuis_id']; ?>" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kuis ini beserta semua pertanyaan dan jawabannya?')" 
                                               class="btn btn-danger btn-xs" title="Hapus Kuis">
                                                <i class="fa fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>Tidak ada kuis yang ditemukan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="addQuizModal" tabindex="-1" role="dialog" aria-labelledby="addQuizModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="addQuizModalLabel">Tambah Kuis Baru</h4>
            </div>
            <form method="POST" action="?page=quizzes_overview">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="judul_kuis_add">Judul Kuis</label>
                        <input type="text" class="form-control" id="judul_kuis_add" name="judul_kuis" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_add">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi_add" name="deskripsi" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="waktu_mulai_add">Waktu Mulai</label>
                        <input type="datetime-local" class="form-control" id="waktu_mulai_add" name="waktu_mulai" required>
                    </div>
                    <div class="form-group">
                        <label for="waktu_selesai_add">Waktu Selesai</label>
                        <input type="datetime-local" class="form-control" id="waktu_selesai_add" name="waktu_selesai" required>
                    </div>
                    <div class="form-group">
                        <label for="durasi_menit_add">Durasi (Menit)</label>
                        <input type="number" class="form-control" id="durasi_menit_add" name="durasi_menit" min="1" required>
                    </div>
                    
                    <?php if ($user_role === 'admin'): // Hanya tampilkan jika user adalah admin ?>
                    <div class="form-group">
                        <label for="guru_id_for_quiz">Pilih Guru Pemilik Kuis</label>
                        <select class="form-control" id="guru_id_for_quiz" name="guru_id_for_quiz" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($all_guru_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="add_quiz" class="btn btn-primary">Simpan Kuis</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editQuizModal" tabindex="-1" role="dialog" aria-labelledby="editQuizModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="editQuizModalLabel">Edit Kuis</h4>
            </div>
            <form method="POST" action="?page=quizzes_overview">
                <div class="modal-body">
                    <input type="hidden" name="kuis_id" id="kuis_id_edit">
                    <div class="form-group">
                        <label for="judul_kuis_edit">Judul Kuis</label>
                        <input type="text" class="form-control" id="judul_kuis_edit" name="judul_kuis" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_edit">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi_edit" name="deskripsi" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="waktu_mulai_edit">Waktu Mulai</label>
                        <input type="datetime-local" class="form-control" id="waktu_mulai_edit" name="waktu_mulai" required>
                    </div>
                    <div class="form-group">
                        <label for="waktu_selesai_edit">Waktu Selesai</label>
                        <input type="datetime-local" class="form-control" id="waktu_selesai_edit" name="waktu_selesai" required>
                    </div>
                    <div class="form-group">
                        <label for="durasi_menit_edit">Durasi (Menit)</label>
                        <input type="number" class="form-control" id="durasi_menit_edit" name="durasi_menit" min="1" required>
                    </div>
                    
                    <?php if ($user_role === 'admin'): // Hanya tampilkan jika user adalah admin ?>
                    <div class="form-group">
                        <label for="guru_id_for_quiz_edit">Pilih Guru Pemilik Kuis</label>
                        <select class="form-control" id="guru_id_for_quiz_edit" name="guru_id_for_quiz_edit" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($all_guru_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_quiz" class="btn btn-primary">Simpan Perubahan</button>
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

    // Logika untuk mengisi data ke Modal Edit Kuis
    $('#editQuizModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var kuis_id = button.data('kuis_id');
        var judul_kuis = button.data('judul_kuis');
        var deskripsi = button.data('deskripsi');
        var waktu_mulai = button.data('waktu_mulai'); // Format YYYY-MM-DDTHH:MM
        var waktu_selesai = button.data('waktu_selesai'); // Format YYYY-MM-DDTHH:MM
        var durasi_menit = button.data('durasi_menit');
        var guru_id = button.data('guru_id'); // Ambil guru_id dari data-attribute

        var modal = $(this);
        modal.find('#kuis_id_edit').val(kuis_id);
        modal.find('#judul_kuis_edit').val(judul_kuis);
        modal.find('#deskripsi_edit').val(deskripsi);
        modal.find('#waktu_mulai_edit').val(waktu_mulai);
        modal.find('#waktu_selesai_edit').val(waktu_selesai);
        modal.find('#durasi_menit_edit').val(durasi_menit);
        
        // Pilih guru di dropdown (hanya jika ada dropdown, yaitu untuk admin)
        if (modal.find('#guru_id_for_quiz_edit').length) {
            modal.find('#guru_id_for_quiz_edit').val(guru_id);
        }
    });
});
</script>