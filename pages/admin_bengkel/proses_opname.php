<?php
// Pastikan sesi sudah dimulai dan file koneksi disertakan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Sesuaikan jalur file koneksi dan functions
include '../../inc/koneksi.php';
include '../../inc/functions.php';

// Atur header untuk respons JSON
header('Content-Type: application/json');

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
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke halaman ini.']);
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
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak terdaftar di bengkel manapun.']);
    exit();
}
$bengkel_ids_string = "'" . implode("','", $accessible_bengkel_ids) . "'";

// ==========================================================
// LOGIKA PEMROSESAN STOK OPNAME
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'simpan_opname') {
        $data_opname = json_decode($_POST['data_opname'], true);
        $bengkel_id = sanitize_input($_POST['bengkel_id']);
        
        if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Bengkel tidak valid.']);
            exit();
        }

        $tanggal_opname = date('Y-m-d H:i:s');
        $success_count = 0;
        $error_count = 0;
        $error_messages = [];

        foreach ($data_opname as $item) {
            $spare_part_id = sanitize_input($item['spare_part_id']);
            $stok_fisik = (int)sanitize_input($item['stok_fisik']);
            $keterangan = sanitize_input($item['keterangan']);

            // Dapatkan stok dari sistem
            // Ubah nama tabel dari `spareparts` menjadi `spareparts`
            // Ubah nama kolom id dari `id_sparepart` menjadi `id`
            $query_get_stok = "SELECT stok_pcs, bengkel_id FROM spareparts WHERE id_sparepart = '$spare_part_id'";
            $result_get_stok = mysqli_query($conn, $query_get_stok);
            $row_stok = mysqli_fetch_assoc($result_get_stok);

            if ($row_stok && in_array($row_stok['bengkel_id'], $accessible_bengkel_ids)) {
                $stok_sistem = (int)$row_stok['stok_pcs'];
                $selisih = $stok_fisik - $stok_sistem;

                // Transaksi: Mulai
                mysqli_begin_transaction($conn);
                try {
                    // Simpan ke tabel stok_opnames
                    $query_insert_opname = "INSERT INTO stok_opnames (tanggal_opname, spare_part_id, stok_sistem, stok_fisik, selisih, keterangan, bengkel_id) VALUES ('$tanggal_opname', '$spare_part_id', '$stok_sistem', '$stok_fisik', '$selisih', '$keterangan', '$bengkel_id')";
                    
                    if (mysqli_query($conn, $query_insert_opname)) {
                        // Perbarui stok di tabel spareparts
                        // Ubah nama tabel dari `spareparts` menjadi `spareparts`
                        // Ubah nama kolom id dari `id_sparepart` menjadi `id`
                        $query_update_stok = "UPDATE spareparts SET stok_pcs = '$stok_fisik' WHERE id_sparepart = '$spare_part_id'";
                        if (mysqli_query($conn, $query_update_stok)) {
                            mysqli_commit($conn);
                            $success_count++;
                        } else {
                            throw new Exception("Gagal memperbarui stok sparepart: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Gagal menyimpan data opname: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_count++;
                    $error_messages[] = $e->getMessage();
                }
            } else {
                $error_count++;
                $error_messages[] = "Spare part dengan ID '$spare_part_id' tidak ditemukan atau tidak valid.";
            }
        }
        
        $message = "Stok Opname Selesai. Berhasil: $success_count, Gagal: $error_count.";
        if (!empty($error_messages)) {
            $message .= " Detail: " . implode(", ", $error_messages);
        }

        $status = ($error_count > 0) ? 'warning' : 'success';
        echo json_encode(['status' => $status, 'message' => $message]);
        exit();
    }
}

// Tambahkan logika jika request bukan POST atau action tidak valid
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
?>