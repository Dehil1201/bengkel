<?php
// Pastikan koneksi database sudah tersedia dari file yang meng-include ini
// Pastikan juga session_start() sudah aktif

// Cek hak akses: hanya super_user yang bisa mengakses halaman ini
if (get_user_role() !== 'super_user') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit();
}

// Logika Tambah/Edit/Hapus Data Bengkel
// --- Logika Hapus Bengkel ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_bengkel_to_delete = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Perubahan: Menggunakan soft delete (mengubah status menjadi non_aktif)
    $update_sql = mysqli_query($conn, "UPDATE bengkels SET status = 'non_aktif' WHERE id_bengkel = '$id_bengkel_to_delete'");
    if ($update_sql) {
        echo "<script>alert('Data bengkel berhasil dinonaktifkan!');window.location.href='?page=management_bengkel';</script>";
    } else {
        echo "<script>alert('Gagal menonaktifkan data bengkel: " . mysqli_error($conn) . "');window.location.href='?page=management_bengkel';</script>";
    }
}

// --- Logika Tambah/Edit Bengkel (Form Processing) ---
if (isset($_POST['submit_bengkel'])) {
    $id_bengkel_form = mysqli_real_escape_string($conn, $_POST['id_bengkel'] ?? '');
    $nama_bengkel = mysqli_real_escape_string($conn, $_POST['nama_bengkel']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $owner_id = mysqli_real_escape_string($conn, $_POST['owner_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $timestamp = date("Y-m-d H:i:s");

    if (empty($id_bengkel_form)) { // Mode Tambah
        // Cek duplikasi nama bengkel
        $check_bengkel = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE nama_bengkel = '$nama_bengkel'");
        if (mysqli_num_rows($check_bengkel) > 0) {
            echo "<div class='alert alert-danger'>Nama bengkel sudah ada! Mohon gunakan nama lain.</div>";
        } else {
            $insert_sql = mysqli_query($conn, "INSERT INTO bengkels (nama_bengkel, telepon, alamat, owner_id, status, created_at, updated_at) VALUES ('$nama_bengkel', '$telepon', '$alamat', '$owner_id', '$status', '$timestamp', '$timestamp')");
            if ($insert_sql) {
                echo "<script>alert('Data bengkel berhasil ditambahkan!');window.location.href='?page=management_bengkel';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal menambahkan data bengkel: " . mysqli_error($conn) . "</div>";
            }
        }
    } else { // Mode Edit
        // Cek duplikasi nama bengkel, kecuali untuk bengkel yang sedang diedit
        $check_bengkel = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE nama_bengkel = '$nama_bengkel' AND id_bengkel != '$id_bengkel_form'");
        if (mysqli_num_rows($check_bengkel) > 0) {
            echo "<div class='alert alert-danger'>Nama bengkel sudah digunakan!</div>";
        } else {
            $update_sql = mysqli_query($conn, "UPDATE bengkels SET nama_bengkel = '$nama_bengkel', telepon = '$telepon', alamat = '$alamat', owner_id = '$owner_id', status = '$status', updated_at = '$timestamp' WHERE id_bengkel = '$id_bengkel_form'");
            if ($update_sql) {
                echo "<script>alert('Data bengkel berhasil diperbarui!');window.location.href='?page=management_bengkel';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal memperbarui data bengkel: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Bengkel</h3>
                <button type="button" class="btn btn-success btn-sm pull-right" data-toggle="modal" data-target="#bengkelModal" data-action="add">
                    <i class="fa fa-plus"></i> Tambah Bengkel
                </button>
            </div>
            
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="filter_owner">Filter Berdasarkan Owner:</label>
                            <select class="form-control" id="filter_owner">
                                <option value="">-- Tampilkan Semua --</option>
                                <?php
                                $sql_owners = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users WHERE role = 'owner_bengkel' ORDER BY nama_lengkap ASC");
                                while ($owner = mysqli_fetch_assoc($sql_owners)) {
                                    echo "<option value='" . htmlspecialchars($owner['nama_lengkap']) . "'>" . htmlspecialchars($owner['nama_lengkap']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="filter_status">Filter Berdasarkan Status:</label>
                            <select class="form-control" id="filter_status">
                                <option value="">-- Tampilkan Semua --</option>
                                <option value="aktif">Aktif</option>
                                <option value="non_aktif">Non Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box-body table-responsive">
                <table id="data" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Bengkel</th>
                            <th>Owner</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $sql_bengkel = mysqli_query($conn, "SELECT b.*, u.nama_lengkap as owner_nama FROM bengkels b JOIN users u ON b.owner_id = u.id_user ORDER BY b.nama_bengkel ASC");
                        while ($data = mysqli_fetch_assoc($sql_bengkel)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data['nama_bengkel']); ?></td>
                                <td><?= htmlspecialchars($data['owner_nama']); ?></td>
                                <td><?= htmlspecialchars($data['telepon']); ?></td>
                                <td><?= htmlspecialchars($data['alamat']); ?></td>
                                <td><span class="label <?= ($data['status'] == 'aktif') ? 'label-success' : 'label-danger'; ?>"><?= htmlspecialchars($data['status']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-xs edit-btn" data-toggle="modal" data-target="#bengkelModal"
                                        data-action="edit"
                                        data-id="<?= $data['id_bengkel']; ?>"
                                        data-nama_bengkel="<?= htmlspecialchars($data['nama_bengkel']); ?>"
                                        data-telepon="<?= htmlspecialchars($data['telepon']); ?>"
                                        data-alamat="<?= htmlspecialchars($data['alamat']); ?>"
                                        data-owner_id="<?= htmlspecialchars($data['owner_id']); ?>"
                                        data-status="<?= htmlspecialchars($data['status']); ?>"
                                        title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?page=management_bengkel&action=delete&id=<?= $data['id_bengkel']; ?>" onclick="return confirm('Yakin ingin menonaktifkan bengkel ini?')" class="btn btn-danger btn-xs" title="Nonaktifkan">
                                        <i class="fa fa-power-off"></i>
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

<div class="modal fade" id="bengkelModal" tabindex="-1" role="dialog" aria-labelledby="bengkelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="bengkelModalLabel">Form Bengkel</h4>
            </div>
            <form id="bengkelForm" method="POST" action="?page=management_bengkel">
                <div class="modal-body">
                    <input type="hidden" name="id_bengkel" id="id_bengkel">
                    <div class="form-group">
                        <label for="nama_bengkel">Nama Bengkel:</label>
                        <input type="text" class="form-control" id="nama_bengkel_modal" name="nama_bengkel" required>
                    </div>
                    <div class="form-group">
                        <label for="telepon">Telepon:</label>
                        <input type="text" class="form-control" id="telepon_modal" name="telepon" required>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat:</label>
                        <textarea class="form-control" id="alamat_modal" name="alamat" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="owner_id">Owner Bengkel:</label>
                        <select class="form-control" id="owner_id_modal" name="owner_id" required>
                            <option value="">-- Pilih Owner --</option>
                            <?php
                            $sql_owners = mysqli_query($conn, "SELECT id_user, nama_lengkap FROM users WHERE role = 'owner_bengkel' ORDER BY nama_lengkap ASC");
                            while ($owner = mysqli_fetch_assoc($sql_owners)) {
                                echo "<option value='" . htmlspecialchars($owner['id_user']) . "'>" . htmlspecialchars($owner['nama_lengkap']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select class="form-control" id="status_modal" name="status" required>
                            <option value="aktif">Aktif</option>
                            <option value="non_aktif">Non Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_bengkel" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    var table = $('#data').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": true,
        "scrollX": true
    });

    // Event listener untuk filter berdasarkan owner
    $('#filter_owner').on('change', function() {
        var owner_name = $(this).val();
        table.column(2).search(owner_name).draw();
    });

    // Event listener untuk filter berdasarkan status
    $('#filter_status').on('change', function() {
        var status = $(this).val();
        table.column(5).search(status).draw();
    });

    // Event listener saat modal ditampilkan
    $('#bengkelModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        var modal = $(this);

        $('#bengkelForm')[0].reset();
        $('#id_bengkel').val('');

        if (action === 'add') {
            modal.find('.modal-title').text('Tambah Bengkel Baru');
            // Default status untuk penambahan adalah 'aktif'
            $('#status_modal').val('aktif');
        } else if (action === 'edit') {
            modal.find('.modal-title').text('Edit Data Bengkel');

            var id_bengkel = button.data('id');
            var nama_bengkel = button.data('nama_bengkel');
            var telepon = button.data('telepon');
            var alamat = button.data('alamat');
            var owner_id = button.data('owner_id');
            var status = button.data('status');

            $('#id_bengkel').val(id_bengkel);
            $('#nama_bengkel_modal').val(nama_bengkel);
            $('#telepon_modal').val(telepon);
            $('#alamat_modal').val(alamat);
            $('#owner_id_modal').val(owner_id);
            $('#status_modal').val(status);
        }
    });
});
</script>