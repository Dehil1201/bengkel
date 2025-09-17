<?php
include '../koneksi.php';

// Tangkap filter dari URL (GET)
$filter_jenis = $_GET['filter_jenis'] ?? '';
$filter_nama  = $_GET['filter_nama'] ?? '';

// Bangun WHERE clause dinamis
$where = "WHERE 1=1";
if (!empty($filter_jenis)) {
    $where .= " AND jenis_akun = '" . mysqli_real_escape_string($conn, $filter_jenis) . "'";
}
if (!empty($filter_nama)) {
    $where .= " AND nama_akun LIKE '%" . mysqli_real_escape_string($conn, $filter_nama) . "%'";
}

// Ambil data akun berdasarkan filter
$daftar_akun = mysqli_query($conn, "SELECT * FROM akun $where ORDER BY kode_akun ASC");

// Proses simpan data akun baru
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_akun'])) {
    $kode_akun   = mysqli_real_escape_string($conn, $_POST['kode_akun']);
    $nama_akun   = mysqli_real_escape_string($conn, $_POST['nama_akun']);
    $jenis_akun  = mysqli_real_escape_string($conn, $_POST['jenis_akun']);
    $keterangan  = mysqli_real_escape_string($conn, $_POST['keterangan']);

    $cek = mysqli_query($conn, "SELECT * FROM akun WHERE kode_akun = '$kode_akun'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Kode akun sudah digunakan.";
    } else {
        $simpan = mysqli_query($conn, "INSERT INTO akun (kode_akun, nama_akun, jenis_akun, keterangan, id_bengkel) 
                                       VALUES ('$kode_akun', '$nama_akun', '$jenis_akun', '$keterangan', '$id_bengkel')");
        if ($simpan) {
            $success = "Data akun berhasil disimpan.";
        } else {
            $error = "Gagal menyimpan data akun.";
        }
    }
}

// Script Swal untuk notif dan reload
$swal_script = '';
if (!empty($success)) {
    $swal_script = "
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Sukses',
            text: '" . addslashes($success) . "',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            window.location.href = '?page=akun';
        });
    </script>
    ";
} elseif (!empty($error)) {
    $swal_script = "
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '" . addslashes($error) . "',
            confirmButtonText: 'OK'
        });
    </script>
    ";
}

$jenis_list = ['Kas', 'Bank', 'Hutang', 'Piutang', 'Modal', 'Pendapatan', 'Beban'];
?>

<div class="box box-primary">
    <div class="box-header with-border clearfix">
        <h3 class="box-title">Manajemen Akun</h3>
        <button class="btn btn-success pull-right" data-toggle="modal" data-target="#modalTambahAkun">
            <i class="fa fa-plus"></i> Tambah Akun
        </button>
    </div>

    <div class="box-body">
        <!-- FORM FILTER -->
        <form method="get" class="form-inline" style="margin-bottom: 20px;">
            <div class="form-group">
                <label for="filter_jenis">Jenis Akun</label>
                <select name="filter_jenis" id="filter_jenis" class="form-control" style="margin-left: 10px; margin-right: 15px;">
                    <option value="">-- Semua --</option>
                    <?php foreach ($jenis_list as $jenis): ?>
                        <option value="<?= $jenis ?>" <?= $filter_jenis == $jenis ? 'selected' : '' ?>><?= $jenis ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="filter_nama">Nama Akun</label>
                <input type="text" name="filter_nama" id="filter_nama" class="form-control" placeholder="Cari nama..." value="<?= htmlspecialchars($filter_nama) ?>" style="margin-left: 10px;">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-left: 15px;">Filter</button>
            <a href="akun.php" class="btn btn-default" style="margin-left: 10px;">Reset</a>
        </form>

        <!-- TABEL DATA -->
        <table class="table table-bordered table-striped" id="tabelAkun" style="width: 100%;">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Jenis</th>
                    <th>Keterangan</th>
                    <th style="width: 90px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($daftar_akun)): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['kode_akun']) ?></td>
                        <td><?= htmlspecialchars($a['nama_akun']) ?></td>
                        <td><?= htmlspecialchars($a['jenis_akun']) ?></td>
                        <td><?= htmlspecialchars($a['keterangan']) ?></td>
                        <td>
                            <a href="hapus_akun.php?id=<?= $a['id_akun'] ?>" onclick="return confirm('Hapus akun ini?')" class="btn btn-danger btn-sm" title="Hapus">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Akun -->
<div class="modal fade" id="modalTambahAkun" tabindex="-1" role="dialog" aria-labelledby="modalTambahAkunLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h4 class="modal-title" id="modalTambahAkunLabel">Tambah Akun</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="kode_akun">Kode Akun</label>
                        <input type="text" name="kode_akun" id="kode_akun" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_akun">Nama Akun</label>
                        <input type="text" name="nama_akun" id="nama_akun" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="jenis_akun">Jenis Akun</label>
                        <select name="jenis_akun" id="jenis_akun" class="form-control" required>
                            <option value="">-- Pilih Jenis --</option>
                            <?php foreach ($jenis_list as $jenis): ?>
                                <option value="<?= $jenis ?>"><?= $jenis ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <textarea name="keterangan" id="keterangan" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button name="simpan_akun" type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(function () {
        $('#tabelAkun').DataTable();
    });
</script>

<?= $swal_script ?>
