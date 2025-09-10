<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit();
} elseif (!function_exists('get_user_role')) {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan.</div>";
    exit();
}

// Logika Tambah/Edit/Hapus Jurusan
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_jurusan_to_delete = mysqli_real_escape_string($conn, $_GET['id']);
    $delete_sql = mysqli_query($conn, "DELETE FROM Jurusan WHERE id_jurusan = '$id_jurusan_to_delete'");
    if ($delete_sql) {
        echo "<script>alert('Data jurusan berhasil dihapus! Kelas yang terhubung akan kehilangan data jurusan.');window.location.href='?page=data_jurusan';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data jurusan: " . mysqli_error($conn) . "');window.location.href='?page=data_jurusan';</script>";
    }
}

if (isset($_POST['submit_jurusan'])) {
    $id_jurusan_form = mysqli_real_escape_string($conn, $_POST['id_jurusan'] ?? '');
    $nama_jurusan = mysqli_real_escape_string($conn, $_POST['nama_jurusan']);

    if (empty($id_jurusan_form)) { // Mode Tambah
        $check_nama_jurusan = mysqli_query($conn, "SELECT id_jurusan FROM Jurusan WHERE nama_jurusan = '$nama_jurusan'");
        if (mysqli_num_rows($check_nama_jurusan) > 0) {
            echo "<div class='alert alert-danger'>Nama jurusan ini sudah ada!</div>";
        } else {
            $insert_sql = mysqli_query($conn, "INSERT INTO Jurusan (nama_jurusan) VALUES ('$nama_jurusan')");
            if ($insert_sql) {
                echo "<script>alert('Jurusan baru berhasil ditambahkan!');window.location.href='?page=data_jurusan';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal menambahkan jurusan: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit
        $check_nama_jurusan = mysqli_query($conn, "SELECT id_jurusan FROM Jurusan WHERE nama_jurusan = '$nama_jurusan' AND id_jurusan != '$id_jurusan_form'");
        if (mysqli_num_rows($check_nama_jurusan) > 0) {
            echo "<div class='alert alert-danger'>Nama jurusan ini sudah digunakan!</div>";
        } else {
            $update_sql = mysqli_query($conn, "UPDATE Jurusan SET nama_jurusan = '$nama_jurusan' WHERE id_jurusan = '$id_jurusan_form'");
            if ($update_sql) {
                echo "<script>alert('Data jurusan berhasil diperbarui!');window.location.href='?page=data_jurusan';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui jurusan: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Jurusan</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#jurusanModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Jurusan
                </button>
            </div>
            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Jurusan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $sql_jurusan = mysqli_query($conn, "SELECT * FROM Jurusan ORDER BY nama_jurusan ASC");
                        while ($data = mysqli_fetch_assoc($sql_jurusan)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['nama_jurusan']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#jurusanModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_jurusan']; ?>"
                                        data-nama_jurusan="<?= htmlspecialchars($data['nama_jurusan']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=data_jurusan&action=delete&id=<?= $data['id_jurusan']; ?>" onclick="return confirm('Yakin ingin menghapus jurusan ini? Ini akan membuat kelas yang terhubung kehilangan data jurusan.')" class="btn btn-danger btn-xs" title="Hapus">
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

<div class="modal fade" id="jurusanModal" tabindex="-1" role="dialog" aria-labelledby="jurusanModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="jurusanModalLabel">Form Jurusan</h4>
            </div>
            <form id="jurusanForm" method="POST" action="?page=data_jurusan">
                <div class="modal-body">
                    <input type="hidden" name="id_jurusan" id="id_jurusan_modal">
                    <div class="form-group">
                        <label for="nama_jurusan">Nama Jurusan:</label>
                        <input type="text" class="form-control" id="nama_jurusan_modal" name="nama_jurusan" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_jurusan" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#data').DataTable({
        "paging": true, "lengthChange": true, "searching": true,
        "ordering": true, "info": true, "autoWidth": true, "scrollX": true
    });

    $('#jurusanModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);
        $('#jurusanForm')[0].reset();
        $('#id_jurusan_modal').val('');

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Jurusan Baru');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Jurusan');
            var id_jurusan = button.data('id');
            var nama_jurusan = button.data('nama_jurusan');
            $('#id_jurusan_modal').val(id_jurusan);
            $('#nama_jurusan_modal').val(nama_jurusan);
        }
    });
});
</script>