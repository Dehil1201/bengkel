<?php
// Pastikan sesi sudah dimulai dan file koneksi disertakan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk membersihkan dan mengamankan input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// ==========================================================
// Pengecekan Akses Berdasarkan Role dan Bengkel ID
// ==========================================================
$user_role = get_user_role();
$allowed_roles = ['owner_bengkel', 'admin_bengkel'];

if (!in_array($user_role, $allowed_roles)) {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit();
}

// Tentukan ID bengkel yang bisa diakses oleh user
$accessible_bengkel_ids = [];
if ($user_role === 'owner_bengkel') {
    $owner_id = $_SESSION['id_user'];
    $query_bengkel_ids = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE owner_id = '$owner_id'");
    while ($row = mysqli_fetch_assoc($query_bengkel_ids)) {
        $accessible_bengkel_ids[] = $row['id_bengkel'];
    }
} else if ($user_role === 'admin_bengkel') {
    $user_id = $_SESSION['id_user'];
    $query_bengkel_admin = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user = '$user_id'");
    if ($row = mysqli_fetch_assoc($query_bengkel_admin)) {
        $accessible_bengkel_ids[] = $row['bengkel_id'];
    }
}

if (empty($accessible_bengkel_ids)) {
    echo "<div class='alert alert-danger'>Anda tidak terdaftar di bengkel manapun.</div>";
    exit();
}
$bengkel_ids_string = "'" . implode("','", $accessible_bengkel_ids) . "'";

// ==========================================================
// Logika Pengambilan Data Riwayat Stok Masuk
// ==========================================================
$query_stok_masuk = "
    SELECT
        sm.tanggal_masuk,
        sm.jumlah_masuk,
        sp.nama_sparepart,
        sup.nama_supplier
    FROM
        stok_masuk AS sm
    LEFT JOIN
        spareparts AS sp ON sm.spare_part_id = sp.id_sparepart
    LEFT JOIN
        suppliers AS sup ON sm.supplier_id = sup.id_supplier
    WHERE
        sm.bengkel_id IN ($bengkel_ids_string)
    ORDER BY
        sm.tanggal_masuk DESC
";
$result_stok_masuk = mysqli_query($conn, $query_stok_masuk);
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Riwayat Stok Masuk</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTableStokMasuk">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal Masuk</th>
                                <th>Nama Spare Part</th>
                                <th>Jumlah</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $has_data = ($result_stok_masuk && mysqli_num_rows($result_stok_masuk) > 0);
                            if ($has_data) {
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($result_stok_masuk)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td>" . htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_masuk']))) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_sparepart']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['jumlah_masuk']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_supplier']) . "</td>";
                                    echo "</tr>";
                                }
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
    // Periksa apakah ada baris data sebelum menginisialisasi DataTables
    $("#dataTableStokMasuk").DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false
        });
});
</script>