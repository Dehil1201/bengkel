<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php

// Cek hak akses: hanya admin yang bisa mengakses halaman ini
// Pastikan fungsi get_user_role() sudah didefinisikan (biasanya di inc/fungsi.php atau sejenisnya)
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit(); // Hentikan eksekusi script
} elseif (!function_exists('get_user_role')) {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Logika Tambah/Edit/Hapus Tahun Ajaran

// --- Logika Hapus Tahun Ajaran ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_ta_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

    $delete_sql = mysqli_query($conn, "DELETE FROM tahun_ajaran WHERE id_tahun_ajaran = '$id_ta_to_delete'");
    if ($delete_sql) {
        echo "<script>alert('Data tahun ajaran berhasil dihapus!');window.location.href='?page=data_tahun_ajaran';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data tahun ajaran: " . mysqli_error($conn) . "');window.location.href='?page=data_tahun_ajaran';</script>";
    }
}

// --- Logika Tambah/Edit Tahun Ajaran (Form Processing) ---
if (isset($_POST['submit_tahun_ajaran'])) {
    $id_ta_form = mysqli_real_escape_string($conn, $_POST['id_tahun_ajaran'] ?? '');
    $nama_tahun_ajaran = mysqli_real_escape_string($conn, $_POST['nama_tahun_ajaran']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Cek apakah ada tahun ajaran lain yang aktif jika yang ini akan diset aktif
    if ($status == 'aktif') {
        // Nonaktifkan tahun ajaran sebelumnya yang aktif (jika ada)
        // Kecuali jika itu adalah tahun ajaran yang sedang diedit dan memang sudah aktif
        $update_status_lain = mysqli_query($conn, "UPDATE tahun_ajaran SET status = 'nonaktif' WHERE status = 'aktif' AND id_tahun_ajaran != '$id_ta_form'");
        if (!$update_status_lain) {
            // Error ini mungkin tidak fatal, tapi bagus untuk tahu
            // echo "<div class='alert alert-warning'>Peringatan: Gagal menonaktifkan tahun ajaran sebelumnya secara otomatis, mungkin tidak ada yang aktif atau terjadi kesalahan database.</div>";
        }
    }

    if (empty($id_ta_form)) { // Mode Tambah
        // Cek duplikasi nama tahun ajaran
        $check_nama_ta = mysqli_query($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE nama_tahun_ajaran = '$nama_tahun_ajaran'");
        if (mysqli_num_rows($check_nama_ta) > 0) {
            echo "<div class='alert alert-danger'>Nama tahun ajaran ini sudah ada!</div>";
        } else {
            $insert_sql = mysqli_query($conn, "INSERT INTO tahun_ajaran (nama_tahun_ajaran, status) VALUES ('$nama_tahun_ajaran', '$status')");
            if ($insert_sql) {
                echo "<script>alert('Tahun ajaran baru berhasil ditambahkan!');window.location.href='?page=data_tahun_ajaran';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal menambahkan tahun ajaran: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit
        // Cek duplikasi nama tahun ajaran, kecuali untuk dirinya sendiri
        $check_nama_ta = mysqli_query($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE nama_tahun_ajaran = '$nama_tahun_ajaran' AND id_tahun_ajaran != '$id_ta_form'");
        if (mysqli_num_rows($check_nama_ta) > 0) {
            echo "<div class='alert alert-danger'>Nama tahun ajaran ini sudah digunakan!</div>";
        } else {
            $update_sql = mysqli_query($conn, "UPDATE tahun_ajaran SET nama_tahun_ajaran = '$nama_tahun_ajaran', status = '$status' WHERE id_tahun_ajaran = '$id_ta_form'");
            if ($update_sql) {
                echo "<script>alert('Data tahun ajaran berhasil diperbarui!');window.location.href='?page=data_tahun_ajaran';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui tahun ajaran: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Tahun Ajaran</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#taModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Tahun Ajaran
                </button>
            </div>
            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Tahun Ajaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $sql_ta = mysqli_query($conn, "SELECT * FROM tahun_ajaran ORDER BY nama_tahun_ajaran DESC");
                        while ($data = mysqli_fetch_assoc($sql_ta)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['nama_tahun_ajaran']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = ($data['status'] == 'aktif') ? 'bg-green' : 'bg-red';
                                    echo '<span class="badge ' . $badge_class . '">' . ucfirst(htmlspecialchars($data['status'])) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#taModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_tahun_ajaran']; ?>"
                                        data-nama_tahun_ajaran="<?= htmlspecialchars($data['nama_tahun_ajaran']); ?>"
                                        data-status="<?= htmlspecialchars($data['status']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=data_tahun_ajaran&action=delete&id=<?= $data['id_tahun_ajaran']; ?>" onclick="return confirm('Yakin ingin menghapus tahun ajaran ini?')" class="btn btn-danger btn-xs" title="Hapus">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="taModal" tabindex="-1" role="dialog" aria-labelledby="taModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="taModalLabel">Form Tahun Ajaran</h4>
            </div>
            <form id="taForm" method="POST" action="?page=data_tahun_ajaran">
                <div class="modal-body">
                    <input type="hidden" name="id_tahun_ajaran" id="id_tahun_ajaran_modal">
                    <div class="form-group">
                        <label for="nama_tahun_ajaran">Nama Tahun Ajaran (contoh: 2024/2025):</label>
                        <input type="text" class="form-control" id="nama_tahun_ajaran_modal" name="nama_tahun_ajaran" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select class="form-control" id="status_modal" name="status" required>
                            <option value="">-- Pilih Status --</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <small class="text-info">Menyetel status 'Aktif' akan menonaktifkan tahun ajaran lainnya.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_tahun_ajaran" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#data').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": true,
        "scrollX": true
    });

    // Event listener saat modal ditampilkan
    $('#taModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Tombol yang memicu modal
        var action = button.data('action');  // Ambil nilai dari data-action (add/edit)
        var modal = $(this);

        // Reset form setiap kali modal dibuka
        $('#taForm')[0].reset();
        $('#id_tahun_ajaran_modal').val(''); // Kosongkan ID

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Tahun Ajaran Baru');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Tahun Ajaran');

            // Ambil data dari tombol edit
            var id_tahun_ajaran = button.data('id');
            var nama_tahun_ajaran = button.data('nama_tahun_ajaran');
            var status = button.data('status');

            // Isi form modal dengan data yang akan diedit
            $('#id_tahun_ajaran_modal').val(id_tahun_ajaran);
            $('#nama_tahun_ajaran_modal').val(nama_tahun_ajaran);
            $('#status_modal').val(status);
        }
    });
});
</script>