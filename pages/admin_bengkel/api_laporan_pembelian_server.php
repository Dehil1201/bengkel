<?php
session_start();
include "../../inc/koneksi.php";

$id_bengkel = $_SESSION['id_bengkel'];

$draw   = intval($_GET['draw']);
$start  = intval($_GET['start']);
$length = intval($_GET['length']);
$search = $_GET['search']['value'];

$tgl_dari     = $_GET['tgl_dari'];
$tgl_sampai   = $_GET['tgl_sampai'];
$id_supplier  = $_GET['id_supplier'];
$id_user      = $_GET['id_user'];

$where = "WHERE t.jenis='pembelian' AND t.id_bengkel='$id_bengkel'";

if ($tgl_dari && $tgl_sampai)
    $where .= " AND DATE(t.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";

if ($id_supplier)
    $where .= " AND t.id_supplier='$id_supplier'";

if ($id_user)
    $where .= " AND t.id_user='$id_user'";

if ($search)
    $where .= " AND (t.no_faktur LIKE '%$search%' 
                      OR p.nama_supplier LIKE '%$search%' 
                      OR u.nama_lengkap LIKE '%$search%')";

$totalData = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total 
     FROM transaksi t
     LEFT JOIN suppliers p ON t.id_supplier=p.id_supplier
     LEFT JOIN users u ON t.id_user=u.id_user
     $where"))['total'];

$query = mysqli_query($conn, "
    SELECT t.no_faktur, t.tanggal, p.nama_supplier, u.nama_lengkap, t.status, t.total
    FROM transaksi t
    LEFT JOIN suppliers p ON t.id_supplier=p.id_supplier
    LEFT JOIN users u ON t.id_user=u.id_user
    $where
    ORDER BY t.tanggal DESC
    LIMIT $start, $length
");

$data = [];
while ($r = mysqli_fetch_assoc($query)) {
    $data[] = [
        "no_faktur" => $r['no_faktur'],
        "tanggal"  => date('d-m-Y', strtotime($r['tanggal'])),
        "supplier" => $r['nama_supplier'] ?? '-',
        "user"     => $r['nama_lengkap'] ?? '-',
        "status"   => $r['status'] == 'selesai'
            ? '<span class="label label-success">Selesai</span>'
            : '<span class="label label-warning">'.$r['status'].'</span>',
        "total"    => "Rp ".number_format($r['total'],0,',','.'),
        "aksi"     => '<button class="btn btn-info btn-sm btn-detail"
                        data-faktur="'.$r['no_faktur'].'">
                        <i class="fa fa-eye"></i> Detail</button>'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalData,
    "data" => $data
]);
