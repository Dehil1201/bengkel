<?php
// ==========================================================
// INIT
// ==========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../inc/koneksi.php';
include '../../inc/functions.php';

header('Content-Type: application/json');

// ==========================================================
// HELPER
// ==========================================================
function sanitize($val) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($val));
}

function response($status, $message) {
    echo json_encode([
        'success' => $status === 'success',
        'status'  => $status,
        'message' => $message
    ]);
    exit;
}

// ==========================================================
// VALIDASI DASAR
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Invalid request method');
}

if (!isset($_SESSION['id_user'])) {
    response('error', 'Session tidak valid');
}

// ==========================================================
// ROLE & AKSES
// ==========================================================
$user_role = get_user_role();
if (!in_array($user_role, ['owner_bengkel', 'admin_bengkel'])) {
    response('error', 'Anda tidak memiliki akses');
}

$user_id = $_SESSION['id_user'];
$accessible_bengkel_ids = [];

// Owner → banyak bengkel
if ($user_role === 'owner_bengkel') {
    $q = mysqli_query($conn, "
        SELECT id_bengkel 
        FROM bengkels 
        WHERE owner_id = '$user_id'
    ");
    while ($r = mysqli_fetch_assoc($q)) {
        $accessible_bengkel_ids[] = $r['id_bengkel'];
    }
}

// Admin → satu bengkel
if ($user_role === 'admin_bengkel') {
    $q = mysqli_query($conn, "
        SELECT bengkel_id 
        FROM users 
        WHERE id_user = '$user_id'
        LIMIT 1
    ");
    if ($r = mysqli_fetch_assoc($q)) {
        $accessible_bengkel_ids[] = $r['bengkel_id'];
    }
}

if (empty($accessible_bengkel_ids)) {
    response('error', 'Anda tidak terdaftar di bengkel manapun');
}

// ==========================================================
// ACTION
// ==========================================================
if (($_POST['action'] ?? '') !== 'simpan_opname') {
    response('error', 'Action tidak valid');
}

// ==========================================================
// INPUT
// ==========================================================
$bengkel_id = sanitize($_POST['bengkel_id'] ?? '');
if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
    response('error', 'Akses bengkel ditolak');
}

$data_opname = json_decode($_POST['data_opname'] ?? '', true);
if (!is_array($data_opname) || empty($data_opname)) {
    response('error', 'Data opname kosong');
}

// ==========================================================
// PROSES
// ==========================================================
$tanggal_full = date('Y-m-d H:i:s');
$tanggal_hari = date('Y-m-d');

$success = 0;
$failed  = 0;

mysqli_begin_transaction($conn);

try {

    foreach ($data_opname as $item) {

        $sparepart_id = sanitize($item['spare_part_id'] ?? '');
        $stok_fisik   = (int)($item['stok_fisik'] ?? 0);
        $keterangan   = sanitize($item['keterangan'] ?? '');

        if (!$sparepart_id) {
            $failed++;
            continue;
        }

        // ===============================
        // LOCK stok sparepart
        // ===============================
        $q = mysqli_query($conn, "
            SELECT stok_pcs 
            FROM spareparts 
            WHERE id_sparepart = '$sparepart_id'
              AND bengkel_id = '$bengkel_id'
            FOR UPDATE
        ");

        if (!$q || mysqli_num_rows($q) === 0) {
            $failed++;
            continue;
        }

        $row = mysqli_fetch_assoc($q);
        $stok_sistem = (int)$row['stok_pcs'];
        $selisih = $stok_fisik - $stok_sistem;

        // ===============================
        // CEK OPNAME HARI INI
        // ===============================
        $cek = mysqli_query($conn, "
            SELECT id_stok_opname 
            FROM stok_opnames
            WHERE spare_part_id = '$sparepart_id'
              AND bengkel_id = '$bengkel_id'
              AND DATE(tanggal_opname) = '$tanggal_hari'
            LIMIT 1
        ");

        $adaOpname = mysqli_num_rows($cek) > 0;

        // ===============================
        // KASUS SELISIH = 0
        // ===============================
        if ($selisih === 0) {

            // Jika sebelumnya ada opname → hapus
            if ($adaOpname) {
                $rowOp = mysqli_fetch_assoc($cek);
                mysqli_query($conn, "
                    DELETE FROM stok_opnames
                    WHERE id_stok_opname = '{$rowOp['id_stok_opname']}'
                ");
            }

        } else {

            // ===========================
            // SELISIH ≠ 0
            // ===========================
            if ($adaOpname) {

                // UPDATE
                $rowOp = mysqli_fetch_assoc($cek);
                $update = mysqli_query($conn, "
                    UPDATE stok_opnames
                    SET 
                        stok_sistem = '$stok_sistem',
                        stok_fisik  = '$stok_fisik',
                        selisih     = '$selisih',
                        keterangan  = '$keterangan',
                        tanggal_opname = '$tanggal_full'
                    WHERE id_stok_opname = '{$rowOp['id_stok_opname']}'
                ");

                if (!$update) {
                    throw new Exception('Gagal update opname');
                }

            } else {

                // INSERT BARU
                $insert = mysqli_query($conn, "
                    INSERT INTO stok_opnames
                    (tanggal_opname, spare_part_id, stok_sistem, stok_fisik, selisih, keterangan, bengkel_id)
                    VALUES
                    ('$tanggal_full', '$sparepart_id', '$stok_sistem', '$stok_fisik', '$selisih', '$keterangan', '$bengkel_id')
                ");

                if (!$insert) {
                    throw new Exception('Gagal insert opname');
                }
            }
        }

        // ===============================
        // UPDATE STOK (TETAP)
        // ===============================
        $updateStok = mysqli_query($conn, "
            UPDATE spareparts 
            SET stok_pcs = '$stok_fisik'
            WHERE id_sparepart = '$sparepart_id'
        ");

        if (!$updateStok) {
            throw new Exception('Gagal update stok');
        }

        $success++;
    }

    mysqli_commit($conn);

} catch (Exception $e) {
    mysqli_rollback($conn);
    response('error', 'Transaksi gagal: ' . $e->getMessage());
}

// ==========================================================
// RESPONSE
// ==========================================================
$status = $failed > 0 ? 'warning' : 'success';
$message = "Stok opname selesai. Berhasil: $success, Gagal: $failed";

response($status, $message);
