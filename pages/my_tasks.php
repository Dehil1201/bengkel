<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya siswa yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'siswa') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat daftar tugas.</div></div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil user_id dari session untuk siswa yang login
$current_user_id = $_SESSION['user_id'] ?? null;
$siswa_id_loggedin = null;
$siswa_kelas_loggedin = null;
$siswa_tahun_ajaran_loggedin = null;

// Jika user adalah siswa, dapatkan siswa_id, kelas, dan tahun ajaran
if ($user_role === 'siswa' && $current_user_id) {
    $query_siswa_data = mysqli_query($conn, "SELECT siswa_id, kelas, id_tahun_ajaran FROM siswa WHERE user_id = '$current_user_id'");
    if ($row_siswa_data = mysqli_fetch_assoc($query_siswa_data)) {
        $siswa_id_loggedin = $row_siswa_data['siswa_id'];
        $siswa_kelas_loggedin = $row_siswa_data['kelas']; // Nama kelas (string)
        $siswa_tahun_ajaran_loggedin = $row_siswa_data['id_tahun_ajaran'];
    } else {
        echo "<div class='alert alert-danger'>Error: Data siswa Anda tidak ditemukan. Silakan hubungi administrator.</div>";
        exit();
    }
}

// Logika Filter (jika diperlukan, meskipun untuk siswa biasanya tidak ada filter)
$filter_tahun_ajaran = $_GET['filter_tahun_ajaran'] ?? $siswa_tahun_ajaran_loggedin; // Default filter tahun ajaran

$where_clause = "WHERE 1"; // Kondisi awal
if ($siswa_kelas_loggedin) {
    // Cari id_kelas berdasarkan nama kelas siswa yang login
    $query_id_kelas = mysqli_query($conn, "SELECT id_kelas FROM kelas WHERE nama_kelas = '" . mysqli_real_escape_string($conn, $siswa_kelas_loggedin) . "'");
    if ($row_id_kelas = mysqli_fetch_assoc($query_id_kelas)) {
        $id_kelas_target = $row_id_kelas['id_kelas'];
        $where_clause .= " AND t.id_kelas = '" . mysqli_real_escape_string($conn, $id_kelas_target) . "'";
    }
}
if (!empty($filter_tahun_ajaran)) {
    $where_clause .= " AND t.id_tahun_ajaran = '" . mysqli_real_escape_string($conn, $filter_tahun_ajaran) . "'";
}
// Tambahkan filter untuk tugas yang tanggalnya masih berlaku
$today = date('Y-m-d');
$where_clause .= " AND t.tanggal_berakhir >= '$today'"; // Hanya tampilkan tugas yang belum lewat deadline


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

?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Tugas Saya</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Judul Tugas</th>
                                <th>Guru Pembuat</th>
                                <th>Tanggal Mulai</th>
                                <th>Deadline</th>
                                <th>Tahun Ajaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Query untuk mendapatkan daftar tugas siswa
                            $sql_tasks = "SELECT 
                                t.task_id, t.judul, t.tanggal_mulai, t.tanggal_berakhir,
                                u.nama_lengkap AS nama_guru,
                                ta.nama_tahun_ajaran
                            FROM tasks t
                            JOIN guru g ON t.guru_id = g.guru_id
                            JOIN users u ON g.user_id = u.user_id
                            LEFT JOIN tahun_ajaran ta ON t.id_tahun_ajaran = ta.id_tahun_ajaran
                            $where_clause
                            ORDER BY t.tanggal_berakhir ASC";
                            $result_tasks = mysqli_query($conn, $sql_tasks);

                            if ($result_tasks) { // Pastikan query berhasil
                                if (mysqli_num_rows($result_tasks) > 0) {
                                    while ($data = mysqli_fetch_assoc($result_tasks)) {
                                        $task_id = $data['task_id'];
                                        
                                        // Cek apakah siswa sudah mengumpulkan tugas ini
                                        $query_submission = mysqli_query($conn, "SELECT submission_id FROM submissions WHERE task_id = '$task_id' AND siswa_id = '$siswa_id_loggedin'");
                                        $submission_status = mysqli_num_rows($query_submission) > 0 ? "<span class='label label-success'>Sudah Dikumpulkan</span>" : "<span class='label label-warning'>Belum Dikumpulkan</span>";
                                    ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($data['judul']); ?></td>
                                            <td><?= htmlspecialchars($data['nama_guru']); ?></td>
                                            <td><?= date('d-m-Y', strtotime($data['tanggal_mulai'])); ?></td>
                                            <td><?= date('d-m-Y', strtotime($data['tanggal_berakhir'])); ?></td>
                                            <td><?= htmlspecialchars($data['nama_tahun_ajaran'] ?? '-'); ?></td>
                                            <td><?= $submission_status; ?></td>
                                            <td>
                                                <a href="?page=submission_form&task_id=<?= $task_id; ?>" class="btn btn-primary btn-xs" title="Lihat Detail & Kumpul">
                                                    <i class="fa fa-pencil"></i> Kumpulkan
                                                </a>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center'>Tidak ada tugas yang ditujukan untuk Anda saat ini.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center text-danger'>Terjadi kesalahan dalam mengambil data tugas: " . mysqli_error($conn) . "</td></tr>";
                                error_log("SQL Error in my_tasks.php: " . mysqli_error($conn));
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
});
</script>