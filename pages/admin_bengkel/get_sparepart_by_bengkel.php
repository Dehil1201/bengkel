<?php
// Pastikan sesi sudah dimulai dan file koneksi disertakan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../inc/koneksi.php';
include '../../inc/functions.php';

// Pastikan request adalah AJAX dan ada parameter bengkel_id
if (isset($_GET['bengkel_id']) && isset($_SESSION['id_user'])) {
    header('Content-Type: application/json');

    $bengkel_id = mysqli_real_escape_string($conn, $_GET['bengkel_id']);

    // Pengecekan akses, pastikan user berhak melihat data bengkel ini
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

    if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
        echo json_encode(['error' => 'Akses ditolak. Bengkel tidak valid.']);
        exit;
    }

    $query = "SELECT id_sparepart, nama_sparepart, stok_pcs FROM spareparts WHERE bengkel_id = '$bengkel_id' ORDER BY nama_sparepart ASC";
    $result = mysqli_query($conn, $query);

    $spareparts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $spareparts[] = $row;
    }

    echo json_encode($spareparts);

} else {
    // Jika bukan request AJAX atau parameter tidak ada
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid request.']);
}
?>