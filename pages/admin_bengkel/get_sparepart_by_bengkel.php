<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../../inc/koneksi.php';
include '../../inc/functions.php';

header('Content-Type: application/json');

// ================= VALIDASI SESSION =================
if (!isset($_SESSION['id_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ================= PARAMETER DATATABLE =================
$draw   = intval($_POST['draw'] ?? 0);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';
$bengkel_id = $_POST['bengkel_id'] ?? null;

if (!$bengkel_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Bengkel ID tidak ditemukan']);
    exit;
}

$bengkel_id = mysqli_real_escape_string($conn, $bengkel_id);

// ================= CEK AKSES USER =================
$user_role = get_user_role();
$accessible_bengkel_ids = [];

if ($user_role === 'owner_bengkel') {
    $owner_id = $_SESSION['id_user'];
    $q = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE owner_id='$owner_id'");
    while ($r = mysqli_fetch_assoc($q)) {
        $accessible_bengkel_ids[] = $r['id_bengkel'];
    }
} elseif ($user_role === 'admin_bengkel') {
    $user_id = $_SESSION['id_user'];
    $q = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$user_id'");
    if ($r = mysqli_fetch_assoc($q)) {
        $accessible_bengkel_ids[] = $r['bengkel_id'];
    }
}

if (!in_array($bengkel_id, $accessible_bengkel_ids)) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

// ================= QUERY =================
$where = "WHERE bengkel_id = '$bengkel_id'";
if ($search !== '') {
    $search = mysqli_real_escape_string($conn, $search);
    $where .= " AND nama_sparepart LIKE '%$search%'";
}

// Total data
$totalData = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM spareparts WHERE bengkel_id='$bengkel_id'")
)['total'];

// Total setelah search
$totalFiltered = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM spareparts $where")
)['total'];

// Data utama
$query = "
    SELECT id_sparepart, nama_sparepart, stok_pcs
    FROM spareparts
    $where
    ORDER BY nama_sparepart ASC
    LIMIT $start, $length
";

$result = mysqli_query($conn, $query);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

// ================= RESPONSE DATATABLE =================
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
