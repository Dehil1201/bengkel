<?php
header('Content-Type: application/json; charset=utf-8');
include "../../inc/koneksi.php";
session_start();

// Ambil id_bengkel
$id_bengkel = $_SESSION['id_bengkel'] ?? ($_GET['id_bengkel'] ?? "");

// Ambil request (GET/POST)
$req = $_REQUEST;

$draw   = intval($req['draw'] ?? 1);
$start  = intval($req['start'] ?? 0);
$length = intval($req['length'] ?? 10);

$tgl_dari     = $req['tgl_dari'] ?? null;
$tgl_sampai   = $req['tgl_sampai'] ?? null;
$id_pelanggan = $req['id_pelanggan'] ?? null;
$id_user      = $req['id_user'] ?? null;

$search_value    = $req['search']['value'] ?? '';
$order_col_index = intval($req['order'][0]['column'] ?? 1);
$order_dir       = (isset($req['order'][0]['dir']) && strtolower($req['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$columns = [
    0 => 't.no_faktur',
    1 => 't.tanggal',
    2 => 'p.nama_pelanggan',
    3 => 'u.nama_lengkap',
    4 => 't.status',
    5 => 't.metode_bayar',
    6 => 't.total',
    7 => 't.discount',
    8 => 't.total_bayar',
    9 => 't.uang_bayar',
    10 => 't.kembalian'
];

$order_by = $columns[$order_col_index] ?? 't.tanggal';


// ======================================
// HELPER FIX: bind_param dinamis
// ======================================
function bindParams($stmt, $types, array &$params)
{
    if ($types === '' || empty($params)) return;

    $bind = [];
    $bind[] = $types;

    foreach ($params as $k => &$v) {
        $bind[] = &$v;  // HARUS reference
    }

    return call_user_func_array([$stmt, "bind_param"], $bind);
}


// ======================================
// WHERE BASE
// ======================================
$where_clauses = ["(t.no_faktur LIKE ? OR t.no_faktur LIKE ?)"];
$params = ["%PJ%", "%PS%"];
$param_types = "ss";

$where_clauses[] = "t.id_bengkel = ?";
$params[] = $id_bengkel;
$param_types .= "s";

if ($tgl_dari && $tgl_sampai) {
    $where_clauses[] = "DATE(t.tanggal) BETWEEN ? AND ?";
    $params[] = $tgl_dari;
    $params[] = $tgl_sampai;
    $param_types .= "ss";
}

if ($id_pelanggan) {
    $where_clauses[] = "t.id_pelanggan = ?";
    $params[] = $id_pelanggan;
    $param_types .= "s";
}

if ($id_user) {
    $where_clauses[] = "t.id_user = ?";
    $params[] = $id_user;
    $param_types .= "s";
}

if ($search_value !== '') {
    $where_clauses[] = "(t.no_faktur LIKE ? OR p.nama_pelanggan LIKE ? OR u.nama_lengkap LIKE ?)";
    $sv = "%$search_value%";
    $params[] = $sv;
    $params[] = $sv;
    $params[] = $sv;
    $param_types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);


// ======================================
// 1) TOTAL RECORDS
// ======================================
$countSql = "SELECT COUNT(*) AS cnt 
             FROM transaksi t 
             WHERE (t.no_faktur LIKE ? OR t.no_faktur LIKE ?) 
             AND t.id_bengkel = ?";

$stmt = $conn->prepare($countSql);
$nf1 = $params[0];
$nf2 = $params[1];
$bengkel = $params[2];
$stmt->bind_param("sss", $nf1, $nf2, $bengkel);
$stmt->execute();
$totalRecords = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();


// ======================================
// 2) FILTERED RECORDS
// ======================================
$cntSql = "
    SELECT COUNT(*) AS cnt
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN users u ON t.id_user = u.id_user
    $where_sql
";

$stmt = $conn->prepare($cntSql);
$bind_params = $params;
bindParams($stmt, $param_types, $bind_params);
$stmt->execute();
$filteredRecords = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();


// ======================================
// 3) DATA QUERY
// ======================================
$sql = "
SELECT 
    t.no_faktur, t.tanggal,
    COALESCE(p.nama_pelanggan, '-') AS nama_pelanggan,
    COALESCE(u.nama_lengkap, '-') AS nama_lengkap,
    t.total_bayar, t.status, t.total, t.discount,
    t.uang_bayar, t.kembalian, t.metode_bayar,
    LEFT(
        (SELECT GROUP_CONCAT(s.nama_sparepart SEPARATOR ', ')
         FROM transaksi_detail_sparepart td
         LEFT JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
         WHERE td.no_faktur = t.no_faktur),
    200) AS daftar_barang
FROM transaksi t
LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
LEFT JOIN users u ON t.id_user = u.id_user
$where_sql
ORDER BY $order_by $order_dir
LIMIT ?, ?
";

$stmt = $conn->prepare($sql);
$params_with_limit = $params;
$params_with_limit[] = $start;
$params_with_limit[] = $length;
bindParams($stmt, $param_types . "ii", $params_with_limit);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
    $data[] = [
        'no_faktur'     => $r['no_faktur'],
        'tanggal'       => date('d-m-Y', strtotime($r['tanggal'])),
        'pelanggan'     => $r['nama_pelanggan'],
        'user'          => $r['nama_lengkap'],
        'status'        => $r['status'],
        'metode_bayar'  => $r['metode_bayar'],
        'total'         => intval($r['total']),
        'discount'      => intval($r['discount']),
        'total_bayar'   => intval($r['total_bayar']),
        'uang_bayar'    => intval($r['uang_bayar']),
        'kembalian'     => intval($r['kembalian']),
        'daftar_barang' => $r['daftar_barang'] ? 
                           $r['daftar_barang'] . (strlen($r['daftar_barang']) >= 200 ? '...' : '') 
                           : ''
    ];
}
$stmt->close();


// ======================================
// 4) TOTAL PENJUALAN
// ======================================
$sumSql = "
    SELECT COALESCE(SUM(t.total_bayar),0) AS total_penjualan
    FROM transaksi t
    LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN users u ON t.id_user = u.id_user
    $where_sql
";

$stmt = $conn->prepare($sumSql);
$bind_params_sum = $params;
bindParams($stmt, $param_types, $bind_params_sum);
$stmt->execute();
$total_penjualan = intval($stmt->get_result()->fetch_assoc()['total_penjualan'] ?? 0);
$stmt->close();


// ======================================
// RESPONSE FINAL
// ======================================
echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data"            => $data,
    "total_penjualan" => $total_penjualan
]);
