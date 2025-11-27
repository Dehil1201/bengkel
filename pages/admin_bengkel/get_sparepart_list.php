<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');
$username = $_SESSION['email'] ?? '';

$hideHargaBeli = ($username === 'user@gmail.com');


// ===== Helper Error Function =====
function respondWithError($message) {
    echo json_encode([
        "success" => false,
        "error" => $message,
        "data" => [],
        "username" => $username,
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => 0,
        "recordsFiltered" => 0
    ]);
    exit;
}

// ===== Validasi Login =====
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    respondWithError("Session expired. Silakan login ulang.");
}

// ===== Ambil Bengkel User =====
$q2 = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
if (!$q2) respondWithError("Gagal ambil data user: " . mysqli_error($conn));

$d2 = mysqli_fetch_assoc($q2);
$id_bengkel = $d2['bengkel_id'] ?? null;

// ===== Parameter DataTables =====
$draw   = intval($_GET['draw'] ?? 0);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);

// ===== Hitung Total Data =====
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts WHERE bengkel_id = '$id_bengkel'");
if (!$totalQuery) respondWithError("Gagal hitung total data: " . mysqli_error($conn));
$totalData = mysqli_fetch_assoc($totalQuery)['total'];

// ===== Query Dasar =====
$baseSql = "
    SELECT sp.*, 
           b.nama_bengkel, 
           k.nama_kategori, 
           m.nama_merk, 
           s.nama_satuan AS nama_satuan_beli,
           k.id_kategori,
           m.id_merk,
           sp.lokasi_rak,
           sp.satuan_beli_id,
           sp.isi_per_pcs_beli,
           sp.hpp_per_pcs,
           sp.stok_minimal
    FROM spareparts sp
    JOIN bengkels b ON sp.bengkel_id = b.id_bengkel
    LEFT JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori
    LEFT JOIN merks m ON sp.merk_id = m.id_merk
    LEFT JOIN satuan s ON sp.satuan_beli_id = s.id_satuan
    WHERE sp.bengkel_id = '$id_bengkel'
";

$sql = $baseSql;

$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

if ($search != '') {

    $safe = mysqli_real_escape_string($conn, strtolower($search));

    // daftar prioritas
    $priority = [
        "LOWER(sp.nama_sparepart)",
        "LOWER(sp.kode_sparepart)",
        "LOWER(m.nama_merk)",
        "LOWER(k.nama_kategori)"
    ];

    $found = false;

    foreach ($priority as $col) {

        $sqlCheck = $baseSql . " AND $col LIKE '%$safe%' LIMIT 1";
        $check = mysqli_query($conn, $sqlCheck);
        
        error_log("CHECK QUERY: " . $sqlCheck);

        if (mysqli_num_rows($check) > 0) {
            // tempelkan kondisi ini ke query utama
            $sql .= " AND $col LIKE '%$safe%'";
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo json_encode([
            "success" => true,
            "draw" => $draw,
            "recordsTotal" => $totalData,
            "recordsFiltered" => 0,
            "data" => []
        ]);
        exit;
    }
}



// ===== Hitung Total Setelah Filter =====
$totalFilteredQuery = mysqli_query($conn, $sql);
if (!$totalFilteredQuery) respondWithError("Gagal hitung total filter: " . mysqli_error($conn));
$totalFiltered = mysqli_num_rows($totalFilteredQuery);

// ===== Sorting =====
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

// ===== Eksekusi Query =====
$query = mysqli_query($conn, $sql);
if (!$query) respondWithError("Gagal ambil data sparepart: " . mysqli_error($conn));

// ===== Siapkan Data Tabel =====
$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($query)) {

    // ===== Ambil Harga Jual =====
    $hargaRows = mysqli_query($conn, "
        SELECT hj.tipe_harga, hj.persentase_jual, hj.harga_jual,
               hj.satuan_jual_id, st.nama_satuan, hj.isi_per_pcs_jual
        FROM harga_jual_sparepart hj
        LEFT JOIN satuan st ON hj.satuan_jual_id = st.id_satuan
        WHERE hj.sparepart_id = '{$row['id_sparepart']}'
        ORDER BY hj.tipe_harga ASC
    ");

    $hargaListHtml = [];
    $hargaListRaw = [];

    while ($hj = mysqli_fetch_assoc($hargaRows)) {
        $hargaListHtml[] = "<p>Rp " . number_format($hj['harga_jual'], 0, ',', '.') . "</p>";

        $hargaListRaw[] = [
            'tipe_harga'        => (int)$hj['tipe_harga'],
            'persentase_jual'  => (float)$hj['persentase_jual'],
            'harga_jual'       => (float)$hj['harga_jual'],
            'satuan_jual_id'   => (int)$hj['satuan_jual_id'],
            'isi_per_pcs_jual' => (int)$hj['isi_per_pcs_jual']
        ];
    }

    // ===== Fix lokasi rak ====
    $lokasi_rak = ($row['lokasi_rak'] === "null") ? "" : $row['lokasi_rak'];

    $data[] = [
        "no"               => $no++,
        "id_sparepart"     => $row['id_sparepart'],
        "kode_sparepart"   => htmlspecialchars($row['kode_sparepart']),
        "nama_sparepart"   => htmlspecialchars($row['nama_sparepart']),
        "nama_merk"        => htmlspecialchars($row['nama_merk']),
        "nama_kategori"    => htmlspecialchars($row['nama_kategori']),
        "stok_pcs"         => htmlspecialchars($row['stok_pcs']),
        "harga_beli"       => $hideHargaBeli ? 0 : $row['harga_beli'],
        "hpp_per_pcs"      => $hideHargaBeli ? 0 : $row['hpp_per_pcs'],
        "harga_jual"       => implode("", $hargaListHtml),
        "harga_jual_raw"   => $hargaListRaw,
        "nama_bengkel"     => htmlspecialchars($row['nama_bengkel']),
        "id_kategori"      => $row['id_kategori'],
        "id_merk"          => $row['id_merk'],
        "lokasi_rak"       => $lokasi_rak,
        "satuan_beli_id"   => $row['satuan_beli_id'],
        "isi_per_pcs_beli" => $row['isi_per_pcs_beli'],
        "stok_minimal"     => $row['stok_minimal'],
    ];
    
}



// ===== Kirim Response JSON =====
echo json_encode([
    "success" => true,
    "draw" => $draw,
    "username" => $username,
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);

error_log("MAIN QUERY: " . $sql);
