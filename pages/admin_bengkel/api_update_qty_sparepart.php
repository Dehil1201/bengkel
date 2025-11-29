<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$id_detail = $_POST['id_detail'] ?? '';
$qty       = $_POST['qty'] ?? '';

// Validasi wajib
if (empty($id_detail) || $qty === '') {
    echo json_encode([
        "status_code" => 400,
        "message" => "Parameter id_detail dan qty wajib diisi."
    ]);
    exit;
}

// Pastikan qty benar-benar INT
$qty = (int) preg_replace('/[^0-9]/', '', $qty);
if ($qty < 1) $qty = 1;

// ✅ Rumus subtotal konsisten:
// subtotal = (harga * qty) - ((harga * qty) * discount / 100)

$sql = "
    UPDATE transaksi_detail_sparepart 
    SET 
        qty = ?,
        subtotal = ( (harga * ?) - ((harga * ?) * discount / 100) )
    WHERE id_detail = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiis", $qty, $qty, $qty, $id_detail);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status_code" => 200,
        "message" => "Qty berhasil diperbarui."
    ]);
} else {
    echo json_encode([
        "status_code" => 500,
        "message" => "Gagal memperbarui qty.",
        "error" => mysqli_error($conn)
    ]);
}

mysqli_stmt_close($stmt);
