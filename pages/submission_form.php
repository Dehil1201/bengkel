<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php
// Pastikan fungsi.php sudah ter-include untuk get_user_role()

// Cek hak akses: hanya siswa yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'siswa') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk mengunggah tugas.</div></div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Ambil user_id dan siswa_id dari session untuk siswa yang login
$current_user_id = $_SESSION['user_id'] ?? null;
$siswa_id_loggedin = null;
if ($current_user_id) {
    $query_siswa_id = mysqli_query($conn, "SELECT siswa_id FROM siswa WHERE user_id = '$current_user_id'");
    if ($row = mysqli_fetch_assoc($query_siswa_id)) {
        $siswa_id_loggedin = $row['siswa_id'];
    }
}

// Ambil task_id dari parameter URL
$task_id = $_GET['task_id'] ?? '';
$error = '';
$success = '';

// Ambil detail tugas dari database
$task = null;
if (!empty($task_id)) {
    $sql_task = "SELECT t.judul, t.tanggal_berakhir, u.nama_lengkap AS nama_guru
                 FROM tasks t
                 JOIN guru g ON t.guru_id = g.guru_id
                 JOIN users u ON g.user_id = u.user_id
                 WHERE t.task_id = '" . mysqli_real_escape_string($conn, $task_id) . "'";
    $result_task = mysqli_query($conn, $sql_task);
    if ($result_task && mysqli_num_rows($result_task) > 0) {
        $task = mysqli_fetch_assoc($result_task);
    } else {
        $error = "Tugas tidak ditemukan.";
    }
} else {
    $error = "Parameter task_id tidak valid.";
}

// Cek apakah siswa sudah mengumpulkan tugas ini
$is_submitted = false;
$submission_file_path = null;
if ($task && $siswa_id_loggedin) {
    $query_submission = mysqli_query($conn, "SELECT file_path FROM submissions WHERE task_id = '$task_id' AND siswa_id = '$siswa_id_loggedin'");
    if (mysqli_num_rows($query_submission) > 0) {
        $is_submitted = true;
        $submission_data = mysqli_fetch_assoc($query_submission);
        $submission_file_path = $submission_data['file_path'];
    }
}

// Cek jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_submitted) {
        $deadline = new DateTime($task['tanggal_berakhir']);
        $now = new DateTime();
        if ($now > $deadline) {
            $error = "Maaf, batas waktu pengumpulan tugas sudah habis.";
        } else if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['submission_file'];
            $file_name = uniqid('submission_') . '_' . basename($file['name']);
            $upload_dir = 'uploads/submissions/';
            
            // Pastikan direktori ada
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Simpan data pengumpulan ke database
                $submitted_at = date('Y-m-d H:i:s');
                $sql_insert = "INSERT INTO submissions (task_id, siswa_id, file_path, submitted_at) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql_insert);
                mysqli_stmt_bind_param($stmt, "ssss", $task_id, $siswa_id_loggedin, $file_path, $submitted_at);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Tugas berhasil diunggah!";
                    $is_submitted = true;
                    $submission_file_path = $file_path;
                    // Logika redirect jika diperlukan
                    // header("Location: ?page=my_tasks");
                    // exit();
                } else {
                    $error = "Terjadi kesalahan saat menyimpan data pengumpulan: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Terjadi kesalahan saat mengunggah file. Coba lagi.";
            }
        } else {
            $error = "Mohon pilih file untuk diunggah.";
        }
    } else {
        $error = "Anda sudah mengumpulkan tugas ini. Tidak dapat mengunggah lagi.";
    }
}

?>

<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Form Pengumpulan Tugas</h3>
            </div>
            <div class="box-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($task): ?>
                    <h4><?= htmlspecialchars($task['judul']); ?></h4>
                    <p><b>Guru:</b> <?= htmlspecialchars($task['nama_guru']); ?></p>
                    <p><b>Deadline:</b> <?= date('d-m-Y H:i', strtotime($task['tanggal_berakhir'])); ?></p>
                    <hr>

                    <?php if ($is_submitted): ?>
                        <div class="alert alert-success">
                            Anda sudah mengumpulkan tugas ini.
                            <br>File yang Anda kumpulkan: <a href="<?= htmlspecialchars($submission_file_path); ?>" target="_blank"><?= basename($submission_file_path); ?></a>
                        </div>
                    <?php else: ?>
                        <?php
                        $deadline_dt = new DateTime($task['tanggal_berakhir']);
                        $now_dt = new DateTime();
                        if ($now_dt > $deadline_dt):
                        ?>
                            <div class="alert alert-danger">Maaf, batas waktu pengumpulan tugas ini sudah habis.</div>
                        <?php else: ?>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="submission_file">Pilih File Tugas:</label>
                                    <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Unggah Tugas</button>
                                    <a href="?page=my_tasks" class="btn btn-default">Kembali</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-warning">Detail tugas tidak dapat ditampilkan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>