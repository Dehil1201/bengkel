<?php
include "../../inc/koneksi.php";

$id_detail = $_POST['id_detail'] ?? null;

if ($id_detail) {
    $stmt = $conn->prepare("DELETE FROM transaksi_detail_sparepart WHERE id_detail = ?");
    $stmt->bind_param("i", $id_detail);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
}
?>
