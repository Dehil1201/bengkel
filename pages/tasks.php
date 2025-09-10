<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya admin dan guru yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk mengelola tugas.</div></div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil user_id dari session untuk guru yang login
$current_user_id = $_SESSION['user_id'] ?? null;
$guru_id_loggedin = null;

// Jika user adalah guru, dapatkan guru_id-nya
if ($user_role === 'guru' && $current_user_id) {
    $query_guru_id = mysqli_query($conn, "SELECT guru_id FROM Guru WHERE user_id = '$current_user_id'");
    if ($row_guru_id = mysqli_fetch_assoc($query_guru_id)) {
        $guru_id_loggedin = $row_guru_id['guru_id'];
    } else {
        echo "<div class='alert alert-danger'>Error: Data guru Anda tidak ditemukan. Silakan hubungi administrator.</div>";
        exit();
    }
}

// Ambil data untuk dropdown Guru (untuk filter dan form)
$query_all_guru = mysqli_query($conn, "SELECT g.guru_id, u.nama_lengkap FROM Guru g JOIN Users u ON g.user_id = u.user_id WHERE g.status = 'aktif' ORDER BY u.nama_lengkap ASC");
$guru_options = [];
if ($query_all_guru) {
    while ($row_guru = mysqli_fetch_assoc($query_all_guru)) {
        $guru_options[$row_guru['guru_id']] = $row_guru['nama_lengkap'];
    }
} else {
    // Tambahkan error handling jika query gagal
    error_log("Error fetching guru options: " . mysqli_error($conn));
}


// Ambil data untuk dropdown Kelas
$query_kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas, id_jurusan FROM kelas ORDER BY nama_kelas ASC");
$kelas_options = [];
if ($query_kelas) {
    while ($row = mysqli_fetch_assoc($query_kelas)) {
        $kelas_options[$row['id_kelas']] = [
            'nama' => $row['nama_kelas'],
            'id_jurusan' => $row['id_jurusan']
        ];
    }
} else {
    error_log("Error fetching kelas options: " . mysqli_error($conn));
}


// Ambil data untuk dropdown Jurusan
$query_jurusan = mysqli_query($conn, "SELECT id_jurusan, nama_jurusan FROM jurusan ORDER BY nama_jurusan ASC");
$jurusan_options = [];
if ($query_jurusan) {
    while ($row = mysqli_fetch_assoc($query_jurusan)) {
        $jurusan_options[$row['id_jurusan']] = $row['nama_jurusan'];
    }
} else {
    error_log("Error fetching jurusan options: " . mysqli_error($conn));
}


// Ambil data untuk dropdown Tahun Ajaran (hanya yang aktif)
$query_tahun_ajaran = mysqli_query($conn, "SELECT id_tahun_ajaran, nama_tahun_ajaran FROM tahun_ajaran WHERE status = 'aktif' ORDER BY nama_tahun_ajaran DESC");
$tahun_ajaran_options = [];
if ($query_tahun_ajaran) {
    while ($row = mysqli_fetch_assoc($query_tahun_ajaran)) {
        $tahun_ajaran_options[$row['id_tahun_ajaran']] = $row['nama_tahun_ajaran'];
    }
} else {
    error_log("Error fetching tahun ajaran options: " . mysqli_error($conn));
}


// Logika Tambah/Edit/Hapus Tugas
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $task_id_to_delete = mysqli_real_escape_string($conn, $_GET['id']);
        
        $delete_sql = mysqli_query($conn, "DELETE FROM tasks WHERE task_id = '$task_id_to_delete'");
        if ($delete_sql) {
            echo "<script>alert('Tugas berhasil dihapus!');window.location.href='?page=tasks';</script>";
        } else {
            echo "<script>alert('Gagal menghapus tugas: " . mysqli_error($conn) . "');window.location.href='?page=tasks';</script>";
        }
    }
}

if (isset($_POST['submit_task'])) {
    $task_id_form = mysqli_real_escape_string($conn, $_POST['task_id'] ?? '');
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $tanggal_mulai = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $tanggal_berakhir = mysqli_real_escape_string($conn, $_POST['tanggal_berakhir']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $id_tahun_ajaran = mysqli_real_escape_string($conn, $_POST['id_tahun_ajaran']);

    // Tentukan guru_id: jika admin dipilih dari form, jika guru otomatis dari session
    $guru_id = null;
    if ($user_role === 'admin' && isset($_POST['guru_id_pembuat'])) {
        $guru_id = mysqli_real_escape_string($conn, $_POST['guru_id_pembuat']);
    } elseif ($user_role === 'guru') {
        $guru_id = $guru_id_loggedin;
    }

    // Ambil id_jurusan dari id_kelas yang dipilih
    $id_jurusan = $kelas_options[$id_kelas]['id_jurusan'] ?? null;
    if (!$id_jurusan) {
         echo "<div class='alert alert-danger'>Jurusan tidak ditemukan untuk kelas yang dipilih.</div>";
         goto end_script; // Langsung ke bagian akhir script
    }

    // Validasi input dasar
    if (empty($judul) || empty($tanggal_mulai) || empty($tanggal_berakhir) || empty($id_kelas) || empty($id_tahun_ajaran) || empty($guru_id)) {
        echo "<div class='alert alert-danger'>Harap lengkapi semua bidang yang wajib diisi.</div>";
        goto end_script;
    }
    
    // Validasi tanggal
    if (strtotime($tanggal_mulai) > strtotime($tanggal_berakhir)) {
        echo "<div class='alert alert-danger'>Tanggal Mulai tidak boleh setelah Tanggal Berakhir.</div>";
        goto end_script;
    }

    if (empty($task_id_form)) { // Mode Tambah Tugas Baru
        $insert_sql = "INSERT INTO tasks (judul, deskripsi, tanggal_mulai, tanggal_berakhir, guru_id, id_kelas, id_jurusan, id_tahun_ajaran) 
                       VALUES ('$judul', " . (empty($deskripsi) ? "NULL" : "'$deskripsi'") . ", '$tanggal_mulai', '$tanggal_berakhir', '$guru_id', '$id_kelas', '$id_jurusan', '$id_tahun_ajaran')";

        if (mysqli_query($conn, $insert_sql)) {
            echo "<script>alert('Tugas baru berhasil ditambahkan!');window.location.href='?page=tasks';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal menambahkan tugas: " . mysqli_error($conn) . "</div>";
        }
    } else { // Mode Edit Tugas
        $update_sql = "UPDATE tasks SET 
                        judul = '$judul', 
                        deskripsi = " . (empty($deskripsi) ? "NULL" : "'$deskripsi'") . ", 
                        tanggal_mulai = '$tanggal_mulai', 
                        tanggal_berakhir = '$tanggal_berakhir', 
                        guru_id = '$guru_id', 
                        id_kelas = '$id_kelas', 
                        id_jurusan = '$id_jurusan', 
                        id_tahun_ajaran = '$id_tahun_ajaran' 
                       WHERE task_id = '$task_id_form'";

        if (mysqli_query($conn, $update_sql)) {
            echo "<script>alert('Tugas berhasil diperbarui!');window.location.href='?page=tasks';</script>";
        } else {
            echo "<div class='alert alert-danger'>Gagal memperbarui tugas: " . mysqli_error($conn) . "</div>";
        }
    }
}
end_script:
// Logika Filter
$filter_guru = $_GET['filter_guru'] ?? '';
$filter_kelas = $_GET['filter_kelas'] ?? '';
$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$filter_tahun_ajaran = $_GET['filter_tahun_ajaran'] ?? '';

$where_clause = "WHERE 1"; // Kondisi awal

if ($user_role === 'guru') {
    // Jika user adalah guru, hanya tampilkan tugas yang dibuatnya
    $where_clause .= " AND t.guru_id = '" . mysqli_real_escape_string($conn, $guru_id_loggedin) . "'";
} else { // Jika user adalah admin, filter bisa diterapkan
    if (!empty($filter_guru)) {
        $where_clause .= " AND t.guru_id = '" . mysqli_real_escape_string($conn, $filter_guru) . "'";
    }
}

if (!empty($filter_kelas)) {
    $where_clause .= " AND t.id_kelas = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'";
}
if (!empty($filter_jurusan)) {
    $where_clause .= " AND t.id_jurusan = '" . mysqli_real_escape_string($conn, $filter_jurusan) . "'";
}
if (!empty($filter_tahun_ajaran)) {
    $where_clause .= " AND t.id_tahun_ajaran = '" . mysqli_real_escape_string($conn, $filter_tahun_ajaran) . "'";
}

?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Tugas</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#taskModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Tugas
                </button>
            </div>
            <div class="box-body">
                <form method="GET" action="?page=tasks" class="form-inline mb-4">
                    <input type="hidden" name="page" value="tasks">
                    
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

                    <div class="form-group mr-2">
                        <label for="filter_kelas">Kelas:</label>
                        <select class="form-control input-sm" id="filter_kelas" name="filter_kelas">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelas_options as $id => $data): ?>
                                <option value="<?= $id; ?>" <?= ($filter_kelas == $id) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($data['nama']); ?>
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
                    <button type="submit" class="btn btn-info btn-sm">Filter</button>
                    <a href="?page=tasks" class="btn btn-default btn-sm">Reset</a>
                </form>

                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>                  <th>Judul Tugas</th>          <th>Guru Pembuat</th>         <th>Tanggal Mulai</th>        <th>Tanggal Berakhir</th>     <th>Kelas Target</th>         <th>Jurusan Target</th>       <th>Tahun Ajaran</th>         <th>Aksi</th>                 </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Join tabel tasks dengan guru, users, kelas, jurusan, tahun_ajaran
                            $sql_tasks = "SELECT 
                                                t.task_id, t.judul, t.deskripsi, t.tanggal_mulai, t.tanggal_berakhir,
                                                u.nama_lengkap AS nama_guru,
                                                k.nama_kelas, j.nama_jurusan, ta.nama_tahun_ajaran,
                                                t.guru_id, t.id_kelas, t.id_jurusan, t.id_tahun_ajaran
                                            FROM tasks t
                                            JOIN guru g ON t.guru_id = g.guru_id
                                            JOIN users u ON g.user_id = u.user_id
                                            LEFT JOIN kelas k ON t.id_kelas = k.id_kelas
                                            LEFT JOIN jurusan j ON t.id_jurusan = j.id_jurusan
                                            LEFT JOIN tahun_ajaran ta ON t.id_tahun_ajaran = ta.id_tahun_ajaran
                                            $where_clause
                                            ORDER BY t.tanggal_mulai DESC";
                            $result_tasks = mysqli_query($conn, $sql_tasks);

                            if ($result_tasks) { // Pastikan query berhasil
                                if (mysqli_num_rows($result_tasks) > 0) {
                                    while ($data = mysqli_fetch_assoc($result_tasks)) {
                            ?>
                                        <tr>
                                            <td><?= $no++; ?></td>                                <td><?= htmlspecialchars($data['judul']); ?></td>    <td><?= htmlspecialchars($data['nama_guru']); ?></td><td><?= date('d-m-Y', strtotime($data['tanggal_mulai'])); ?></td> <td><?= date('d-m-Y', strtotime($data['tanggal_berakhir'])); ?></td> <td><?= htmlspecialchars($data['nama_kelas'] ?? '-'); ?></td> <td><?= htmlspecialchars($data['nama_jurusan'] ?? '-'); ?></td> <td><?= htmlspecialchars($data['nama_tahun_ajaran'] ?? '-'); ?></td> <td>
                                                <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#taskModal"
                                                    data-action="edit"
                                                    data-task_id="<?= $data['task_id']; ?>"
                                                    data-judul="<?= htmlspecialchars($data['judul']); ?>"
                                                    data-deskripsi="<?= htmlspecialchars($data['deskripsi']); ?>"
                                                    data-tanggal_mulai="<?= $data['tanggal_mulai']; ?>"
                                                    data-tanggal_berakhir="<?= $data['tanggal_berakhir']; ?>"
                                                    data-guru_id="<?= $data['guru_id']; ?>"
                                                    data-id_kelas="<?= $data['id_kelas']; ?>"
                                                    data-id_tahun_ajaran="<?= $data['id_tahun_ajaran']; ?>"
                                                    title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <a href="?page=tasks&action=delete&id=<?= $data['task_id']; ?>" onclick="return confirm('Yakin ingin menghapus tugas ini?')" class="btn btn-danger btn-xs" title="Hapus">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                            <?php
                                    }
                                } else {
                                    // Ini penting: colspan harus sesuai dengan jumlah kolom header
                                    echo "<tr><td colspan='9' class='text-center'>Tidak ada tugas yang ditemukan.</td></tr>";
                                }
                            } else {
                                // Jika query gagal, tampilkan pesan error dan kosongkan tabel
                                echo "<tr><td colspan='9' class='text-center text-danger'>Terjadi kesalahan dalam mengambil data tugas: " . mysqli_error($conn) . "</td></tr>";
                                error_log("SQL Error in tasks.php: " . mysqli_error($conn));
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="taskModal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="taskModalLabel">Form Tugas</h4>
            </div>
            <form id="taskForm" method="POST" action="?page=tasks">
                <div class="modal-body">
                    <input type="hidden" name="task_id" id="task_id_modal">
                    <div class="form-group">
                        <label for="judul_modal">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul_modal" name="judul" placeholder="Masukkan judul tugas" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_modal">Deskripsi Tugas</label>
                        <textarea class="form-control" id="deskripsi_modal" name="deskripsi" rows="3" placeholder="Deskripsi singkat tentang tugas ini"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_mulai_modal">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal_mulai_modal" name="tanggal_mulai" required>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_berakhir_modal">Tanggal Berakhir <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal_berakhir_modal" name="tanggal_berakhir" required>
                    </div>
                    <div class="form-group">
                        <label for="id_kelas_modal">Kelas Target <span class="text-danger">*</span></label>
                        <select class="form-control" id="id_kelas_modal" name="id_kelas" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php 
                            foreach ($kelas_options as $id => $data) {
                                echo "<option value='". $id ."'>" . htmlspecialchars($data['nama']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="id_jurusan_display_modal">Jurusan Target</label>
                        <input type="text" class="form-control" id="id_jurusan_display_modal" readonly>
                        <small class="text-muted">Jurusan akan otomatis terisi setelah memilih kelas.</small>
                    </div>
                    <div class="form-group">
                        <label for="id_tahun_ajaran_modal">Tahun Ajaran <span class="text-danger">*</span></label>
                        <select class="form-control" id="id_tahun_ajaran_modal" name="id_tahun_ajaran" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php 
                            foreach ($tahun_ajaran_options as $id => $nama) {
                                echo "<option value='". $id ."'>" . htmlspecialchars($nama) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <?php if ($user_role === 'admin'): ?>
                    <div class="form-group">
                        <label for="guru_id_pembuat_modal">Guru Pembuat Tugas <span class="text-danger">*</span></label>
                        <select class="form-control" id="guru_id_pembuat_modal" name="guru_id_pembuat" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php 
                            foreach ($guru_options as $id => $nama) {
                                echo "<option value='". $id ."'>" . htmlspecialchars($nama) . "</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Admin dapat membuat tugas atas nama guru lain.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_task" class="btn btn-primary">Simpan</button>
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

    // Data kelas dan jurusan dari PHP untuk digunakan JavaScript
    const kelasData = <?= json_encode($kelas_options); ?>;
    const jurusanData = <?= json_encode($jurusan_options); ?>;

    // Fungsi untuk mengisi Jurusan berdasarkan Kelas
    function updateJurusanDisplay(selectedKelasId, targetInputId) {
        let jurusanNama = '';
        if (selectedKelasId && kelasData[selectedKelasId]) {
            const idJurusanTerkait = kelasData[selectedKelasId].id_jurusan;
            if (jurusanData[idJurusanTerkait]) {
                jurusanNama = jurusanData[idJurusanTerkait];
            }
        }
        $(targetInputId).val(jurusanNama);
    }

    // Panggil fungsi updateJurusanDisplay saat dropdown kelas filter berubah
    $('#filter_kelas').on('change', function() {
        const selectedKelasId = $(this).val();
        let filteredJurusanOptions = '<option value="">Semua Jurusan</option>';
        if (selectedKelasId) {
            const idJurusanTerkait = kelasData[selectedKelasId].id_jurusan;
            if (jurusanData[idJurusanTerkait]) {
                // Jika kelas dipilih, hanya tampilkan jurusan yang terkait dengan kelas itu
                // Pastikan filter_jurusan di PHP sudah terdefinisi untuk perbandingan ini
                filteredJurusanOptions += `<option value="${idJurusanTerkait}" ${'<?= $filter_jurusan; ?>' == idJurusanTerkait ? 'selected' : ''}>${jurusanData[idJurusanTerkait]}</option>`;
            }
        } else {
            // Jika "Semua Kelas", tampilkan semua jurusan
            for (const id in jurusanData) {
                filteredJurusanOptions += `<option value="${id}" ${'<?= $filter_jurusan; ?>' == id ? 'selected' : ''}>${jurusanData[id]}</option>`;
            }
        }
        $('#filter_jurusan').html(filteredJurusanOptions);
    }).trigger('change'); // Panggil saat load untuk inisialisasi filter jurusan

    // Panggil fungsi updateJurusanDisplay saat dropdown kelas modal berubah
    $('#id_kelas_modal').on('change', function() {
        updateJurusanDisplay($(this).val(), '#id_jurusan_display_modal');
    });

    // Logika untuk Modal Tambah/Edit Tugas
    $('#taskModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);
        $('#taskForm')[0].reset(); // Reset form
        $('#task_id_modal').val(''); // Clear hidden ID

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Tugas Baru');
            // Reset dropdowns ke default
            $('#id_kelas_modal').val('');
            $('#id_tahun_ajaran_modal').val('');
            $('#guru_id_pembuat_modal').val(''); // Hanya jika admin
            updateJurusanDisplay('', '#id_jurusan_display_modal'); // Clear jurusan display
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Tugas');
            var task_id = button.data('task_id');
            var judul = button.data('judul');
            var deskripsi = button.data('deskripsi');
            var tanggal_mulai = button.data('tanggal_mulai');
            var tanggal_berakhir = button.data('tanggal_berakhir');
            var guru_id = button.data('guru_id');
            var id_kelas = button.data('id_kelas');
            var id_tahun_ajaran = button.data('id_tahun_ajaran');

            $('#task_id_modal').val(task_id);
            $('#judul_modal').val(judul);
            $('#deskripsi_modal').val(deskripsi);
            $('#tanggal_mulai_modal').val(tanggal_mulai);
            $('#tanggal_berakhir_modal').val(tanggal_berakhir);
            $('#id_kelas_modal').val(id_kelas);
            $('#id_tahun_ajaran_modal').val(id_tahun_ajaran);
            
            // Set guru_id_pembuat_modal jika ada (khusus admin)
            if ($('#guru_id_pembuat_modal').length) {
                $('#guru_id_pembuat_modal').val(guru_id);
            }

            // Update display jurusan berdasarkan kelas yang diedit
            updateJurusanDisplay(id_kelas, '#id_jurusan_display_modal');
        }
    });
});
</script>