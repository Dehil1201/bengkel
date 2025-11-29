<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$id_detail = $_POST['id_detail'] ?? '';
$diskon    = $_POST['diskon'] ?? '';

// Validasi wajib
if (empty($id_detail) || $diskon === '') {
    echo json_encode([
        "status_code" => 400,
        "message" => "Parameter id_detail dan diskon wajib diisi."
    ]);
    exit;
}

// ✅ Izinkan decimal (contoh: 2.5, 10.75)
$diskon = str_replace(',', '.', $diskon);                     // jaga-jaga input koma
$diskon = preg_replace('/[^0-9.]/', '', $diskon);            // hanya angka & titik
$diskon = (float) $diskon;

if ($diskon < 0)   $diskon = 0;
if ($diskon > 100) $diskon = 100;

// ✅ Rumus subtotal KONSISTEN (support decimal):
// subtotal = (harga * qty) - ((harga * qty) * discount / 100)

$sql = "
    UPDATE transaksi_detail_sparepart 
    SET 
        discount = ?,
        subtotal = ( (harga * qty) - ((harga * qty) * ? / 100) )
    WHERE id_detail = ?
";

$stmt = mysqli_prepare($conn, $sql);

// ✅ ganti bind "iis" → "dds"
mysqli_stmt_bind_param($stmt, "dds", $diskon, $diskon, $id_detail);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status_code" => 200,
        "message" => "Diskon berhasil diperbarui."
    ]);
} else {
    echo json_encode([
        "status_code" => 500,
        "message" => "Gagal memperbarui diskon.",
        "error" => mysqli_error($conn)
    ]);
}

mysqli_stmt_close($stmt);
