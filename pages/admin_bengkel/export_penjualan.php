<?php
header("Content-Type: text/csv; charset=utf-8");
$tanggal_file = date('d_m_Y'); // format: 13_04_2026

$filename = "transaksi_" . $tanggal_file . ".csv";

header("Content-Disposition: attachment; filename=$filename");

session_start();
include "../../inc/koneksi.php";

// ========================
// SESSION
// ========================
$id_bengkel = intval($_SESSION['id_bengkel'] ?? 0);
$jenis = $_GET['jenis'] ?? '';
if ($id_bengkel == 0) {
    die("Session bengkel tidak ditemukan");
}

// ========================
// REQUEST
// ========================
$tgl_dari     = $_GET['tgl_dari'] ?? null;
$tgl_sampai   = $_GET['tgl_sampai'] ?? null;
$id_pelanggan = $_GET['id_pelanggan'] ?? null;
$id_user      = $_GET['id_user'] ?? null;

// HANDLE SEARCH (fix undefined)
$search_value = $_GET['search']['value'] ?? ($_GET['search'] ?? '');
if ($search_value === 'undefined') {
    $search_value = '';
}

// ========================
// WHERE
// ========================
$where = "WHERE t.id_bengkel = ?";
$params = [$id_bengkel];
$types  = "i";

// FILTER TANGGAL
if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(t.tanggal) BETWEEN ? AND ?";
    $params[] = $tgl_dari;
    $params[] = $tgl_sampai;
    $types .= "ss";
}

// FILTER PELANGGAN
if (!empty($id_pelanggan)) {
    $where .= " AND t.id_pelanggan = ?";
    $params[] = $id_pelanggan;
    $types .= "s";
}

// FILTER USER
if (!empty($id_user)) {
    $where .= " AND t.id_user = ?";
    $params[] = $id_user;
    $types .= "s";
}

// ========================
// FILTER JENIS TRANSAKSI
// ========================
if (!empty($jenis)) {

    if ($jenis === 'penjualan') {
        $where .= " AND t.no_faktur LIKE ?";
        $params[] = "PJ%";
        $types .= "s";
    }

    if ($jenis === 'service') {
        $where .= " AND t.no_faktur LIKE ?";
        $params[] = "PS%";
        $types .= "s";
    }

    if ($jenis === 'pembelian') {
        $where .= " AND t.no_faktur LIKE ?";
        $params[] = "PB%";
        $types .= "s";
    }
}

// SEARCH GLOBAL
if (!empty($search_value)) {
    $where .= " AND (
        t.no_faktur LIKE ?
        OR p.nama_pelanggan LIKE ?
        OR u.nama_lengkap LIKE ?
    )";

    $sv = "%$search_value%";
    $params[] = $sv;
    $params[] = $sv;
    $params[] = $sv;
    $types .= "sss";
}

// ========================
// QUERY (TANPA LIMIT)
// ========================
$sql = "
SELECT 
    t.no_faktur, 
    t.tanggal,
    COALESCE(p.nama_pelanggan, '-') AS pelanggan,
    COALESCE(u.nama_lengkap, '-') AS user,
    t.status,
    t.metode_bayar,
    t.total,
    t.discount,
    t.total_bayar,
    t.uang_bayar,
    t.kembalian
FROM transaksi t
LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
LEFT JOIN users u ON t.id_user = u.id_user
$where
ORDER BY t.tanggal DESC
";

$stmt = $conn->prepare($sql);

// bind dinamis
$bind = [$types];
foreach ($params as $k => $v) {
    $bind[] = &$params[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$result = $stmt->get_result();

// ========================
// OUTPUT CSV (FIX EXCEL)
// ========================
$output = fopen("php://output", "w");

// WAJIB untuk Excel Indonesia
echo "sep=;\n";

// HEADER
fputcsv($output, [
    'No Faktur',
    'Tanggal',
    'Pelanggan',
    'User',
    'Status',
    'Metode Bayar',
    'Total',
    'Diskon',
    'Total Bayar',
    'Uang Bayar',
    'Kembalian'
], ';');

// DATA
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['no_faktur'],
        date('d-m-Y', strtotime($row['tanggal'])),
        $row['pelanggan'],
        $row['user'],
        $row['status'],
        $row['metode_bayar'],
        $row['total'],
        $row['discount'],
        $row['total_bayar'],
        $row['uang_bayar'],
        $row['kembalian']
    ], ';');
}

fclose($output);
exit;