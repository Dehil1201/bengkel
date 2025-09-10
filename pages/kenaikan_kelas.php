<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit();
} elseif (!function_exists('get_user_role')) {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan.</div>";
    exit();
}

// Ambil data untuk dropdown Jurusan
$query_jurusan_all = mysqli_query($conn, "SELECT id_jurusan, nama_jurusan FROM Jurusan ORDER BY nama_jurusan ASC");
$jurusan_options_all = [];
while ($row = mysqli_fetch_assoc($query_jurusan_all)) {
    $jurusan_options_all[$row['id_jurusan']] = $row['nama_jurusan'];
}

// Ambil data untuk dropdown Kelas
$query_kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas, id_jurusan FROM kelas ORDER BY nama_kelas ASC");
$kelas_options = [];
$kelas_jurusan_map = []; // Untuk mapping kelas ke jurusan
while ($row = mysqli_fetch_assoc($query_kelas)) {
    $kelas_options[$row['id_kelas']] = $row['nama_kelas'];
    $kelas_jurusan_map[$row['id_kelas']] = $row['id_jurusan'];
}

// Ambil data untuk dropdown Tahun Ajaran
$query_tahun_ajaran = mysqli_query($conn, "SELECT id_tahun_ajaran, nama_tahun_ajaran FROM tahun_ajaran ORDER BY nama_tahun_ajaran DESC");
$tahun_ajaran_options = [];
$current_tahun_ajaran_id = null;

$query_active_ta = mysqli_query($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1");
if ($row_active_ta = mysqli_fetch_assoc($query_active_ta)) {
    $current_tahun_ajaran_id = $row_active_ta['id_tahun_ajaran'];
}

$all_tahun_ajaran = [];
while ($row = mysqli_fetch_assoc($query_tahun_ajaran)) {
    $all_tahun_ajaran[$row['id_tahun_ajaran']] = $row['nama_tahun_ajaran'];
}

// Tentukan kelas tertinggi (Ini perlu disesuaikan dengan struktur kelasmu, misal "XII" untuk SMA)
// Contoh: mencari kelas yang namanya mengandung "XII" atau "3" (untuk SMK kelas 3)
// Atau kamu bisa tambahkan kolom is_highest_grade di tabel kelas
$highest_class_id = null;
$highest_class_name = '';

// Cara 1: Asumsi kelas tertinggi adalah yang terakhir secara alfabet (misal XII)
// $query_highest_class = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas DESC LIMIT 1");
// if ($row_highest = mysqli_fetch_assoc($query_highest_class)) {
//     $highest_class_id = $row_highest['id_kelas'];
//     $highest_class_name = $row_highest['nama_kelas'];
// }
// Cara 2: Lebih spesifik jika penamaan kelas tidak konsisten
$query_highest_class_specific = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas WHERE nama_kelas LIKE '%XII%' OR nama_kelas LIKE '%3%' ORDER BY nama_kelas DESC LIMIT 1");
if ($row_highest = mysqli_fetch_assoc($query_highest_class_specific)) {
    $highest_class_id = $row_highest['id_kelas'];
    $highest_class_name = $row_highest['nama_kelas'];
}


// Logika Proses Kenaikan Kelas atau Meluluskan Siswa
if (isset($_POST['proses_kenaikan_kelas']) || isset($_POST['proses_kelulusan_siswa'])) {
    $kelas_asal_id = mysqli_real_escape_string($conn, $_POST['kelas_asal']);
    $siswa_terpilih = $_POST['siswa_terpilih'] ?? []; // Array of siswa_id

    if (empty($siswa_terpilih)) {
        echo "<div class='alert alert-warning'>Tidak ada siswa yang dipilih.</div>";
    } else {
        $success_count = 0;
        $fail_count = 0;

        if (isset($_POST['proses_kenaikan_kelas'])) {
            $kelas_tujuan_id = mysqli_real_escape_string($conn, $_POST['kelas_tujuan']);
            $tahun_ajaran_tujuan_id = mysqli_real_escape_string($conn, $_POST['tahun_ajaran_tujuan']);

            // Validasi: Pastikan kelas tujuan berada dalam jurusan yang sama dengan kelas asal
            $jurusan_kelas_asal = $kelas_jurusan_map[$kelas_asal_id] ?? null;
            $jurusan_kelas_tujuan = $kelas_jurusan_map[$kelas_tujuan_id] ?? null;

            if ($jurusan_kelas_asal === null || $jurusan_kelas_tujuan === null || $jurusan_kelas_asal != $jurusan_kelas_tujuan) {
                echo "<div class='alert alert-danger'>Kelas tujuan harus berada di jurusan yang sama dengan kelas asal siswa!</div>";
            } elseif ($kelas_asal_id == $kelas_tujuan_id && $selected_tahun_ajaran_filter == $tahun_ajaran_tujuan_id) {
                echo "<div class='alert alert-danger'>Kelas dan Tahun Ajaran tujuan tidak boleh sama dengan kelas dan tahun ajaran asal.</div>";
            } else {
                foreach ($siswa_terpilih as $siswa_id) {
                    $siswa_id = mysqli_real_escape_string($conn, $siswa_id);
                    $update_sql = mysqli_query($conn, "UPDATE Siswa SET kelas = '$kelas_tujuan_id', id_tahun_ajaran = '$tahun_ajaran_tujuan_id' WHERE siswa_id = '$siswa_id' AND kelas = '$kelas_asal_id'");
                    if ($update_sql) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }
                }
                if ($success_count > 0) {
                    echo "<script>alert('$success_count siswa berhasil dinaikkan kelasnya ke " . htmlspecialchars($kelas_options[$kelas_tujuan_id]) . " untuk Tahun Ajaran " . htmlspecialchars($all_tahun_ajaran[$tahun_ajaran_tujuan_id]) . "!');window.location.href='?page=kenaikan_kelas';</script>";
                }
                if ($fail_count > 0) {
                    echo "<div class='alert alert-warning'>$fail_count siswa gagal dinaikkan kelasnya.</div>";
                }
            }
        } elseif (isset($_POST['proses_kelulusan_siswa'])) {
            foreach ($siswa_terpilih as $siswa_id) {
                $siswa_id = mysqli_real_escape_string($conn, $siswa_id);
                $check_siswa_kelas = mysqli_query($conn, "SELECT kelas FROM Siswa WHERE siswa_id = '$siswa_id'");
                $siswa_current_kelas = mysqli_fetch_assoc($check_siswa_kelas)['kelas'] ?? null;

                if ($siswa_current_kelas == $highest_class_id) { // Hanya luluskan jika dari kelas tertinggi
                    $update_sql = mysqli_query($conn, "UPDATE Siswa SET status = 'lulus', kelas = NULL, id_tahun_ajaran = NULL WHERE siswa_id = '$siswa_id'");
                    if ($update_sql) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }
                } else {
                    $fail_count++;
                }
            }
            if ($success_count > 0) {
                echo "<script>alert('$success_count siswa berhasil diluluskan dan statusnya menjadi alumni!');window.location.href='?page=kenaikan_kelas';</script>";
            }
            if ($fail_count > 0) {
                echo "<div class='alert alert-warning'>$fail_count siswa gagal diluluskan (mungkin bukan dari kelas tertinggi).</div>";
            }
        }
    }
}

// Logika untuk menampilkan siswa berdasarkan filter
$selected_kelas_filter = $_POST['filter_kelas'] ?? '';
$selected_tahun_ajaran_filter = $_POST['filter_tahun_ajaran'] ?? ($current_tahun_ajaran_id ?? '');

$siswa_data_display = [];
$jurusan_kelas_asal_terpilih = null;

if (!empty($selected_kelas_filter)) {
    // Dapatkan ID jurusan dari kelas asal yang dipilih
    $jurusan_kelas_asal_terpilih = $kelas_jurusan_map[$selected_kelas_filter] ?? null;

    $sql_siswa_filter = "SELECT s.siswa_id, u.nama_lengkap, s.nisn, k.nama_kelas, ta.nama_tahun_ajaran, s.status
                         FROM Siswa s
                         JOIN Users u ON s.user_id = u.user_id
                         JOIN kelas k ON s.kelas = k.id_kelas
                         JOIN tahun_ajaran ta ON s.id_tahun_ajaran = ta.id_tahun_ajaran
                         WHERE s.kelas = '$selected_kelas_filter' AND s.status = 'aktif'";
    
    if (!empty($selected_tahun_ajaran_filter)) {
        $sql_siswa_filter .= " AND s.id_tahun_ajaran = '$selected_tahun_ajaran_filter'";
    }

    $sql_siswa_filter .= " ORDER BY u.nama_lengkap ASC";
    
    $result_siswa_filter = mysqli_query($conn, $sql_siswa_filter);
    if ($result_siswa_filter) {
        while ($row = mysqli_fetch_assoc($result_siswa_filter)) {
            $siswa_data_display[] = $row;
        }
    } else {
        echo "<div class='alert alert-danger'>Error mengambil data siswa: " . mysqli_error($conn) . "</div>";
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Kenaikan Kelas & Kelulusan Siswa</h3>
            </div>
            <div class="box-body">
                <form method="POST" action="?page=kenaikan_kelas">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="filter_kelas">Pilih Kelas Asal:</label>
                                <select class="form-control" id="filter_kelas" name="filter_kelas" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelas_options as $id => $nama): ?>
                                        <option value="<?= $id; ?>" <?= ($selected_kelas_filter == $id) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($nama); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="filter_tahun_ajaran">Pilih Tahun Ajaran Asal:</label>
                                <select class="form-control" id="filter_tahun_ajaran" name="filter_tahun_ajaran">
                                    <option value="">-- Semua Tahun Ajaran --</option>
                                    <?php foreach ($all_tahun_ajaran as $id => $nama): ?>
                                        <option value="<?= $id; ?>" <?= ($selected_tahun_ajaran_filter == $id) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($nama); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih tahun ajaran untuk memfilter siswa.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label><br>
                                <button type="submit" class="btn btn-primary">Tampilkan Siswa</button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if (!empty($selected_kelas_filter) && !empty($siswa_data_display)): ?>
                <hr>
                <form method="POST" action="?page=kenaikan_kelas">
                    <input type="hidden" name="kelas_asal" value="<?= htmlspecialchars($selected_kelas_filter); ?>">
                    <input type="hidden" name="filter_tahun_ajaran" value="<?= htmlspecialchars($selected_tahun_ajaran_filter); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <h4>Siswa di Kelas <?= htmlspecialchars($kelas_options[$selected_kelas_filter]); ?> (Jurusan: <?= htmlspecialchars($jurusan_options_all[$jurusan_kelas_asal_terpilih] ?? 'Tidak Diketahui'); ?>)</h4>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="check_all_siswa"></th>
                                            <th>NISN</th>
                                            <th>Nama Lengkap</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswa_data_display as $siswa): ?>
                                            <tr>
                                                <td><input type="checkbox" name="siswa_terpilih[]" value="<?= $siswa['siswa_id']; ?>" class="siswa_checkbox"></td>
                                                <td><?= htmlspecialchars($siswa['nisn']); ?></td>
                                                <td><?= htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                                <td><span class="label label-<?= ($siswa['status'] == 'aktif') ? 'success' : 'info'; ?>"><?= htmlspecialchars(ucfirst($siswa['status'])); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($selected_kelas_filter != $highest_class_id): // Jika bukan kelas tertinggi, tampilkan opsi kenaikan kelas ?>
                                <div class="form-group">
                                    <label for="kelas_tujuan">Naikkan ke Kelas:</label>
                                    <select class="form-control" id="kelas_tujuan" name="kelas_tujuan" required>
                                        <option value="">-- Pilih Kelas Tujuan --</option>
                                        <?php 
                                        foreach ($kelas_options as $id => $nama): 
                                            // Tampilkan hanya kelas yang satu jurusan DAN ID-nya lebih besar dari kelas asal (asumsi ID kelas berurutan)
                                            if ($id != $selected_kelas_filter && ($kelas_jurusan_map[$id] ?? null) == $jurusan_kelas_asal_terpilih):
                                                echo '<option value="' . $id . '">' . htmlspecialchars($nama) . '</option>';
                                            endif;
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Hanya menampilkan kelas dalam jurusan yang sama.</small>
                                </div>
                                <div class="form-group">
                                    <label for="tahun_ajaran_tujuan">Untuk Tahun Ajaran:</label>
                                    <select class="form-control" id="tahun_ajaran_tujuan" name="tahun_ajaran_tujuan" required>
                                        <option value="">-- Pilih Tahun Ajaran Tujuan --</option>
                                        <?php 
                                        foreach ($all_tahun_ajaran as $id => $nama): 
                                            // Tampilkan semua tahun ajaran yang lebih baru dari tahun ajaran asal
                                            if ($id > $selected_tahun_ajaran_filter) { // Asumsi ID tahun ajaran berurutan
                                                echo '<option value="' . $id . '">' . htmlspecialchars($nama) . '</option>';
                                            }
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Pilih tahun ajaran dimana siswa akan berada di kelas tujuan.</small>
                                </div>
                                <button type="submit" name="proses_kenaikan_kelas" class="btn btn-primary btn-lg btn-block" onclick="return confirm('Yakin ingin memproses kenaikan kelas siswa yang dipilih ke tahun ajaran selanjutnya?')">
                                    Proses Kenaikan Kelas
                                </button>
                                <p class="text-muted text-center" style="margin-top: 10px;">
                                    <i class="fa fa-info-circle"></i> Pastikan Anda sudah memilih kelas tujuan dan tahun ajaran tujuan.
                                </p>
                            <?php else: // Jika ini adalah kelas tertinggi, tampilkan opsi kelulusan ?>
                                <div class="alert alert-info">
                                    Siswa di kelas **<?= htmlspecialchars($highest_class_name); ?>** adalah kelas tertinggi. Anda dapat meluluskan mereka.
                                </div>
                                <button type="submit" name="proses_kelulusan_siswa" class="btn btn-success btn-lg btn-block" onclick="return confirm('Yakin ingin meluluskan siswa yang dipilih? Status mereka akan menjadi alumni.')">
                                    Luluskan Siswa (Jadikan Alumni)
                                </button>
                                <p class="text-muted text-center" style="margin-top: 10px;">
                                    <i class="fa fa-info-circle"></i> Siswa yang diluluskan akan memiliki status 'lulus' dan tidak akan muncul di daftar siswa aktif.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                <?php elseif (!empty($selected_kelas_filter) && empty($siswa_data_display)): ?>
                    <div class="alert alert-info">Tidak ada siswa aktif ditemukan di kelas dan tahun ajaran yang dipilih.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#check_all_siswa').on('click', function() {
        $('.siswa_checkbox').prop('checked', $(this).prop('checked'));
    });

    <?php if (!empty($selected_kelas_filter)): ?>
        $('#filter_kelas').val('<?= htmlspecialchars($selected_kelas_filter); ?>');
    <?php endif; ?>
    <?php if (!empty($selected_tahun_ajaran_filter)): ?>
        $('#filter_tahun_ajaran').val('<?= htmlspecialchars($selected_tahun_ajaran_filter); ?>');
    <?php endif; ?>
});
</script>