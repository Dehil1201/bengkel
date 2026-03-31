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
        "username" => $_SESSION['email'] ?? '',
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
$draw   = intval($_POST['draw'] ?? 0);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

// ===== Hitung Total Data =====
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts WHERE bengkel_id = '$id_bengkel'");
if (!$totalQuery) respondWithError("Gagal hitung total data: " . mysqli_error($conn));
$totalData = mysqli_fetch_assoc($totalQuery)['total'];

// ===== Hitung Total Filtered =====
$countSql = "
    SELECT COUNT(*) as total
    FROM spareparts sp
    LEFT JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori
    LEFT JOIN merks m ON sp.merk_id = m.id_merk
    WHERE sp.bengkel_id = '$id_bengkel'
";

$search = trim($_POST['search']['value'] ?? '');

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $keywords = preg_split('/\s+/', $safe);
    $whereParts = [];
    foreach ($keywords as $word) {
        $word = trim($word);
        if ($word === '') continue;
        $whereParts[] = "(
            sp.nama_sparepart LIKE '%$word%' OR
            sp.kode_sparepart LIKE '%$word%' OR
            m.nama_merk       LIKE '%$word%' OR
            k.nama_kategori   LIKE '%$word%' OR
            sp.lokasi_rak     LIKE '%$word%'
        )";
    }
    if (!empty($whereParts)) {
        $countSql .= " AND " . implode(" AND ", $whereParts);
    }
}

$cq = mysqli_query($conn, $countSql);
if (!$cq) respondWithError("Gagal hitung total filter: " . mysqli_error($conn));
$totalFiltered = mysqli_fetch_assoc($cq)['total'];

// ===== Sorting =====
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
$orderColumnDir   = $_POST['order'][0]['dir'] ?? 'asc';

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

// ===== STEP 1: Ambil id_sparepart sesuai filter, paging dan sorting =====
$idSparepartList = [];

$idSparepartQuery = "
    SELECT sp.id_sparepart
    FROM spareparts sp
    LEFT JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori
    LEFT JOIN merks m ON sp.merk_id = m.id_merk
    WHERE sp.bengkel_id = '$id_bengkel'
";

if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $keywords = preg_split('/\s+/', $safe);
    $whereParts = [];
    foreach ($keywords as $word) {
        $word = trim($word);
        if ($word === '') continue;
        $whereParts[] = "(
            sp.nama_sparepart LIKE '%$word%' OR
            sp.kode_sparepart LIKE '%$word%' OR
            m.nama_merk       LIKE '%$word%' OR
            k.nama_kategori   LIKE '%$word%' OR
            sp.lokasi_rak     LIKE '%$word%'
        )";
    }
    if (!empty($whereParts)) {
        $idSparepartQuery .= " AND " . implode(" AND ", $whereParts);
    }
}

$idSparepartQuery .= " ORDER BY $orderBy $orderColumnDir LIMIT $start, $length";

$resIds = mysqli_query($conn, $idSparepartQuery);
if (!$resIds) respondWithError("Gagal ambil id_sparepart: " . mysqli_error($conn));

while ($row = mysqli_fetch_assoc($resIds)) {
    $idSparepartList[] = intval($row['id_sparepart']);
}

// Jika tidak ada data, langsung kirim response kosong
if (empty($idSparepartList)) {
    echo json_encode([
        "success" => true,
        "draw" => $draw,
        "username" => $username,
        "recordsTotal" => $totalData,
        "recordsFiltered" => $totalFiltered,
        "data" => []
    ]);
    exit;
}

$idSparepartStr = implode(",", $idSparepartList);

// ===== STEP 2: Ambil data utama tanpa subquery terjual =====
$mainSql = "
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
    WHERE sp.id_sparepart IN ($idSparepartStr)
    ORDER BY FIELD(sp.id_sparepart, $idSparepartStr)
";

$resMain = mysqli_query($conn, $mainSql);
if (!$resMain) respondWithError("Gagal ambil data sparepart utama: " . mysqli_error($conn));

// ===== STEP 3: Ambil data terjual untuk sparepart yg tampil =====
$terjualSql = "
    SELECT tds.kode_sparepart, SUM(tds.qty) AS terjual
    FROM transaksi_detail_sparepart tds
    JOIN transaksi t ON t.no_faktur = tds.no_faktur
    JOIN spareparts sp ON tds.kode_sparepart = sp.kode_sparepart
    WHERE t.jenis = 'penjualan'
      AND t.id_bengkel = '$id_bengkel'
      AND sp.id_sparepart IN ($idSparepartStr)
";

$terjualSql .= " GROUP BY tds.kode_sparepart";

$resTerjual = mysqli_query($conn, $terjualSql);
if (!$resTerjual) respondWithError("Gagal ambil data terjual: " . mysqli_error($conn));

$terjualMap = [];
while ($row = mysqli_fetch_assoc($resTerjual)) {
    $terjualMap[$row['kode_sparepart']] = (int)$row['terjual'];
}

// ===== STEP 4: Proses data untuk response =====
$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($resMain)) {
    $terjual = $terjualMap[$row['kode_sparepart']] ?? 0;

    // Ambil harga jual seperti biasa
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

    $lokasi_rak = ($row['lokasi_rak'] === "null") ? "" : $row['lokasi_rak'];

    $data[] = [
        "no"               => $no++,
        "id_sparepart"     => $row['id_sparepart'],
        "kode_sparepart"   => htmlspecialchars($row['kode_sparepart']),
        "nama_sparepart"   => htmlspecialchars($row['nama_sparepart']),
        "nama_merk"        => htmlspecialchars($row['nama_merk']),
        "nama_kategori"    => htmlspecialchars($row['nama_kategori']),
        "stok_pcs"         => htmlspecialchars($row['stok_pcs']),
        "terjual"          => $terjual,
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

// Optional: debugging log
error_log("MAIN QUERY ID LIST: " . $idSparepartQuery);
error_log("MAIN DATA QUERY: " . $mainSql);
error_log("TERJUAL QUERY: " . $terjualSql);
