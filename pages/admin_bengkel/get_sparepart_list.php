<?php
session_start();
include "../../inc/koneksi.php";

header('Content-Type: application/json; charset=utf-8');

// Validasi login
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// Ambil bengkel user
$q2 = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$d2 = mysqli_fetch_assoc($q2);
$id_bengkel = $d2['bengkel_id'] ?? null;

// Parameter DataTables
$draw   = intval($_GET['draw'] ?? 0);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? "";

// Hitung total data
$totalQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total
    FROM spareparts sp
    WHERE sp.bengkel_id = '$id_bengkel'
");
$totalData = mysqli_fetch_assoc($totalQuery)['total'];

// Query dasar
$sql = "
    SELECT sp.*, 
           b.nama_bengkel, 
           k.nama_kategori, 
           m.nama_merk, 
           s.nama_satuan AS nama_satuan_beli
    FROM spareparts sp
    JOIN bengkels b ON sp.bengkel_id = b.id_bengkel
    JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori
    JOIN merk_sparepart m ON sp.merk_id = m.id_merk
    JOIN satuan s ON sp.satuan_beli_id = s.id_satuan
    WHERE sp.bengkel_id = '$id_bengkel'
";

// Search
if (!empty($searchValue)) {
    $searchValue = mysqli_real_escape_string($conn, $searchValue);
    $sql .= " AND (
        sp.nama_sparepart LIKE '%$searchValue%' OR
        sp.kode_sparepart LIKE '%$searchValue%' OR
        m.nama_merk LIKE '%$searchValue%' OR
        k.nama_kategori LIKE '%$searchValue%'
    ) ";
}

// Hitung total setelah filter
$totalFilteredQuery = mysqli_query($conn, $sql);
$totalFiltered = mysqli_num_rows($totalFilteredQuery);

// Order
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderColumnDir   = $_GET['order'][0]['dir'] ?? 'asc';

$columns = [
    1 => 'sp.kode_sparepart',
    2 => 'sp.nama_sparepart',
    3 => 'm.nama_merk',
    4 => 'k.nama_kategori',
    5 => 'sp.stok_pcs',
    6 => 'sp.harga_beli',
    7 => 'sp.id_sparepart',
    8 => 'b.nama_bengkel'
];
$orderBy = $columns[$orderColumnIndex] ?? 'sp.nama_sparepart';

$sql .= " ORDER BY $orderBy $orderColumnDir LIMIT $start, $length";
$query = mysqli_query($conn, $sql);

// Siapkan data
$data = [];
$no = $start + 1;
while ($row = mysqli_fetch_assoc($query)) {
    // ambil harga jual per satuan
    $hargaRows = mysqli_query($conn, "
        SELECT hj.harga_jual, st.nama_satuan
        FROM harga_jual_sparepart hj
        JOIN satuan st ON hj.satuan_jual_id = st.id_satuan
        WHERE hj.sparepart_id = '{$row['id_sparepart']}'
        ORDER BY hj.tipe_harga ASC
    ");
    $hargaList = [];
    while ($hj = mysqli_fetch_assoc($hargaRows)) {
        $hargaList[] = "<p><strong>{$hj['nama_satuan']}:</strong> Rp " . number_format($hj['harga_jual'],0,',','.') . "</p>";
    }

    // Tombol aksi
    $aksi = '
        <a href="#" class="btn btn-warning btn-xs btn-edit" data-id="'.$row['id_sparepart'].'">
            <i class="fa fa-pencil"></i> Edit
        </a>
        <button type="button" class="btn btn-danger btn-xs btn-hapus" data-id="'.$row['id_sparepart'].'">
            <i class="fa fa-trash"></i> Hapus
        </button>
    ';

    $data[] = [
        $no++,
        htmlspecialchars($row['kode_sparepart']),
        htmlspecialchars($row['nama_sparepart']),
        htmlspecialchars($row['nama_merk']),
        htmlspecialchars($row['nama_kategori']),
        htmlspecialchars($row['stok_pcs']),
        "Rp " . number_format($row['harga_beli'],0,',','.'),
        implode("", $hargaList),
        htmlspecialchars($row['nama_bengkel']),
        $aksi
    ];
}

// Response ke DataTables
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
