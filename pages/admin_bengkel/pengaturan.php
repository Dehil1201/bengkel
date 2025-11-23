<?php

$id_user = $_SESSION['id_user'];
$qbengkel = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user'");
$dbengkel = mysqli_fetch_array($qbengkel);
$id_bengkel = $dbengkel['bengkel_id'];
$id_bengkel_login = $id_bengkel; // AMBIL ID BENGKEL DARI LOGIN

// ==========================
//  PROSES UPDATE (POST)
// ==========================
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $nama_bengkel = mysqli_real_escape_string($conn, $_POST['nama_bengkel']);
    $alamat    = mysqli_real_escape_string($conn, $_POST['alamat']);
    $telepon      = mysqli_real_escape_string($conn, $_POST['telepon']);

    // Cek apakah data sudah ada
    $cek = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE id_bengkel='$id_bengkel' LIMIT 1");

    if (mysqli_num_rows($cek) > 0) {

        $sql = "UPDATE bengkels 
                SET nama_bengkel='$nama_bengkel', alamat='$alamat', telepon='$telepon'
                WHERE id_bengkel='$id_bengkel'";

    } else {

        $sql = "INSERT INTO bengkels (id_bengkel, nama_bengkel, alamat, telepon)
                VALUES ('$id_bengkel', '$nama_bengkel', '$alamat', '$telepon')";
    }

    if (mysqli_query($conn, $sql)) {
        $alert = "<script>Swal.fire('Berhasil', 'Pengaturan berhasil disimpan', 'success');</script>";
    } else {
        $alert = "<script>Swal.fire('Error', 'Gagal menyimpan data: " . mysqli_error($conn) . "', 'error');</script>";
    }
}

// ==========================
//  LOAD DATA UNTUK FORM
// ==========================
$q = mysqli_query($conn, "SELECT * FROM bengkels WHERE id_bengkel='$id_bengkel' LIMIT 1");


if ($cek = mysqli_num_rows($q) > 0) {
    $row = mysqli_fetch_assoc($q);
    $nama_bengkel = $row['nama_bengkel'];
    $alamat    = $row['alamat'];
    $telepon      = $row['telepon'];
} else {
    $nama_bengkel = "";
    $alamat    = "";
    $telepon      = "";
    echo $cek;
}

?>

<!-- Include SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php 
// Tampilkan notifikasi jika ada
if (!empty($alert)) echo $alert;
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Pengaturan</h3>
            </div>

            <div class="box-body">

                <form method="POST">

                    <table class="table table-bordered" style="border: none;">
                        <tbody>

                            <tr>
                                <td style="width: 200px; vertical-align: middle;"><b>Nama Toko</b></td>
                                <td style="width: 20px; vertical-align: middle;">:</td>
                                <td>
                                    <input type="text" name="nama_bengkel" class="form-control" value="<?= $nama_bengkel ?>">
                                </td>
                            </tr>

                            <tr>
                                <td style="vertical-align: top;"><b>Alamat</b></td>
                                <td style="vertical-align: top;">:</td>
                                <td>
                                    <textarea name="alamat" class="form-control" rows="3"><?= $alamat ?></textarea>
                                </td>
                            </tr>

                            <tr>
                                <td style="vertical-align: middle;"><b>No telepon/HP</b></td>
                                <td style="vertical-align: middle;">:</td>
                                <td>
                                    <input type="text" name="telepon" class="form-control" value="<?= $telepon ?>">
                                </td>
                            </tr>

                        </tbody>
                    </table>

                    <div class="text-right" style="padding-top: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Simpan
                        </button>
                    </div>

                </form>

            </div> <!-- /.box-body -->
        </div> <!-- /.box -->
    </div> <!-- /.col -->
</div>
