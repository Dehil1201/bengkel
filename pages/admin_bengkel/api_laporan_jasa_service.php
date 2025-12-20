<?php
session_start();
include "../../inc/koneksi.php";

$id_bengkel = $_SESSION['id_bengkel'];

$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';

$tgl_dari     = $_GET['tgl_dari'] ?? '';
$tgl_sampai   = $_GET['tgl_sampai'] ?? '';
$id_pelanggan = $_GET['id_pelanggan'] ?? '';
$id_user      = $_GET['id_user'] ?? '';
$id_teknisi   = $_GET['id_teknisi'] ?? '';

$where = "WHERE (t.no_faktur LIKE '%PS%' OR t.no_faktur LIKE '%JS%')
          AND t.id_bengkel = '$id_bengkel'";

if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
}
if ($id_pelanggan) {
    $where .= " AND t.id_pelanggan = '$id_pelanggan'";
}
if ($id_user) {
    $where .= " AND t.id_user = '$id_user'";
}
if ($id_teknisi) {
    $where .= " AND t.id_teknisi = '$id_teknisi'";
}
if ($search) {
    $where .= " AND (
        t.no_faktur LIKE '%$search%' OR
        t.no_polisi LIKE '%$search%' OR
        p.nama_pelanggan LIKE '%$search%' OR
        tk.nama_teknisi LIKE '%$search%'
    )";
}


$baseQuery = "
FROM transaksi t
LEFT JOIN pelanggans p ON t.id_pelanggan = p.id_pelanggan
LEFT JOIN users u ON t.id_user = u.id_user
LEFT JOIN teknisis tk ON t.id_teknisi = tk.id_teknisi
$where
";

$totalData = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) total $baseQuery")
)['total'];

$dataQuery = mysqli_query($conn, "
    SELECT 
        t.no_faktur, t.tanggal, t.no_polisi, t.kendaraan,
        p.nama_pelanggan, u.nama_lengkap, t.status,
        t.total_bayar, tk.nama_teknisi,

        IFNULL((
            SELECT SUM(td.biaya)
            FROM transaksi_detail_servis td
            WHERE td.no_faktur = t.no_faktur
        ),0) total_servis,

        IFNULL((
            SELECT SUM(ts.subtotal)
            FROM transaksi_detail_sparepart ts
            WHERE ts.no_faktur = t.no_faktur
        ),0) total_sparepart
    $baseQuery
    ORDER BY t.tanggal DESC
    LIMIT $start, $length
");

$data = [];
while ($row = mysqli_fetch_assoc($dataQuery)) {
    $data[] = [
        $row['no_faktur'],
        date('d-m-Y', strtotime($row['tanggal'])),
        $row['nama_pelanggan'] ?? '-',
        $row['kendaraan'] ?? '-',
        $row['no_polisi'] ?? '-',
        $row['nama_lengkap'] ?? '-',
        $row['status'],
        'Rp ' . number_format($row['total_bayar'], 0, ',', '.'),
        'Rp ' . number_format($row['total_sparepart'], 0, ',', '.'),
        'Rp ' . number_format($row['total_servis'], 0, ',', '.'),
        $row['nama_teknisi'] ?? '-',
        '<button class="btn btn-info btn-sm btn-detail" data-faktur="'.$row['no_faktur'].'">
            <i class="fa fa-eye"></i> Detail
         </button>'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalData,
    "data" => $data
]);
