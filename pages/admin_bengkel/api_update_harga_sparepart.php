<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$id_detail = $_POST['id_detail'] ?? '';
$harga     = $_POST['harga'] ?? 0;

// Validasi wajib
if (empty($id_detail)) {
    echo json_encode([
        'status_code' => 400,
        'message' => 'Parameter id_detail wajib diisi.'
    ]);
    exit;
}

// Pastikan harga benar-benar INT (tanpa format 1.000.000)
$harga = (int) preg_replace('/[^0-9]/', '', $harga);

// ✅ Rumus subtotal yang benar:
// subtotal = (harga * qty) - ((harga * qty) * discount / 100)

$sql = "
    UPDATE transaksi_detail_sparepart 
    SET 
        harga = ?,
        subtotal = ( (? * qty) - ((? * qty) * discount / 100) )
    WHERE id_detail = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiis", $harga, $harga, $harga, $id_detail);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status_code' => 200,
        'message' => 'Harga berhasil diperbarui.'
    ]);
} else {
    echo json_encode([
        'status_code' => 500,
        'message' => 'Gagal memperbarui harga.',
        'error' => mysqli_error($conn)
    ]);
}

mysqli_stmt_close($stmt);
