<?php
// Pastikan koneksi database sudah tersedia dan user adalah siswa
if (function_exists('get_user_role') && get_user_role() !== 'siswa') {
    die("Akses ditolak. Halaman ini hanya untuk siswa.");
}
if (!isset($conn)) {
    die("Error: Koneksi database tidak tersedia.");
}

// Ambil user_id dari sesi
$current_user_id = $_SESSION['user_id'] ?? null;

// Jika user_id tidak ada, hentikan eksekusi
if (empty($current_user_id)) {
    die("Data pengguna tidak valid. Silakan login kembali.");
}

// Ambil ID kelas dari tabel `Siswa` untuk user yang sedang login
// Pastikan nama tabel menggunakan huruf besar 'Siswa' jika itu yang benar di database Anda
$query_siswa = mysqli_query($conn, "
    SELECT s.kelas AS id_kelas_siswa, s.siswa_id 
    FROM Siswa s
    WHERE s.user_id = '$current_user_id'
");
$data_siswa = mysqli_fetch_assoc($query_siswa);

if (!$data_siswa) {
    die("Data siswa tidak ditemukan. Pastikan akun Anda terdaftar di tabel Siswa.");
}

$siswa_id_kelas = $data_siswa['id_kelas_siswa'];
$siswa_id = $data_siswa['siswa_id'];
?>

<section class="content-header">
    <h1>
        Materi Pembelajaran
        <small>Daftar Materi untuk kelas Anda</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Materi Pembelajaran</li>
    </ol>
</section>

<section class="content">
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Daftar Materi Pembelajaran</h3>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Judul Materi</th>
                            <th>Deskripsi</th>
                            <th>Mapel</th>
                            <th>Tahun Ajaran</th>
                            <th>Diunggah Oleh</th>
                            <th>Tanggal Unggah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Ambil semua materi yang ditujukan untuk kelas siswa ini
                        $sql_materi = "
                            SELECT 
                                m.judul, m.deskripsi, m.file_path, m.tanggal_unggah,
                                mp.nama_mapel, ta.nama_tahun_ajaran, u.nama_lengkap AS nama_guru
                            FROM materi m
                            JOIN mapel mp ON m.id_mapel = mp.id_mapel
                            JOIN tahun_ajaran ta ON m.id_tahun_ajaran = ta.id_tahun_ajaran
                            JOIN guru g ON m.guru_id = g.guru_id
                            JOIN Users u ON g.user_id = u.user_id
                            WHERE m.id_kelas = '$siswa_id_kelas'
                            ORDER BY m.tanggal_unggah DESC
                        ";
                        
                        $result_materi = mysqli_query($conn, $sql_materi);
                        if (mysqli_num_rows($result_materi) > 0) {
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($result_materi)) {
                        ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['judul']); ?></td>
                                    <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_mapel']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_tahun_ajaran']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_guru']); ?></td>
                                    <td><?= date('d M Y H:i', strtotime($row['tanggal_unggah'])); ?></td>
                                    <td>
                                        <?php
                                        $file_path = htmlspecialchars($row['file_path']);
                                        if ($file_path) {
                                            echo '<a href="' . $file_path . '" class="btn btn-info btn-xs" target="_blank" title="Lihat/Unduh File"><i class="fa fa-eye"></i> Lihat</a>';
                                        } else {
                                            echo '<span class="text-danger">File tidak tersedia</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Belum ada materi pembelajaran yang diunggah untuk kelas Anda.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    $('#example1').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": true,
        "scrollX": true
    });
});
</script>