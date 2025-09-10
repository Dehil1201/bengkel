<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya admin dan guru yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil task_id dari URL
$task_id = $_GET['task_id'] ?? null;
if (!$task_id || !is_numeric($task_id)) {
    echo "<div class='alert alert-danger'>ID tugas tidak valid.</div>";
    exit();
}

$task_id = mysqli_real_escape_string($conn, $task_id);
$current_user_id = $_SESSION['user_id'] ?? null;
$guru_id_loggedin = null;

// Jika user adalah guru, dapatkan guru_id-nya untuk validasi
if ($user_role === 'guru' && $current_user_id) {
    $query_guru_id = mysqli_query($conn, "SELECT guru_id FROM Guru WHERE user_id = '$current_user_id'");
    if ($row_guru_id = mysqli_fetch_assoc($query_guru_id)) {
        $guru_id_loggedin = $row_guru_id['guru_id'];
    }
}

// Ambil detail tugas dan nama kelas yang terkait
$sql_task_detail = "SELECT 
                        t.task_id, t.judul, t.deskripsi, t.tanggal_dibuat, t.tanggal_berakhir,
                        u.nama_lengkap AS nama_guru,
                        k.nama_kelas, j.nama_jurusan, ta.nama_tahun_ajaran,
                        t.guru_id, t.id_kelas, t.id_tahun_ajaran
                    FROM tasks t
                    JOIN guru g ON t.guru_id = g.guru_id
                    JOIN users u ON g.user_id = u.user_id
                    LEFT JOIN kelas k ON t.id_kelas = k.id_kelas
                    LEFT JOIN jurusan j ON t.id_jurusan = j.id_jurusan
                    LEFT JOIN tahun_ajaran ta ON t.id_tahun_ajaran = ta.id_tahun_ajaran
                    WHERE t.task_id = '$task_id'";
$result_task_detail = mysqli_query($conn, $sql_task_detail);
$task_data = mysqli_fetch_assoc($result_task_detail);

// Validasi tugas ditemukan dan hak akses guru
if (!$task_data) {
    echo "<div class='alert alert-danger'>Tugas tidak ditemukan.</div>";
    exit();
}
if ($user_role === 'guru' && $task_data['guru_id'] != $guru_id_loggedin) {
    echo "<div class='alert alert-danger'>Anda tidak memiliki izin untuk melihat detail tugas ini.</div>";
    exit();
}

// Logika untuk memberikan nilai dan feedback
if (isset($_POST['submit_nilai'])) {
    $siswa_id_nilai = mysqli_real_escape_string($conn, $_POST['siswa_id']);
    $nilai = mysqli_real_escape_string($conn, $_POST['nilai']);
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback']); // Ambil feedback

    // Pastikan nilai valid (contoh: 0-100)
    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) {
        echo "<div class='alert alert-warning'>Nilai harus berupa angka antara 0-100.</div>";
    } else {
        // Cek apakah sudah ada submission
        $query_check_submission = mysqli_query($conn, "SELECT submission_id FROM submissions WHERE task_id = '$task_id' AND siswa_id = '$siswa_id_nilai'");
        if (mysqli_num_rows($query_check_submission) > 0) {
            // Update nilai dan feedback pada submission yang sudah ada
            $update_sql = "UPDATE submissions SET nilai = '$nilai', feedback = '$feedback' WHERE task_id = '$task_id' AND siswa_id = '$siswa_id_nilai'";
            if (mysqli_query($conn, $update_sql)) {
                echo "<script>alert('Nilai dan feedback berhasil diperbarui!'); window.location.href='?page=submission_details&task_id=$task_id';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui nilai dan feedback: " . mysqli_error($conn) . "</div>";
            }
        } else {
             echo "<div class='alert alert-warning'>Siswa ini belum mengumpulkan tugas, nilai tidak bisa diberikan.</div>";
        }
    }
}

// Ambil id_kelas dan id_tahun_ajaran dari detail tugas
$id_kelas_target = $task_data['id_kelas'];
$id_tahun_ajaran_target = $task_data['id_tahun_ajaran'];

// QUERY YANG TELAH DIPERBAIKI
// Menambahkan kolom feedback ke dalam SELECT
$sql_submissions = "SELECT 
                        s.siswa_id, 
                        u.nama_lengkap AS nama_siswa,
                        sub.submission_id,
                        sub.file_path,
                        sub.nilai,
                        sub.feedback
                    FROM siswa s
                    JOIN users u ON s.user_id = u.user_id
                    LEFT JOIN submissions sub ON s.siswa_id = sub.siswa_id AND sub.task_id = '$task_id'
                    WHERE s.kelas = '" . mysqli_real_escape_string($conn, $id_kelas_target) . "'
                    AND s.id_tahun_ajaran = '" . mysqli_real_escape_string($conn, $id_tahun_ajaran_target) . "'
                    AND s.status = 'aktif'
                    ORDER BY u.nama_lengkap ASC";
$result_submissions = mysqli_query($conn, $sql_submissions);

?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Detail Pengumpulan Tugas: <?= htmlspecialchars($task_data['judul']); ?></h3>
                <a href="?page=submissions_overview" class="btn btn-default btn-sm pull-right">Kembali</a>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Informasi Tugas</h4>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr><th>Nama Guru</th><td><?= htmlspecialchars($task_data['nama_guru']); ?></td></tr>
                                <tr><th>Kelas & Jurusan</th><td><?= htmlspecialchars($task_data['nama_kelas'] . ' (' . $task_data['nama_jurusan'] . ')'); ?></td></tr>
                                <tr><th>Tahun Ajaran</th><td><?= htmlspecialchars($task_data['nama_tahun_ajaran']); ?></td></tr>
                                <tr><th>Tanggal Dibuat</th><td><?= date('d-m-Y', strtotime($task_data['tanggal_dibuat'])); ?></td></tr>
                                <tr><th>Deadline</th><td><?= date('d-m-Y', strtotime($task_data['tanggal_berakhir'])); ?></td></tr>
                            </tbody>
                        </table>
                        <h4>Deskripsi Tugas</h4>
                        <p><?= nl2br(htmlspecialchars($task_data['deskripsi'])); ?></p>
                    </div>
                </div>
                
                <hr>

                <h4>Daftar Pengumpulan Siswa</h4>
                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Siswa</th>
                                <th>Status</th>
                                <th>File</th>
                                <th>Nilai</th>
                                <th>Feedback</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if ($result_submissions && mysqli_num_rows($result_submissions) > 0) {
                                while ($submission = mysqli_fetch_assoc($result_submissions)) {
                                    $is_submitted = !empty($submission['submission_id']);
                                    $status_label = $is_submitted ? '<span class="label label-success">Sudah Kumpul</span>' : '<span class="label label-danger">Belum Kumpul</span>';
                            ?>
                                    <tr>
                                        <td valign="top"><?= $no++; ?></td>
                                        <td valign="top"><?= htmlspecialchars($submission['nama_siswa']); ?></td>
                                        <td valign="top"><?= $status_label; ?></td>
                                        <td style="vertical-align: top;" valign="top">
                                            <?php if ($is_submitted): ?>
                                                <a href="<?= htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-primary btn-xs">
                                                    <i class="fa fa-download"></i> Unduh File
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="vertical-align: top;" class="text-center" valign="top">
                                            <?= $is_submitted ? (htmlspecialchars($submission['nilai']) ?? '-') : '-'; ?>
                                        </td>
                                        <td style="vertical-align: top;" valign="top">
                                            <?= $is_submitted ? nl2br(htmlspecialchars($submission['feedback'] ?? '-')) : '-'; ?>
                                        </td>
                                        <td style="vertical-align: top;" valign="top">
                                            <?php if ($is_submitted): ?>
                                                <form action="?page=submission_details&task_id=<?= $task_id; ?>" method="POST" class="form-inline">
                                                    <input type="hidden" name="siswa_id" value="<?= $submission['siswa_id']; ?>">
                                                    <input type="number" name="nilai" value="<?= htmlspecialchars($submission['nilai'] ?? ''); ?>" 
                                                            class="form-control input-sm" style="width: 70px;" min="0" max="100" placeholder="Nilai" required>
                                                    <textarea name="feedback" class="form-control input-sm" placeholder="Feedback" rows="2" style="width: 150px;"><?= htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                                                    <button type="submit" name="submit_nilai" class="btn btn-warning btn-sm">Nilai & Feedback</button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center'>Tidak ada siswa yang terdaftar untuk tugas ini atau kesalahan data.</td></tr>";
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
    $('#data').DataTable({
        "paging": true, "lengthChange": true, "searching": true,
        "ordering": true, "info": true, "autoWidth": true
    });
});
</script>