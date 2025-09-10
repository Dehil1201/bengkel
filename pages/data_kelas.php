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
$query_jurusan = mysqli_query($conn, "SELECT id_jurusan, nama_jurusan FROM Jurusan ORDER BY nama_jurusan ASC");
$jurusan_options = [];
while ($row = mysqli_fetch_assoc($query_jurusan)) {
    $jurusan_options[$row['id_jurusan']] = $row['nama_jurusan'];
}

// Logika Tambah/Edit/Hapus Kelas
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_kelas_to_delete = mysqli_real_escape_string($conn, $_GET['id']);
    $delete_sql = mysqli_query($conn, "DELETE FROM kelas WHERE id_kelas = '$id_kelas_to_delete'");
    if ($delete_sql) {
        echo "<script>alert('Data kelas berhasil dihapus!');window.location.href='?page=data_kelas';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data kelas: " . mysqli_error($conn) . "');window.location.href='?page=data_kelas';</script>";
    }
}

if (isset($_POST['submit_kelas'])) {
    $id_kelas_form = mysqli_real_escape_string($conn, $_POST['id_kelas'] ?? '');
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $id_jurusan = mysqli_real_escape_string($conn, $_POST['id_jurusan']); // Data baru: id_jurusan

    if (empty($id_kelas_form)) { // Mode Tambah
        $check_nama_kelas = mysqli_query($conn, "SELECT id_kelas FROM kelas WHERE nama_kelas = '$nama_kelas'");
        if (mysqli_num_rows($check_nama_kelas) > 0) {
            echo "<div class='alert alert-danger'>Nama kelas ini sudah ada!</div>";
        } else {
            $insert_sql = mysqli_query($conn, "INSERT INTO kelas (nama_kelas, id_jurusan) VALUES ('$nama_kelas', '$id_jurusan')"); // Insert id_jurusan
            if ($insert_sql) {
                echo "<script>alert('Kelas baru berhasil ditambahkan!');window.location.href='?page=data_kelas';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal menambahkan kelas: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit
        $check_nama_kelas = mysqli_query($conn, "SELECT id_kelas FROM kelas WHERE nama_kelas = '$nama_kelas' AND id_kelas != '$id_kelas_form'");
        if (mysqli_num_rows($check_nama_kelas) > 0) {
            echo "<div class='alert alert-danger'>Nama kelas ini sudah digunakan!</div>";
        } else {
            $update_sql = mysqli_query($conn, "UPDATE kelas SET nama_kelas = '$nama_kelas', id_jurusan = '$id_jurusan' WHERE id_kelas = '$id_kelas_form'"); // Update id_jurusan
            if ($update_sql) {
                echo "<script>alert('Data kelas berhasil diperbarui!');window.location.href='?page=data_kelas';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui kelas: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Kelas</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#kelasModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Kelas
                </button>
            </div>
            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Kelas</th>
                            <th>Jurusan</th> <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        // Join dengan tabel Jurusan untuk menampilkan nama jurusan
                        $sql_kelas = mysqli_query($conn, "SELECT k.*, j.nama_jurusan FROM kelas k LEFT JOIN Jurusan j ON k.id_jurusan = j.id_jurusan ORDER BY k.nama_kelas ASC");
                        while ($data = mysqli_fetch_assoc($sql_kelas)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['nama_kelas']); ?></td>
                                <td><?= htmlspecialchars($data['nama_jurusan'] ?? '-'); ?></td> <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#kelasModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_kelas']; ?>"
                                        data-nama_kelas="<?= htmlspecialchars($data['nama_kelas']); ?>"
                                        data-id_jurusan="<?= htmlspecialchars($data['id_jurusan']); ?>" title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=data_kelas&action=delete&id=<?= $data['id_kelas']; ?>" onclick="return confirm('Yakin ingin menghapus kelas ini?')" class="btn btn-danger btn-xs" title="Hapus">
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

<div class="modal fade" id="kelasModal" tabindex="-1" role="dialog" aria-labelledby="kelasModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="kelasModalLabel">Form Kelas</h4>
            </div>
            <form id="kelasForm" method="POST" action="?page=data_kelas">
                <div class="modal-body">
                    <input type="hidden" name="id_kelas" id="id_kelas_modal">
                    <div class="form-group">
                        <label for="nama_kelas">Nama Kelas (contoh: X IPA 1):</label>
                        <input type="text" class="form-control" id="nama_kelas_modal" name="nama_kelas" required>
                    </div>
                    <div class="form-group">
                        <label for="id_jurusan">Jurusan:</label>
                        <select class="form-control" id="id_jurusan_modal" name="id_jurusan" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($jurusan_options as $id => $nama): ?>
                                <option value="<?= $id; ?>"><?= htmlspecialchars($nama); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_kelas" class="btn btn-primary">Simpan</button>
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

    $('#kelasModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);
        $('#kelasForm')[0].reset();
        $('#id_kelas_modal').val('');

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Kelas Baru');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Kelas');
            var id_kelas = button.data('id');
            var nama_kelas = button.data('nama_kelas');
            var id_jurusan = button.data('id_jurusan'); // Ambil id_jurusan
            
            $('#id_kelas_modal').val(id_kelas);
            $('#nama_kelas_modal').val(nama_kelas);
            $('#id_jurusan_modal').val(id_jurusan); // Set nilai dropdown jurusan
        }
    });
});
</script>