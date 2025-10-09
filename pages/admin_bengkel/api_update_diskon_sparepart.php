<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$id_detail = $_POST['id_detail'] ?? '';
$diskon = $_POST['diskon'] ?? 0;

if (!$id_detail) {
    echo json_encode(['status_code' => 400, 'message' => 'Parameter id_detail wajib diisi.']);
    exit;
}

// Update data di tabel detail transaksi
$q = "UPDATE transaksi_detail_sparepart 
      SET discount = '$diskon', 
          subtotal = (harga * qty) - ((harga * qty) * ($diskon / 100)) 
      WHERE id_detail = '$id_detail'";

if (mysqli_query($conn, $q)) {
    echo json_encode(['status_code' => 200, 'message' => 'Diskon berhasil diperbarui.']);
} else {
    echo json_encode(['status_code' => 500, 'message' => 'Gagal memperbarui diskon.']);
}
