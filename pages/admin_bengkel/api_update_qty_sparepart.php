<?php
include "../../inc/koneksi.php";

$id_detail = $_POST['id_detail'] ?? null;
$qty       = $_POST['qty'] ?? null;

if ($id_detail && $qty) {
    $stmt = $conn->prepare("UPDATE transaksi_detail_sparepart SET qty = ?, subtotal = harga * ? WHERE id_detail = ?");
    $stmt->bind_param("iii", $qty, $qty, $id_detail);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
}
?>
