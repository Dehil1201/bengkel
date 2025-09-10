<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php (INI PENTING SEKALI!)

// Cek hak akses: hanya siswa yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'siswa') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

$current_user_id = $_SESSION['user_id'] ?? null;
$siswa_id_loggedin = null;

if ($current_user_id) {
    $query_siswa_id = mysqli_query($conn, "SELECT siswa_id FROM Siswa WHERE user_id = '$current_user_id'");
    if ($row_siswa_id = mysqli_fetch_assoc($query_siswa_id)) {
        $siswa_id_loggedin = $row_siswa_id['siswa_id'];
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Kuis Tersedia</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Judul Kuis</th>
                                <th>Deskripsi</th>
                                <th>Durasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Query untuk mengambil semua kuis dan status pengerjaan oleh siswa saat ini
                            $sql_quizzes = "SELECT 
                                                K.kuis_id,
                                                K.judul_kuis,
                                                K.deskripsi,
                                                K.durasi_menit,
                                                COUNT(JS.jawaban_id) AS total_jawaban
                                            FROM Kuis K
                                            LEFT JOIN JawabanSiswa JS ON K.kuis_id = JS.kuis_id AND JS.siswa_id = '" . mysqli_real_escape_string($conn, $siswa_id_loggedin) . "'
                                            GROUP BY K.kuis_id
                                            ORDER BY K.kuis_id DESC";
                            
                            $result_quizzes = mysqli_query($conn, $sql_quizzes);

                            if ($result_quizzes && mysqli_num_rows($result_quizzes) > 0) {
                                while ($kuis = mysqli_fetch_assoc($result_quizzes)) {
                                    $is_done = ($kuis['total_jawaban'] > 0);
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($kuis['judul_kuis']); ?></td>
                                        <td><?= nl2br(htmlspecialchars($kuis['deskripsi'] ?? 'Tidak ada deskripsi.')); ?></td>
                                        <td><?= htmlspecialchars($kuis['durasi_menit']); ?> menit</td>
                                        <td>
                                            <?php if ($is_done) : ?>
                                                <span class="label label-success">Sudah Dikerjakan</span>
                                            <?php else : ?>
                                                <span class="label label-warning">Belum Dikerjakan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_done) : ?>
                                                <button type="button" class="btn btn-default btn-xs" disabled>Lihat Hasil</button>
                                            <?php else : ?>
                                                <a href="?page=take_quiz&kuis_id=<?= $kuis['kuis_id']; ?>" class="btn btn-success btn-xs">
                                                    <i class="fa fa-play"></i> Kerjakan Kuis
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Belum ada kuis yang tersedia.</td></tr>";
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
        "ordering": true, "info": true, "autoWidth": true, "scrollX": true,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
        }
    });
});
</script>