<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

// ========= Helper Response =========
function respond($success, $message, $data = [], $status_code = null) {
    if ($status_code === null) {
        $status_code = $success ? 200 : 400;
    }
    http_response_code($status_code);
    echo json_encode([
        "success" => $success,
        "status_code" => $status_code,
        "message" => $message,
        "items" => $data["items"] ?? [],
        "more" => $data["more"] ?? false
    ]);
    exit;
}

// ========= Validasi Login =========
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    respond(false, "Session expired. Silakan login ulang.", [], 401);
}

// ========= Ambil bengkel_id user =========
$q2 = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$d2 = mysqli_fetch_assoc($q2);
$id_bengkel = $d2['bengkel_id'];

// ========= Pagination & Search =========
$search = trim($_POST['search'] ?? '');
$page   = max(1, (int)($_POST['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// ========= Base Query =========
$sql = "
  SELECT sp.id_sparepart, sp.kode_sparepart, sp.nama_sparepart, sp.hpp_per_pcs, sp.stok_pcs
  FROM spareparts sp
  WHERE sp.bengkel_id = ?
";

$params = [$id_bengkel];
$types  = "i";

// ========= ✅ OPTIMASI SEARCH MULTI KEYWORD =========
if ($search !== '') {

    // Pecah berdasarkan spasi → multi keyword
    $keywords = preg_split('/\s+/', $search);

    $whereParts = [];
    foreach ($keywords as $word) {
        if ($word === '') continue;

        $whereParts[] = "(
            sp.nama_sparepart LIKE ?
            OR sp.kode_sparepart LIKE ?
        )";

        $like = "%$word%";
        $params[] = $like;
        $params[] = $like;
        $types .= "ss";
    }

    if ($whereParts) {
        $sql .= " AND " . implode(" AND ", $whereParts);
    }
}

// ========= Sorting + Limit =========
$sql .= " ORDER BY sp.nama_sparepart ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// ========= Execute Main Query =========
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$items = [];

// ========= Loop Sparepart + Ambil Harga Jual =========
while ($row = $res->fetch_assoc()) {

    $kode_sparepart = $row['kode_sparepart'];
    $id_sparepart   = $row['id_sparepart'];

    // --- Ambil Harga Jual terkait sparepart ---
    $qHarga = mysqli_query($conn, "
        SELECT 
            hj.id_harga_jual,
            hj.tipe_harga,
            hj.persentase_jual,
            hj.harga_jual,
            hj.isi_per_pcs_jual,
            st.id_satuan,
            st.nama_satuan
        FROM harga_jual_sparepart hj
        JOIN satuan st ON hj.satuan_jual_id = st.id_satuan
        WHERE hj.sparepart_id = '$id_sparepart'
        AND hj.harga_jual > 0
        ORDER BY hj.tipe_harga ASC
    ");

    $hargaList = [];
    while ($h = mysqli_fetch_assoc($qHarga)) {
        $hargaList[] = [
            "id_harga_jual"    => (int)$h['id_harga_jual'],
            "tipe_harga"       => (int)$h['tipe_harga'],
            "nama_satuan"      => $h['nama_satuan'],
            "id_satuan"        => (int)$h['id_satuan'],
            "persentase_jual"  => (float)$h['persentase_jual'],
            "harga_jual"       => (float)$h['harga_jual'],
            "isi_per_pcs_jual" => (int)$h['isi_per_pcs_jual']
        ];
    }

    // --- Masukkan ke items ---
    $items[] = [
        "id" => $kode_sparepart,
        "nama_sparepart" => $row['nama_sparepart'],
        "stok" => $row['stok_pcs'],
        "harga_beli" => (float)$row['hpp_per_pcs'],
        "harga_satuan" => $hargaList
    ];
}

// ========= Response =========
$response = [
    "items" => $items,
    "more"  => count($items) == $limit
];

respond(true, "Data sparepart & harga berhasil diambil.", $response, 200);
