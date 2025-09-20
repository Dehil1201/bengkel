<?php
include "../../inc/koneksi.php";

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';
$tanggal_dari = $_POST['tanggal_dari'] ?? '';
$tanggal_sampai = $_POST['tanggal_sampai'] ?? '';

// Build kondisi WHERE dinamis
$where = "WHERE 1=1 ";

if ($tanggal_dari != '') {
    $tglDari = mysqli_real_escape_string($conn, $tanggal_dari);
    $where .= " AND rp.tanggal_retur >= '$tglDari' ";
}

if ($tanggal_sampai != '') {
    $tglSampai = mysqli_real_escape_string($conn, $tanggal_sampai);
    $where .= " AND rp.tanggal_retur <= '$tglSampai' ";
}

if ($search != '') {
    $searchEscaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        rp.no_faktur LIKE '%$searchEscaped%' OR 
        p.nama_supplier LIKE '%$searchEscaped%' OR 
        rp.alasan LIKE '%$searchEscaped%'
    ) ";
}

// Hitung total data tanpa filter (untuk DataTables)
$totalDataQuery = "SELECT COUNT(*) as total FROM retur_pembelian";
$totalDataResult = mysqli_query($conn, $totalDataQuery);
$totalData = mysqli_fetch_assoc($totalDataResult)['total'];

// Hitung total data dengan filter
$totalFilteredQuery = "SELECT COUNT(*) as total FROM retur_pembelian rp
    LEFT JOIN suppliers p ON rp.id_supplier = p.id_supplier
    $where";
$totalFilteredResult = mysqli_query($conn, $totalFilteredQuery);
$totalFiltered = mysqli_fetch_assoc($totalFilteredResult)['total'];

// Ambil data dengan filter dan limit
$sql = "SELECT rp.id_retur_pembelian, rp.no_retur, rp.no_faktur, p.nama_supplier, 
        rp.tanggal_retur, rp.alasan, rp.total_retur
    FROM retur_pembelian rp
    LEFT JOIN suppliers p ON rp.id_supplier = p.id_supplier
    $where
    ORDER BY rp.tanggal_retur DESC
    LIMIT $start, $length";

$result = mysqli_query($conn, $sql);

$data = [];
$no = $start + 1;
while ($row = mysqli_fetch_assoc($result)) {
    $row['no'] = $no++;
    $data[] = $row;
}

// Output JSON sesuai format DataTables
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
