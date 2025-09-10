<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../inc/koneksi.php';

function get_user_role() {
    // Implementasikan fungsi ini sesuai dengan struktur otorisasi Anda
    return 'admin_bengkel';
}

function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];
$user_role = get_user_role();
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

if (isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    if ($aksi == 'tambah' || $aksi == 'edit') {
        mysqli_begin_transaction($conn);
        try {
            $nama_sparepart = sanitize_input($_POST['nama_sparepart']);
            $kategori_id = sanitize_input($_POST['kategori_id']);
            $merk_id = sanitize_input($_POST['merk_id']);
            $lokasi_rak = sanitize_input($_POST['lokasi_rak']);
            $harga_beli = (float)sanitize_input($_POST['harga_beli']);
            $satuan_beli_id = sanitize_input($_POST['satuan_beli_id']);
            $isi_per_pcs_beli = (int)sanitize_input($_POST['isi_per_pcs_beli']);
            $stok_pcs = (int)sanitize_input($_POST['stok_pcs']);
            $stok_minimal = (int)sanitize_input($_POST['stok_minimal']);
            $bengkel_id = sanitize_input($_POST['bengkel_id']); // Ambil dari input hidden

            if (!in_array($bengkel_id, $accessible_bengkel_ids)) { throw new Exception("Akses ditolak. Bengkel tidak valid."); }
            $hpp_per_pcs = $isi_per_pcs_beli > 0 ? $harga_beli / $isi_per_pcs_beli : 0;

            if ($aksi == 'tambah') {
                $kode_sparepart = sanitize_input($_POST['kode_sparepart']);
                // Cek jika kode spare part kosong, maka buat otomatis
                if (empty($kode_sparepart)) {
                    $kode_sparepart = 'SP-' . time();
                }

                $query = "INSERT INTO spareparts (kode_sparepart, nama_sparepart, kategori_id, merk_id, lokasi_rak, harga_beli, satuan_beli_id, isi_per_pcs_beli, hpp_per_pcs, stok_pcs, stok_minimal, bengkel_id) VALUES ('$kode_sparepart', '$nama_sparepart', '$kategori_id', '$merk_id', '$lokasi_rak', '$harga_beli', '$satuan_beli_id', '$isi_per_pcs_beli', '$hpp_per_pcs', '$stok_pcs', '$stok_minimal', '$bengkel_id')";
                if (!mysqli_query($conn, $query)) { throw new Exception(mysqli_error($conn)); }
                $last_id = mysqli_insert_id($conn);
                $response = ['status' => 'success', 'message' => 'Spare part berhasil ditambahkan.'];
            } else if ($aksi == 'edit') {
                $id_sparepart = sanitize_input($_POST['id_sparepart']);
                $kode_sparepart = sanitize_input($_POST['kode_sparepart']);
                $query = "UPDATE spareparts SET kode_sparepart = '$kode_sparepart', nama_sparepart = '$nama_sparepart', kategori_id = '$kategori_id', merk_id = '$merk_id', lokasi_rak = '$lokasi_rak', harga_beli = '$harga_beli', satuan_beli_id = '$satuan_beli_id', isi_per_pcs_beli = '$isi_per_pcs_beli', hpp_per_pcs = '$hpp_per_pcs', stok_pcs = '$stok_pcs', stok_minimal = '$stok_minimal', bengkel_id = '$bengkel_id' WHERE id_sparepart = '$id_sparepart' AND bengkel_id IN ('" . implode("','", $accessible_bengkel_ids) . "')";
                if (!mysqli_query($conn, $query)) { throw new Exception(mysqli_error($conn)); }
                $last_id = $id_sparepart;
                // Hapus harga jual yang lama sebelum menambahkan yang baru
                if (!mysqli_query($conn, "DELETE FROM harga_jual_sparepart WHERE sparepart_id = '$last_id'")) { throw new Exception(mysqli_error($conn)); }
                $response = ['status' => 'success', 'message' => 'Spare part berhasil diubah.'];
            }

            for ($i = 1; $i <= 4; $i++) {
                $persentase_jual = (float)($_POST['persentase_jual_' . $i] ?? 0);
                $harga_jual = (float)($_POST['harga_jual_' . $i] ?? 0);
                $satuan_jual_id = sanitize_input($_POST['satuan_jual_' . $i]);
                $isi_per_pcs_jual = (int)($_POST['isi_per_pcs_jual_' . $i] ?? 0);
                
                if (!empty($satuan_jual_id)) {
                    $query_jual = "INSERT INTO harga_jual_sparepart (sparepart_id, tipe_harga, persentase_jual, harga_jual, satuan_jual_id, isi_per_pcs_jual) VALUES ('$last_id', '$i', '$persentase_jual', '$harga_jual', '$satuan_jual_id', '$isi_per_pcs_jual')";
                    if (!mysqli_query($conn, $query_jual)) { throw new Exception(mysqli_error($conn)); }
                }
            }

            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response['message'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else if ($aksi == 'hapus') {
        $id_sparepart = sanitize_input($_POST['id']);
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM harga_jual_sparepart WHERE sparepart_id = '$id_sparepart'");
            $query = "DELETE FROM spareparts WHERE id_sparepart = '$id_sparepart' AND bengkel_id IN ('" . implode("','", $accessible_bengkel_ids) . "')";
            if (!mysqli_query($conn, $query)) { throw new Exception(mysqli_error($conn)); }
            mysqli_commit($conn);
            $response = ['status' => 'success', 'message' => 'Spare part berhasil dihapus.'];
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response['message'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>