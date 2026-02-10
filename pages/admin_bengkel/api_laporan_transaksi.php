<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

// =====================
// VALIDASI LOGIN
// =====================
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode(["error" => "Session expired"]);
    exit;
}

// =====================
// AMBIL BENGKEL USER
// =====================
$qB = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$dB = mysqli_fetch_assoc($qB);
$id_bengkel = $dB['bengkel_id'] ?? 0;

// =====================
// DATATABLES REQUEST
// =====================
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? "";

// =====================
// FILTER CUSTOM
// =====================
$tgl_mulai   = $_POST['tgl_mulai'] ?? "";
$tgl_selesai = $_POST['tgl_selesai'] ?? "";
$jenis       = $_POST['jenis'] ?? "";

// =====================
// BASE QUERY
// =====================
$baseQuery = "
    FROM transaksi t
    LEFT JOIN pelanggans c ON c.id_pelanggan = t.id_pelanggan
    LEFT JOIN suppliers s ON s.id_supplier = t.id_supplier
    LEFT JOIN transaksi_detail_sparepart td ON td.no_faktur = t.no_faktur
    LEFT JOIN spareparts sp ON sp.kode_sparepart = td.kode_sparepart
    WHERE t.id_bengkel = '$id_bengkel'
";

$filter = "";

// Filter jenis
if ($jenis != "") {
    $jenis = mysqli_real_escape_string($conn, $jenis);
    $filter .= " AND LOWER(t.jenis) = LOWER('$jenis')";
}

// Filter tanggal
if ($tgl_mulai != "" && $tgl_selesai != "") {
    $filter .= " AND DATE(t.tanggal) BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
}

// Search global
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $filter .= " AND (
        t.no_faktur LIKE '%$search%' OR
        c.nama_pelanggan LIKE '%$search%' OR
        s.nama_supplier LIKE '%$search%'
    )";
}

// =====================
// TOTAL DATA (TANPA FILTER)
// =====================
$qTotal = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM transaksi 
    WHERE id_bengkel = '$id_bengkel'
");
$dTotal = mysqli_fetch_assoc($qTotal);
$recordsTotal = $dTotal['total'];

// =====================
// TOTAL DATA FILTERED
// =====================
$qFiltered = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM (
        SELECT t.id_transaksi
        $baseQuery
        $filter
        GROUP BY t.id_transaksi
    ) x
");
$dFiltered = mysqli_fetch_assoc($qFiltered);
$recordsFiltered = $dFiltered['total'];

// =====================
// QUERY DATA UTAMA
// =====================
$queryData = "
    SELECT 
        t.id_transaksi,
        t.tanggal,
        t.no_faktur,
        t.jenis,
        t.total,
        t.status,

        c.nama_pelanggan,
        s.nama_supplier,

        IFNULL(SUM(td.qty * sp.hpp_per_pcs), 0) AS hpp

    $baseQuery
    $filter
    GROUP BY t.id_transaksi
    ORDER BY t.tanggal DESC
    LIMIT $start, $length
";

$qData = mysqli_query($conn, $queryData);

// =====================
// OLAH DATA
// =====================
$data = [];
$no = $start + 1;

while ($row = mysqli_fetch_assoc($qData)) {

    // Nama pelanggan / supplier
    if ($row['jenis'] === 'penjualan') {
        $nama = $row['nama_pelanggan'] ?: "-";
    } else {
        $nama = $row['nama_supplier'] ?: "-";
    }

    // Hitung HPP & LABA
    $hpp   = (float)$row['hpp'];
    $total = (float)$row['total'];
    $laba  = $total - $hpp;

    // Status badge
    $badge = "<span class='badge bg-success'>Selesai</span>";
    if ($row['status'] === 'pending') {
        $badge = "<span class='badge bg-warning text-dark'>Pending</span>";
    } elseif ($row['status'] === 'batal') {
        $badge = "<span class='badge bg-danger'>Batal</span>";
    }

    $data[] = [
        $no++,
        date("d-m-Y", strtotime($row['tanggal'])),
        $row['no_faktur'],
        $nama,
        ucfirst($row['jenis']),
        "Rp " . number_format($hpp, 0, ',', '.'),
        "Rp " . number_format($total, 0, ',', '.'),
        "Rp " . number_format($laba, 0, ',', '.'),
        $badge
    ];
}

// =====================
// OUTPUT JSON
// =====================
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
]);
