<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya admin dan guru yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat ikhtisar pengumpulan tugas.</div></div>";
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

// Ambil data untuk dropdown Guru (untuk filter)
$query_all_guru = mysqli_query($conn, "SELECT g.guru_id, u.nama_lengkap FROM Guru g JOIN Users u ON g.user_id = u.user_id WHERE g.status = 'aktif' ORDER BY u.nama_lengkap ASC");
$guru_options = [];
if ($query_all_guru) {
    while ($row_guru = mysqli_fetch_assoc($query_all_guru)) {
        $guru_options[$row_guru['guru_id']] = $row_guru['nama_lengkap'];
    }
} else {
    error_log("Error fetching guru options: " . mysqli_error($conn));
}


// Ambil data untuk dropdown Kelas
// Perlu mengambil nama_kelas dan id_jurusan untuk mapping
$query_kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas, id_jurusan FROM kelas ORDER BY nama_kelas ASC");
$kelas_options = []; // Untuk dropdown
$kelas_id_to_nama = []; // Untuk mapping id_kelas tugas ke nama_kelas siswa
if ($query_kelas) {
    while ($row = mysqli_fetch_assoc($query_kelas)) {
        $kelas_options[$row['id_kelas']] = [
            'nama' => $row['nama_kelas'],
            'id_jurusan' => $row['id_jurusan']
        ];
        $kelas_id_to_nama[$row['id_kelas']] = $row['nama_kelas']; // Simpan mapping
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


// Logika Filter
$filter_guru = $_GET['filter_guru'] ?? '';
$filter_kelas = $_GET['filter_kelas'] ?? '';
$filter_jurusan = $_GET['filter_jurusan'] ?? '';
$filter_tahun_ajaran = $_GET['filter_tahun_ajaran'] ?? '';

$where_clause = "WHERE 1"; // Kondisi awal untuk tasks

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
                <h3 class="box-title">Ikhtisar Pengumpulan Tugas</h3>
            </div>
            <div class="box-body">
                <form method="GET" action="?page=submissions_overview" class="form-inline mb-4">
                    <input type="hidden" name="page" value="submissions_overview">
                    
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
                    <a href="?page=submissions_overview" class="btn btn-default btn-sm">Reset</a>
                </form>

                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Judul Tugas</th>
                                <th>Guru Pembuat</th>
                                <th>Kelas & Jurusan Target</th>
                                <th>Tahun Ajaran</th>
                                <th>Deadline</th>
                                <th>Total Siswa</th>
                                <th>Sudah Kumpul</th>
                                <th>Belum Kumpul</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Query untuk mendapatkan daftar tugas dengan informasi kelas/jurusan/tahun ajaran
                            $sql_tasks = "SELECT 
                                                    t.task_id, t.judul, t.tanggal_berakhir,
                                                    u.nama_lengkap AS nama_guru,
                                                    k.nama_kelas, j.nama_jurusan, ta.nama_tahun_ajaran,
                                                    t.id_kelas, t.id_jurusan, t.id_tahun_ajaran
                                                FROM tasks t
                                                JOIN guru g ON t.guru_id = g.guru_id
                                                JOIN users u ON g.user_id = u.user_id
                                                LEFT JOIN kelas k ON t.id_kelas = k.id_kelas
                                                LEFT JOIN jurusan j ON t.id_jurusan = j.id_jurusan
                                                LEFT JOIN tahun_ajaran ta ON t.id_tahun_ajaran = ta.id_tahun_ajaran
                                                $where_clause
                                                ORDER BY t.tanggal_berakhir DESC";
                            $result_tasks = mysqli_query($conn, $sql_tasks);

                            if ($result_tasks) { // Pastikan query berhasil
                                if (mysqli_num_rows($result_tasks) > 0) {
                                    while ($task = mysqli_fetch_assoc($result_tasks)) {
                                        $task_id = $task['task_id'];
                                        $id_kelas_target_tugas = $task['id_kelas'];
                                        $id_tahun_ajaran_target_tugas = $task['id_tahun_ajaran'];

                                        // Dapatkan nama kelas dari ID kelas target tugas
                                        $nama_kelas_target_siswa = $kelas_id_to_nama[$id_kelas_target_tugas] ?? '';

                                        // Hitung total siswa untuk kelas (nama kelas) dan tahun ajaran target
                                        $query_total_siswa = mysqli_query($conn, "SELECT COUNT(siswa_id) AS total_siswa 
                                                                                        FROM siswa 
                                                                                        WHERE kelas = '" . mysqli_real_escape_string($conn, $nama_kelas_target_siswa) . "' 
                                                                                        AND id_tahun_ajaran = '$id_tahun_ajaran_target_tugas'
                                                                                        AND status = 'aktif'");
                                        
                                        if ($query_total_siswa) {
                                            $total_siswa_data = mysqli_fetch_assoc($query_total_siswa);
                                            $total_siswa = $total_siswa_data['total_siswa'] ?? 0;
                                        } else {
                                            $total_siswa = 0;
                                            error_log("SQL Error counting total siswa for task_id $task_id: " . mysqli_error($conn));
                                        }


                                        // Hitung jumlah siswa yang sudah mengumpulkan untuk tugas ini
                                        $query_sudah_kumpul = mysqli_query($conn, "SELECT COUNT(DISTINCT siswa_id) AS sudah_kumpul 
                                                                                        FROM submissions 
                                                                                        WHERE task_id = '$task_id'");
                                        
                                        if ($query_sudah_kumpul) {
                                            $sudah_kumpul_data = mysqli_fetch_assoc($query_sudah_kumpul);
                                            $sudah_kumpul = $sudah_kumpul_data['sudah_kumpul'] ?? 0;
                                        } else {
                                            $sudah_kumpul = 0;
                                            error_log("SQL Error counting submitted siswa for task_id $task_id: " . mysqli_error($conn));
                                        }

                                        $belum_kumpul = $total_siswa - $sudah_kumpul;
                                        if ($belum_kumpul < 0) $belum_kumpul = 0;

                                        $kelas_jurusan_display = htmlspecialchars($task['nama_kelas'] ?? '-') . ' (' . htmlspecialchars($task['nama_jurusan'] ?? '-') . ')';
                            ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($task['judul']); ?></td>
                                            <td><?= htmlspecialchars($task['nama_guru']); ?></td>
                                            <td><?= $kelas_jurusan_display; ?></td>
                                            <td><?= htmlspecialchars($task['nama_tahun_ajaran'] ?? '-'); ?></td>
                                            <td><?= date('d-m-Y', strtotime($task['tanggal_berakhir'])); ?></td>
                                            <td><?= $total_siswa; ?></td>
                                            <td><?= $sudah_kumpul; ?></td>
                                            <td><?= $belum_kumpul; ?></td>
                                            <td>
                                                <a href="?page=submission_details&task_id=<?= $task_id; ?>" class="btn btn-info btn-xs" title="Lihat Detail">
                                                    <i class="fa fa-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                            <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='10' class='text-center'>Tidak ada tugas yang ditemukan dengan kriteria filter.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center text-danger'>Terjadi kesalahan dalam mengambil data tugas: " . mysqli_error($conn) . "</td></tr>";
                                error_log("SQL Error in submissions_overview.php (main query): " . mysqli_error($conn));
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
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

    // Panggil fungsi updateJurusanDisplay saat dropdown kelas filter berubah
    $('#filter_kelas').on('change', function() {
        const selectedKelasId = $(this).val();
        let filteredJurusanOptions = '<option value="">Semua Jurusan</option>';
        if (selectedKelasId) {
            const idJurusanTerkait = kelasData[selectedKelasId].id_jurusan;
            if (jurusanData[idJurusanTerkait]) {
                filteredJurusanOptions += `<option value="${idJurusanTerkait}" ${'<?= $filter_jurusan; ?>' == idJurusanTerkait ? 'selected' : ''}>${jurusanData[idJurusanTerkait]}</option>`;
            }
        } else {
            for (const id in jurusanData) {
                filteredJurusanOptions += `<option value="${id}" ${'<?= $filter_jurusan; ?>' == id ? 'selected' : ''}>${jurusanData[id]}</option>`;
            }
        }
        $('#filter_jurusan').html(filteredJurusanOptions);
    }).trigger('change');

});
</script>