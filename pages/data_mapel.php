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

// Logika Tambah/Edit/Hapus Mata Pelajaran

// --- Logika Hapus Mata Pelajaran ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_mapel_to_delete = mysqli_real_escape_string($conn, $_GET['id']);

    $delete_sql = mysqli_query($conn, "DELETE FROM mapel WHERE id_mapel = '$id_mapel_to_delete'");
    if ($delete_sql) {
        echo "<script>alert('Data mata pelajaran berhasil dihapus!');window.location.href='?page=data_mapel';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data mata pelajaran: " . mysqli_error($conn) . "');window.location.href='?page=data_mapel';</script>";
    }
}

// --- Logika Tambah/Edit Mata Pelajaran (Form Processing) ---
if (isset($_POST['submit_mapel'])) {
    $id_mapel_form = mysqli_real_escape_string($conn, $_POST['id_mapel'] ?? '');
    $nama_mapel = mysqli_real_escape_string($conn, $_POST['nama_mapel']);

    if (empty($id_mapel_form)) { // Mode Tambah
        // Cek duplikasi nama mata pelajaran
        $check_nama_mapel = mysqli_query($conn, "SELECT id_mapel FROM mapel WHERE nama_mapel = '$nama_mapel'");
        if (mysqli_num_rows($check_nama_mapel) > 0) {
            echo "<div class='alert alert-danger'>Nama mata pelajaran ini sudah ada!</div>";
        } else {
            $insert_sql = mysqli_query($conn, "INSERT INTO mapel (nama_mapel) VALUES ('$nama_mapel')");
            if ($insert_sql) {
                echo "<script>alert('Mata pelajaran baru berhasil ditambahkan!');window.location.href='?page=data_mapel';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal menambahkan mata pelajaran: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit
        // Cek duplikasi nama mata pelajaran, kecuali untuk dirinya sendiri
        $check_nama_mapel = mysqli_query($conn, "SELECT id_mapel FROM mapel WHERE nama_mapel = '$nama_mapel' AND id_mapel != '$id_mapel_form'");
        if (mysqli_num_rows($check_nama_mapel) > 0) {
            echo "<div class='alert alert-danger'>Nama mata pelajaran ini sudah digunakan!</div>";
        } else {
            $update_sql = mysqli_query($conn, "UPDATE mapel SET nama_mapel = '$nama_mapel' WHERE id_mapel = '$id_mapel_form'");
            if ($update_sql) {
                echo "<script>alert('Data mata pelajaran berhasil diperbarui!');window.location.href='?page=data_mapel';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui mata pelajaran: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Mata Pelajaran</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#mapelModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Mata Pelajaran
                </button>
            </div>
            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $sql_mapel = mysqli_query($conn, "SELECT * FROM mapel ORDER BY nama_mapel ASC");
                        while ($data = mysqli_fetch_assoc($sql_mapel)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['nama_mapel']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#mapelModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_mapel']; ?>"
                                        data-nama_mapel="<?= htmlspecialchars($data['nama_mapel']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=data_mapel&action=delete&id=<?= $data['id_mapel']; ?>" onclick="return confirm('Yakin ingin menghapus mata pelajaran ini?')" class="btn btn-danger btn-xs" title="Hapus">
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

<div class="modal fade" id="mapelModal" tabindex="-1" role="dialog" aria-labelledby="mapelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="mapelModalLabel">Form Mata Pelajaran</h4>
            </div>
            <form id="mapelForm" method="POST" action="?page=data_mapel">
                <div class="modal-body">
                    <input type="hidden" name="id_mapel" id="id_mapel_modal">
                    <div class="form-group">
                        <label for="nama_mapel">Nama Mata Pelajaran:</label>
                        <input type="text" class="form-control" id="nama_mapel_modal" name="nama_mapel" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_mapel" class="btn btn-primary">Simpan</button>
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
    $('#mapelModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Tombol yang memicu modal
        var action = button.data('action');  // Ambil nilai dari data-action (add/edit)
        var modal = $(this);

        // Reset form setiap kali modal dibuka
        $('#mapelForm')[0].reset();
        $('#id_mapel_modal').val(''); // Kosongkan ID

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Mata Pelajaran Baru');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Mata Pelajaran');

            // Ambil data dari tombol edit
            var id_mapel = button.data('id');
            var nama_mapel = button.data('nama_mapel');

            // Isi form modal dengan data yang akan diedit
            $('#id_mapel_modal').val(id_mapel);
            $('#nama_mapel_modal').val(nama_mapel);
        }
    });
});
</script>