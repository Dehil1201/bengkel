<?php
session_start();
include '../../inc/koneksi.php';
include '../../inc/functions.php';
require '../../vendor/autoload.php';

use Mpdf\Mpdf;

$user_role = get_user_role();
if (!in_array($user_role, ['owner_bengkel', 'admin_bengkel'])) {
    die('Akses ditolak');
}

$bengkel_id = $_GET['bengkel_id'] ?? null;
if (!$bengkel_id) die('Bengkel tidak valid');

$mpdf = new Mpdf([
    'format' => 'A4',
    'margin_top' => 5,
]);

// ======================
// DATA BENGKEL
// ======================
$qBengkel = mysqli_query($conn, "
    SELECT nama_bengkel 
    FROM bengkels 
    WHERE id_bengkel = '$bengkel_id'
");
$bengkel = mysqli_fetch_assoc($qBengkel);

// ======================
// DATA STOK OPNAME
// ======================
$query = mysqli_query($conn, "
    SELECT 
        so.tanggal_opname,
        sp.nama_sparepart,
        so.stok_sistem,
        so.stok_fisik,
        so.selisih,
        so.keterangan
    FROM stok_opnames so
    JOIN spareparts sp ON so.spare_part_id = sp.id_sparepart
    WHERE so.bengkel_id = '$bengkel_id'
    ORDER BY so.tanggal_opname DESC
");

$html = '
<h2 style="text-align:center">LAPORAN STOK OPNAME</h2>
<p><strong>Bengkel:</strong> '.$bengkel['nama_bengkel'].'</p>
<p><strong>Tanggal Cetak:</strong> '.date('d-m-Y H:i').'</p>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr style="background:#f0f0f0">
            <th>No</th>
            <th>Spare Part</th>
            <th>Stok Sistem</th>
            <th>Stok Fisik</th>
            <th>Selisih</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
';

$no = 1;
while ($row = mysqli_fetch_assoc($query)) {
    $warna = ($row['selisih'] < 0) ? 'style="color:red"' : '';
    $html .= "
        <tr>
            <td>{$no}</td>
            <td>{$row['nama_sparepart']}</td>
            <td align='center'>{$row['stok_sistem']}</td>
            <td align='center'>{$row['stok_fisik']}</td>
            <td align='center' {$warna}>{$row['selisih']}</td>
            <td>{$row['keterangan']}</td>
        </tr>
    ";
    $no++;
}

$html .= '</tbody></table>';

$mpdf->WriteHTML($html);
$mpdf->Output('stok_opname_'.$bengkel['nama_bengkel'].'.pdf', 'I');
